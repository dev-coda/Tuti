@extends('layouts.admin')


@section('content')


<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl ">Páginas de Contenido</h1>
            <p class="text-gray-600 mt-1">Gestiona las páginas de contenido personalizadas de tu sitio</p>
        </div>
        <div class="items-center justify-between block sm:flex md:divide-x md:divide-gray-100 ">
            <div class="flex items-center mb-4 sm:mb-0">
               <x-search :home="route('content-pages.index')" />
            </div>
            <a href="{{ route('content-pages.create') }}"
                class="text-white bg-blue-700 hover:bg-primary-800 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-2.5 ">
                Nueva Página
            </a>
        </div>
    </div>
</div>
<div class="flex flex-col">
    <div class="overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
            <div class="overflow-hidden shadow">
                <table class="min-w-full divide-y divide-gray-200 table-fixed ">
                    <thead class="bg-gray-100">
                        <tr>
                           
                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Título
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Slug
                            </th>
                           
                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Estado
                            </th>
                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Última Actualización
                            </th>
                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 ">

                        @forelse ($pages as $page)
                        <tr class="hover:bg-gray-100">
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                <a class="flex flex-col text-gray-900  hover:text-blue-500" href="{{ route('content-pages.edit', $page) }}">
                                    <span class="text-base font-semibold ">
                                        {{ $page->title }}
                                    </span>
                                </a>
                            </td>

                            <td class="p-4 text-base font-medium text-gray-900 whitespace-nowra">
                                <code class="bg-gray-100 px-2 py-1 rounded text-sm">/contenido/{{ $page->slug }}</code>
                            </td>
                           
                            <td class="p-4 text-base  text-gray-900 whitespace-nowra">
                                <div class="flex items-center">
                                    <div @class([
                                        'inline-block w-4 h-4 mr-2 rounded-full', 
                                        'bg-green-700' => $page->enabled,
                                        'bg-red-700' => !$page->enabled
                                        ])></div>
                                    {{ $page->enabled ? 'Habilitado' : 'Deshabilitado' }}
                                </div>
                            </td>

                            <td class="p-4 text-sm text-gray-500 whitespace-nowra">
                                {{ $page->updated_at->format('d/m/Y H:i') }}
                            </td>
                          

                            <td class="p-4 space-x-2 whitespace-nowrap text-end">
                                @if($page->enabled)
                                <a href="{{ route('contenido.show', $page->slug) }}"
                                    target="_blank"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-gray-700 rounded-lg bg-gray-100 hover:bg-gray-200 focus:ring-4 focus:ring-gray-300"
                                    title="Ver página pública">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                </a>
                                @endif
                                <a href="{{ route('content-pages.edit', $page) }}"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 ">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z">
                                        </path>
                                        <path fill-rule="evenodd"
                                            d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    Editar
                                </a>
                              
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-12 h-12 mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p class="text-lg font-medium">No hay páginas de contenido creadas</p>
                                    <p class="text-sm mt-1">Crea tu primera página de contenido para comenzar</p>
                                    <a href="{{ route('content-pages.create') }}" class="mt-4 text-blue-600 hover:text-blue-800 font-medium">
                                        Crear Nueva Página →
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
            


                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


{{ $pages->links() }} 









@endsection

