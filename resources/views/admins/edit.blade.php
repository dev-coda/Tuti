@extends('layouts.admin')


@section('content')

<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">{{$user->name}}</h1>
    </div>



    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Información</h3>
            {{ Aire::open()->route('admins.update', $user)->bind($user)}}
                
                <div class="grid grid-cols-2 gap-5">

                    {{ Aire::input('name', 'Nombre')->groupClass('mb-0') }}
                    {{ Aire::email('email', 'Correo electrónico')->groupClass('mb-0') }}

                    {{ Aire::password('password', 'Contraseña')->groupClass('mb-5')->value('') }}
                    {{ Aire::password('password_confirmation', 'Confirme Contraseña')->groupClass('mb-5') }}
                    
                </div>

                <div class="col-span-6 justify-between  items-center mt-5 space-x-2 flex">

                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Actualizar')->variant()->submit() }}
                        <a href="{{ route('admins.index') }}">Cancelar</a>
                    </p> 

                    
                    <div class="flex items-center space-x-2">
                        <span class="text-sm font-medium text-gray-900">Estado</span>

                        <input type="hidden" name="status_id" value="0"> 
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input id="statusSwitch" type="checkbox" name='status_id' value="1" class="sr-only peer" @checked($user->status_id)>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                <span id="statusText" class="ml-3 text-sm font-medium text-gray-900">
                                    {{ $user->status_id ? 'Activo' : 'Inactivo' }}
                                </span>
                            </label>
                    </div>

                </div>

            {{ Aire::close() }}
        </div>
    </div>

   

   
</div>



@endsection

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const switchInput = document.getElementById("statusSwitch");
        const statusText = document.getElementById("statusText");

        switchInput.addEventListener("change", function () {
            statusText.textContent = switchInput.checked ? "Activo" : "Inactivo";
        });
    });
</script>


