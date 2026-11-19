<section class="mb-8">
    <h2 class="text-lg font-semibold text-gray-900">{{ $heading }}</h2>
    <p class="mb-3 text-sm text-gray-500">{{ $note }}</p>
    <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3 text-right">Disponible</th>
                    <th class="px-4 py-3 text-right">Venta</th>
                    <th class="px-4 py-3 text-right">Cobertura</th>
                    <th class="px-4 py-3 text-right">Comprar</th>
                    <th class="px-4 py-3">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('products.edit', $row['product_id']) }}" class="font-medium text-gray-900 hover:underline">{{ $row['name'] }}</a>
                            <div class="text-xs text-gray-500">{{ $row['sku'] }} · {{ $row['bodega_count'] }} {{ $row['bodega_count'] === 1 ? 'bodega' : 'bodegas' }}</div>
                        </td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['available']) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['units_sold']) }}</td>
                        <td class="px-4 py-3 text-right">{{ $row['cover_days'] === null ? '—' : number_format($row['cover_days'], 1) }}</td>
                        <td class="px-4 py-3 text-right">{{ $row['suggest'] > 0 ? number_format($row['suggest']) : '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $row['status_class'] }}">{{ $row['status_label'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ $empty }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
