<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class InventoryAgent
{
    public function __construct(private InventoryIntelligence $inventory) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, mode: string}
     */
    public function reply(string $message, array $history = []): array
    {
        $message = trim($message);
        if ($message === '') {
            return [
                'reply' => 'Escribe una pregunta sobre el inventario.',
                'mode' => 'local',
            ];
        }

        $key = config('services.openai.key');
        if (is_string($key) && $key !== '') {
            try {
                return $this->replyWithModel($message, $history, $key);
            } catch (\Throwable $exception) {
                report($exception);
                $local = $this->replyLocally($message);

                return [
                    'reply' => "No pude consultar el modelo. Esta respuesta sale de las herramientas locales.\n\n".$local['reply'],
                    'mode' => 'local',
                ];
            }
        }

        return $this->replyLocally($message);
    }

    /**
     * @return array{reply: string, mode: string}
     */
    private function replyLocally(string $message): array
    {
        $text = $this->normalize($message);
        $window = $this->inventory->windowDays(null);
        $cover = $this->inventory->coverDays(null);

        if ($this->contains($text, ['ayuda', 'que puedes', 'qué puedes', 'como te uso'])) {
            return ['reply' => $this->help(), 'mode' => 'local'];
        }

        if ($this->contains($text, ['bodega', 'almacen', 'almacén'])) {
            return ['reply' => $this->formatWarehouses(), 'mode' => 'local'];
        }

        if ($this->contains($text, ['reponer', 'reposicion', 'reposición', 'que pedir', 'qué pedir', 'que comprar', 'qué comprar', 'sugerenc'])) {
            return ['reply' => $this->formatProducts(
                'Para cubrir '.$cover.' días con la venta de los últimos '.$window.', compraría:',
                $this->inventory->reorder(null, $window, $cover, 8),
                'No hay productos con venta reciente que estén agotados, bajo el piso o con cobertura corta.'
            ), 'mode' => 'local'];
        }

        if ($this->contains($text, ['sin rotacion', 'sin rotación', 'no rota', 'muerto', 'no se vende', 'quieto'])) {
            return ['reply' => $this->formatProducts(
                'Productos con stock sobre el piso y sin venta en '.$window.' días:',
                $this->inventory->deadStock(null, $window, $cover, 8),
                'No hay productos activos con stock quieto en esa ventana.'
            ), 'mode' => 'local'];
        }

        if ($this->contains($text, ['bajo', 'agotad', 'critico', 'crítico', 'quiebre', 'piso'])) {
            return ['reply' => $this->formatProducts(
                'Productos agotados o en el piso de inventario:',
                $this->inventory->attention(null, $window, $cover, 8),
                'Ningún producto activo está agotado o bajo el piso.'
            ), 'mode' => 'local'];
        }

        if ($this->contains($text, ['resumen', 'como esta', 'cómo está', 'estado', 'panorama'])) {
            return ['reply' => $this->formatSummary($this->inventory->summary($window, $cover)), 'mode' => 'local'];
        }

        $found = $this->inventory->findProducts($message, $window, $cover, 3);
        if ($found !== []) {
            return ['reply' => $this->formatFound($found, $window), 'mode' => 'local'];
        }

        return [
            'reply' => "No encontré un producto ni una consulta de inventario en ese mensaje.\n\n".$this->help(),
            'mode' => 'local',
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, mode: string}
     */
    private function replyWithModel(string $message, array $history, string $key): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
        ];

        foreach (array_slice($history, -6) as $turn) {
            $role = $turn['role'] ?? '';
            $content = trim((string) ($turn['content'] ?? ''));
            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }

        $messages[] = ['role' => 'user', 'content' => $message];
        $first = $this->complete($key, $messages, $this->toolDefinitions());
        $assistant = $first['choices'][0]['message'] ?? [];
        $calls = $assistant['tool_calls'] ?? [];

        if ($calls === []) {
            $text = trim((string) ($assistant['content'] ?? ''));

            return [
                'reply' => $text !== '' ? $text : 'El modelo no devolvió una respuesta.',
                'mode' => 'model',
            ];
        }

        $messages[] = [
            'role' => 'assistant',
            'content' => $assistant['content'] ?? null,
            'tool_calls' => $calls,
        ];

        foreach ($calls as $call) {
            $name = (string) ($call['function']['name'] ?? '');
            $args = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);
            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => (string) ($call['id'] ?? ''),
                'content' => json_encode($this->runTool($name, is_array($args) ? $args : []), JSON_UNESCAPED_UNICODE),
            ];
        }

        $second = $this->complete($key, $messages, []);
        $text = trim((string) ($second['choices'][0]['message']['content'] ?? ''));

        return [
            'reply' => $text !== '' ? $text : 'El modelo no redactó una respuesta con los datos del inventario.',
            'mode' => 'model',
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>|array<int, mixed>
     */
    private function runTool(string $name, array $args): array
    {
        $window = $this->inventory->windowDays($args['window_days'] ?? null);
        $cover = $this->inventory->coverDays($args['cover_days'] ?? null);
        $bodega = trim((string) ($args['bodega'] ?? ''));
        $bodega = $bodega === '' ? null : $bodega;

        return match ($name) {
            'resumen_inventario' => $this->inventory->summary($window, $cover, $bodega),
            'listar_bodegas' => $this->inventory->warehouses(),
            'productos_bajo_stock' => $this->inventory->attention($bodega, $window, $cover, 12),
            'productos_sin_rotacion' => $this->inventory->deadStock($bodega, $window, $cover, 12),
            'sugerencias_reposicion' => $this->inventory->reorder($bodega, $window, $cover, 12),
            'buscar_producto' => $this->inventory->findProducts((string) ($args['consulta'] ?? ''), $window, $cover, 5),
            default => ['error' => 'Herramienta desconocida'],
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    private function complete(string $key, array $messages, array $tools): array
    {
        $payload = [
            'model' => config('services.openai.model', 'gpt-4o-mini'),
            'temperature' => 0.2,
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withToken($key)
            ->timeout(25)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI respondió HTTP '.$response->status());
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('OpenAI devolvió una respuesta vacía.');
        }

        return $json;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolDefinitions(): array
    {
        $filters = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'bodega' => ['type' => 'string', 'description' => 'Código de bodega. Vacío para todas.'],
                'window_days' => ['type' => 'integer'],
                'cover_days' => ['type' => 'integer'],
            ],
        ];

        return [
            $this->tool('resumen_inventario', 'Totales de stock, agotados, bajo piso, cobertura corta y stock sin rotación.', $filters),
            $this->tool('listar_bodegas', 'Unidades, agotados y líneas por bodega.', ['type' => 'object', 'additionalProperties' => false, 'properties' => (object) []]),
            $this->tool('productos_bajo_stock', 'Productos activos agotados o por debajo del piso.', $filters),
            $this->tool('productos_sin_rotacion', 'Productos activos con stock sobre el piso y sin venta en la ventana.', $filters),
            $this->tool('sugerencias_reposicion', 'Cantidad sugerida para cubrir la demanda reciente sin quedar bajo el piso. Solo productos con venta.', $filters),
            $this->tool('buscar_producto', 'Busca un producto por nombre, SKU o bodega y devuelve stock por bodega.', [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'consulta' => ['type' => 'string'],
                ],
                'required' => ['consulta'],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function tool(string $name, string $description, array $parameters): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => $parameters,
            ],
        ];
    }

    private function systemPrompt(): string
    {
        return 'Eres el agente de inventario de Tuti en stage. Respondes en español, breve y concreto. '
            .'Solo usas cifras que devuelvan las herramientas. No inventes stock, ventas ni pedidos. '
            .'No puedes modificar inventario: Dynamics es la fuente y la sincronización pisa los cambios manuales. '
            .'La venta es de toda la compañía. Si se filtra una bodega, el disponible es solo de esa bodega. '
            .'Si falta un dato, dilo.';
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function formatSummary(array $summary): string
    {
        $sync = $summary['last_sync']
            ? "Última sincronización: {$summary['last_sync']['at']} ({$summary['last_sync']['bodega']}, {$summary['last_sync']['status']})."
            : 'Todavía no hay sincronizaciones registradas.';

        $management = $summary['inventory_enabled']
            ? 'La validación de inventario en la tienda está activa.'
            : 'La validación de inventario en la tienda está apagada. Estas cifras igual leen el stock sincronizado.';

        return implode("\n", [
            "Inventario de los últimos {$summary['window_days']} días. Cobertura objetivo: {$summary['cover_days']} días. Piso global: {$summary['global_floor']}.",
            "{$summary['products']} productos activos con stock registrado, {$summary['units_on_hand']} unidades disponibles.",
            "{$summary['stockouts']} agotados, {$summary['below_floor']} bajo el piso, {$summary['low_cover']} con cobertura corta, {$summary['dead']} sin rotación.",
            $management,
            $sync,
        ]);
    }

    private function formatWarehouses(): string
    {
        $rows = $this->inventory->warehouses();
        if ($rows === []) {
            return 'No hay bodegas con inventario de productos gestionados.';
        }

        $lines = ['Stock por bodega:'];
        foreach ($rows as $row) {
            $lines[] = "- {$row['bodega']}: {$row['units']} disponibles, {$row['stockouts']} agotados, {$row['below_floor']} bajo el piso ({$row['lines']} líneas).";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function formatProducts(string $title, array $rows, string $empty): string
    {
        if ($rows === []) {
            return $empty;
        }

        $lines = [$title];
        foreach ($rows as $row) {
            $cover = $row['cover_days'] === null ? 'sin venta' : $row['cover_days'].' días';
            $buy = $row['suggest'] > 0 ? " Compraría {$row['suggest']}." : '';
            $lines[] = "- {$row['name']} ({$row['sku']}): {$row['available']} disponibles, piso {$row['floor']}, venta {$row['units_sold']}, cobertura {$cover}.{$buy}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    private function formatFound(array $products, int $window): string
    {
        $blocks = [];
        foreach ($products as $product) {
            $lines = ["{$product['name']} ({$product['sku']}): {$product['available']} disponibles en total, {$product['units_sold']} vendidas en {$window} días."];
            foreach ($product['warehouses'] as $line) {
                $variation = $line['variation'] ? " / {$line['variation']}" : '';
                $lines[] = "- {$line['bodega']}{$variation}: {$line['available']} disponibles, {$line['reserved']} reservadas, piso {$line['floor']} ({$line['status_label']}).";
            }
            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
    }

    private function help(): string
    {
        return implode("\n", [
            'Puedo leer el inventario, no modificarlo. Prueba con:',
            '- resumen del inventario',
            '- productos bajo el piso',
            '- qué debo reponer',
            '- stock sin rotación',
            '- bodegas',
            '- el nombre o SKU de un producto',
        ]);
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function contains(string $text, array $needles): bool
    {
        $normalized = $this->normalize($text);
        foreach ($needles as $needle) {
            if (str_contains($normalized, $this->normalize($needle))) {
                return true;
            }
        }

        return false;
    }
}
