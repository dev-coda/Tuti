@extends('layouts.admin')


@section('content')
{{ Aire::open()->route('content-pages.update', $contentPage)->bind($contentPage)}}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Editar Página de Contenido</h1>
                <p class="text-gray-600 mt-1">{{ $contentPage->title }}</p>
            </div>
            @if($contentPage->enabled)
            <a 
                href="{{ route('contenido.show', $contentPage->slug) }}" 
                target="_blank"
                class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 px-4 rounded-lg transition-colors inline-flex items-center"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                </svg>
                Ver Página Pública
            </a>
            @endif
        </div>
    </div>

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
                        data-content="{{ json_encode($contentPage->content ?? '', JSON_UNESCAPED_UNICODE) }}"
                        data-content-encoding="json"
                        data-name="content"
                        data-placeholder="Escribe el contenido de la página aquí..."
                        data-height="500px"
                    ></div>
                    
                    <p class="mt-2 text-sm text-gray-500">
                        Utiliza el editor para dar formato al contenido de tu página
                    </p>
                </div>

                <div class="col-span-6 justify-between  items-center mt-5 space-x-2 flex">

                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Actualizar')->variant()->submit() }}
                        <a href="{{ route('content-pages.index') }}">Cancelar</a>
                    </p>

                    
                    <x-remove-button />  
                   
                </div>
            </div>


        </div>
    </div>

    <div class="col-span-full xl:col-auto">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2">
            <h3 class="mb-4 text-xl font-semibold ">Configuración</h3>
            
            <div>
                {{ Aire::hidden('enabled')->value(0)}}
                <label class="relative inline-flex items-center cursor-pointer">
                    <input @checked($contentPage->enabled) type="checkbox" name='enabled' value="1" class="sr-only peer">
                    <div
                        class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300  rounded-full peer  peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all0 peer-checked:bg-blue-600">
                    </div>
                    <span class="ml-3 text-sm font-medium text-gray-900 ">Página Habilitada</span>
                </label>
                <p class="mt-2 text-sm text-gray-500">
                    Si está habilitada, la página será visible públicamente en /contenido/{slug}
                </p>
            </div>

            <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">
                    Información de la Página
                </h4>
                <dl class="text-xs space-y-2">
                    <div>
                        <dt class="text-gray-500">Creado</dt>
                        <dd class="text-gray-900">{{ $contentPage->created_at->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Última actualización</dt>
                        <dd class="text-gray-900">{{ $contentPage->updated_at->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">URL pública</dt>
                        <dd class="text-gray-900 break-all">
                            <code class="bg-white px-2 py-1 rounded">/contenido/{{ $contentPage->slug }}</code>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
{{ Aire::close() }}






<x-remove-drawer title="Página de Contenido" route='content-pages.destroy' :item='$contentPage' />


@endsection

