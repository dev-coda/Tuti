@extends('layouts.admin')


@section('content')

<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Crear supernumerario</h1>
        <p class="mt-1 text-sm text-gray-500">Puedes habilitarlo en todas las zonas o limitarlo a una zona.</p>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Información</h3>
            {{ Aire::open()->route('supernumerarios.store')}}
                <div class="grid grid-cols-2 gap-5">

                    {{ Aire::input('name', 'Nombre')->groupClass('mb-0') }}
                    {{ Aire::email('email', 'Correo electrónico')->groupClass('mb-0') }}

                    @php
                        $coversAllOld = old('covers_all_zones', '1');
                        if (is_array($coversAllOld)) {
                            $coversAllOld = end($coversAllOld);
                        }
                    @endphp
                    <div class="col-span-2">
                        <input type="hidden" name="covers_all_zones" value="0">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="covers_all_zones" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" @checked((string) $coversAllOld === '1' || $coversAllOld === true)>
                            Habilitar en todas las zonas
                        </label>
                        <p class="mt-1 text-xs text-gray-500">Si está activo, opera en todas las zonas y rutas. Si no, queda limitado a la zona indicada.</p>
                    </div>

                    {{ Aire::number('zone', 'Zona (si no cubre todas)')->groupClass('mb-0') }}
                    <div></div>

                    {{ Aire::password('password', 'Contraseña')->groupClass('mb-5') }}
                    {{ Aire::password('password_confirmation', 'Confirme Contraseña')->groupClass('mb-5') }}

                </div>

                <div class="col-span-6 justify-between  items-center mt-5 space-x-2 flex">

                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Guardar')->variant()->submit() }}
                        <a href="{{ route('supernumerarios.index') }}">Cancelar</a>
                    </p>
                </div>
            {{ Aire::close() }}
        </div>
    </div>


</div>



@endsection
