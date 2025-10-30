@extends('layouts.admin')


@section('content')
    {{ Aire::open()->route('content-pages.store')}}
    <div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
        <div class="mb-4 col-span-full xl:mb-2">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Nueva Página de Contenido</h1>
            <p class="text-gray-600 mt-1">Crea una nueva página de contenido personalizada</p>
        </div>
        <!-- Right Content -->

        <div class="col-span-2">
            <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
                <h3 class="mb-4 text-xl font-semibold ">Información de la Página</h3>

                <div class="grid grid-cols-6 gap-6">

                    {{ Aire::input('title', "Título")->groupClass('col-span-6')->helpText('El título que se mostrará en la página') }}
                    
                    {{ Aire::input('slug', "Slug")->groupClass('col-span-6')->helpText('URL amigable (ej: terminos-y-condiciones). Solo letras minúsculas, números y guiones') }}

                    <div class="col-span-6">
                        <label class="block text-sm font-medium text-gray-700 mb-3">
                            Contenido
                        </label>
                        
                        <!-- Vue Rich Text Editor Component -->
                        <div 
                            class="rich-text-editor-mount" 
                            data-content=""
                            data-content-encoding="json"
                            data-name="content"
                            data-placeholder="Escribe el contenido de la página aquí..."
                            data-height="500px"
                        ></div>
                        
                        <p class="mt-2 text-sm text-gray-500">
                            Utiliza el editor para dar formato al contenido de tu página
                        </p>
                    </div>

                    <div class="col-span-6 items-center space-x-2 flex">
                        {{ Aire::submit('Crear Página')->variant()->submit() }}
                        <a href="{{ route('content-pages.index') }}">Cancelar</a>
                    </div>
                </div>


            </div>
        </div>

        <div class="col-span-full xl:col-auto">
            <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2">
                <h3 class="mb-4 text-xl font-semibold ">Configuración</h3>
                
                <div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" checked name='enabled' value="1" class="sr-only peer">
                        <div
                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300  rounded-full peer  peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all0 peer-checked:bg-blue-600">
                        </div>
                        <span class="ml-3 text-sm font-medium text-gray-900 ">Página Habilitada</span>
                    </label>
                    <p class="mt-2 text-sm text-gray-500">
                        Si está habilitada, la página será visible públicamente en /contenido/{slug}
                    </p>
                </div>

                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h4 class="text-sm font-semibold text-blue-900 mb-2">
                        <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        Sobre el Slug
                    </h4>
                    <p class="text-xs text-blue-800">
                        El slug es la parte de la URL que identifica esta página. Por ejemplo, si el slug es "terminos-condiciones", la página estará disponible en: <br>
                        <code class="bg-white px-2 py-1 rounded mt-1 inline-block">/contenido/terminos-condiciones</code>
                    </p>
                </div>
            </div>
        </div>
    </div>
    {{ Aire::close() }}


@endsection

