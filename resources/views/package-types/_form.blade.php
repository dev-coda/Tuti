@if ($errors->any())
    <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php($packageType = $packageType ?? null)

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-3xl">
    <div>
        <label for="code" class="block mb-1 text-sm font-medium text-gray-900">Código</label>
        <input type="text" id="code" name="code" value="{{ old('code', $packageType?->code) }}" required
            class="border border-gray-300 rounded-lg text-sm px-3 py-2 w-full" placeholder="Ej: M">
    </div>
    <div>
        <label for="name" class="block mb-1 text-sm font-medium text-gray-900">Nombre</label>
        <input type="text" id="name" name="name" value="{{ old('name', $packageType?->name) }}" required
            class="border border-gray-300 rounded-lg text-sm px-3 py-2 w-full" placeholder="Ej: Mediano">
    </div>
    <div>
        <label for="max_length_cm" class="block mb-1 text-sm font-medium text-gray-900">Largo máx. (cm)</label>
        <input type="number" step="0.01" min="0.01" id="max_length_cm" name="max_length_cm"
            value="{{ old('max_length_cm', $packageType?->max_length_cm) }}" required
            class="border border-gray-300 rounded-lg text-sm px-3 py-2 w-full">
    </div>
    <div>
        <label for="max_width_cm" class="block mb-1 text-sm font-medium text-gray-900">Ancho máx. (cm)</label>
        <input type="number" step="0.01" min="0.01" id="max_width_cm" name="max_width_cm"
            value="{{ old('max_width_cm', $packageType?->max_width_cm) }}" required
            class="border border-gray-300 rounded-lg text-sm px-3 py-2 w-full">
    </div>
    <div>
        <label for="max_height_cm" class="block mb-1 text-sm font-medium text-gray-900">Alto máx. (cm)</label>
        <input type="number" step="0.01" min="0.01" id="max_height_cm" name="max_height_cm"
            value="{{ old('max_height_cm', $packageType?->max_height_cm) }}" required
            class="border border-gray-300 rounded-lg text-sm px-3 py-2 w-full">
    </div>
    <div>
        <label for="max_weight_kg" class="block mb-1 text-sm font-medium text-gray-900">Peso máx. (kg)</label>
        <input type="number" step="0.001" min="0.001" id="max_weight_kg" name="max_weight_kg"
            value="{{ old('max_weight_kg', $packageType?->max_weight_kg) }}" required
            class="border border-gray-300 rounded-lg text-sm px-3 py-2 w-full">
    </div>
    <div>
        <label for="position" class="block mb-1 text-sm font-medium text-gray-900">Orden</label>
        <input type="number" step="1" min="0" id="position" name="position"
            value="{{ old('position', $packageType?->position ?? 0) }}"
            class="border border-gray-300 rounded-lg text-sm px-3 py-2 w-full">
    </div>
    <div class="flex items-center pt-6">
        <input type="checkbox" id="active" name="active" value="1"
            @checked(old('active', $packageType?->active ?? true))
            class="w-4 h-4 border border-gray-300 rounded">
        <label for="active" class="ml-2 text-sm font-medium text-gray-900">Activo</label>
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-800">
        Guardar
    </button>
    <a href="{{ route('package-types.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:underline self-center">Cancelar</a>
</div>
