@extends('layouts.admin')

@section('content')
<div class="py-8">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        @include('admin.inventory._nav', [
            'title' => 'Agente de inventario',
            'subtitle' => $modelEnabled
                ? 'Pregunta en español. Con OPENAI_API_KEY el modelo consulta las mismas herramientas de stock y ventas. Sin clave, las herramientas responden directo.'
                : 'Pregunta en español. No hay OPENAI_API_KEY en este ambiente, así que las herramientas de stock y ventas responden directo.',
        ])

        <div id="agent-log" class="mb-4 space-y-3">
            @forelse ($messages as $message)
                <div class="flex {{ ($message['role'] ?? '') === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[85%] rounded-lg px-4 py-3 text-sm {{ ($message['role'] ?? '') === 'user' ? 'bg-gray-900 text-white' : 'bg-white text-gray-800 shadow-sm ring-1 ring-gray-200' }}">
                        <p class="whitespace-pre-wrap">{{ $message['content'] ?? '' }}</p>
                        @if (($message['role'] ?? '') === 'assistant')
                            <p class="mt-2 text-xs {{ ($message['role'] ?? '') === 'user' ? 'text-gray-300' : 'text-gray-400' }}">
                                {{ ($message['mode'] ?? 'local') === 'model' ? 'Modelo' : 'Herramientas locales' }}
                            </p>
                        @endif
                    </div>
                </div>
            @empty
                <p id="agent-empty" class="text-sm text-gray-500">Prueba “resumen del inventario”, “qué debo reponer” o el SKU de un producto.</p>
            @endforelse
        </div>

        <div class="mb-3 flex flex-wrap gap-2">
            @foreach (['Resumen del inventario', 'Productos bajo el piso', 'Qué debo reponer', 'Stock sin rotación', 'Bodegas'] as $prompt)
                <button type="button" class="agent-prompt rounded-full bg-white px-3 py-1 text-sm text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50" data-prompt="{{ $prompt }}">{{ $prompt }}</button>
            @endforeach
        </div>

        <form id="agent-form" method="POST" action="{{ route('admin.inventory.ask') }}" class="flex gap-2">
            @csrf
            <label for="message" class="sr-only">Pregunta</label>
            <input id="message" name="message" maxlength="500" required class="w-full rounded-md border-gray-300 text-sm shadow-sm" placeholder="Pregunta por stock, rotación o un SKU" value="{{ old('message') }}">
            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white">Enviar</button>
        </form>
        @error('message')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ route('admin.inventory.agent.clear') }}" class="mt-3">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-gray-500 underline">Limpiar conversación</button>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const form = document.getElementById('agent-form');
    const input = document.getElementById('message');
    const log = document.getElementById('agent-log');

    document.querySelectorAll('.agent-prompt').forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.dataset.prompt;
            form.requestSubmit();
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message) {
            return;
        }

        const body = new FormData(form);
        body.set('message', message);
        appendBubble(message, true);
        input.value = '';
        const pending = appendBubble('Consultando inventario…', false);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body,
            });
            const payload = await response.json();
            pending.querySelector('p').textContent = payload.reply || payload.message || 'No pude leer la respuesta.';
            const mode = document.createElement('p');
            mode.className = 'mt-2 text-xs text-gray-400';
            mode.textContent = payload.mode === 'model' ? 'Modelo' : 'Herramientas locales';
            pending.appendChild(mode);
        } catch (error) {
            pending.querySelector('p').textContent = 'No pude consultar el agente.';
        }
    });

    function appendBubble(text, fromUser) {
        document.getElementById('agent-empty')?.remove();
        const wrap = document.createElement('div');
        wrap.className = 'flex ' + (fromUser ? 'justify-end' : 'justify-start');
        const bubble = document.createElement('div');
        bubble.className = 'max-w-[85%] rounded-lg px-4 py-3 text-sm ' + (fromUser ? 'bg-gray-900 text-white' : 'bg-white text-gray-800 shadow-sm ring-1 ring-gray-200');
        const paragraph = document.createElement('p');
        paragraph.className = 'whitespace-pre-wrap';
        paragraph.textContent = text;
        bubble.appendChild(paragraph);
        wrap.appendChild(bubble);
        log.appendChild(wrap);
        return bubble;
    }
</script>
@endsection
