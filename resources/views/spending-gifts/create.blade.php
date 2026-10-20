@extends('layouts.admin')

@section('content')
{{ Aire::open()->route('spending-gifts.store') }}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Nuevo obsequio por monto</h1>
        <p class="mt-1 text-sm text-gray-500">Después de crearlo vas a elegir los productos que suman para alcanzar el monto. Cada obsequio puede ser un producto distinto.</p>
    </div>
    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-xl font-semibold">Información</h3>
            @include('spending-gifts._fields')
            <div class="col-span-6 justify-between items-center mt-5 space-x-2 flex">
                <p class="flex space-x-2 items-center">
                    {{ Aire::submit('Crear')->variant()->submit() }}
                </p>
                <a href="{{ route('spending-gifts.index') }}">Cancelar</a>
            </div>
        </div>
    </div>
</div>
{{ Aire::close() }}
@endsection
