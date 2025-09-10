@extends('layouts.admin')

@section('content')
<div class="p-4">
    <div class="bg-white rounded-lg shadow">
        <div class="p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-4">Editar Festivo</h1>

            {{ Aire::open()->route('holidays.update', $holiday)->bind($holiday) }}

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    {{ Aire::select([1 => 'Festivo', 2 => 'Sábado'], 'type_id')->required() }}
                </div>
                <div>
                    {{ Aire::input('date')->type('date')->required() }}
                </div>
            </div>

            <div class="mt-6 flex space-x-4">
                {{ Aire::submit('Actualizar') }}
                <a href="{{ route('holidays.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    Cancelar
                </a>
            </div>

            {{ Aire::close() }}
        </div>
    </div>
</div>
@endsection