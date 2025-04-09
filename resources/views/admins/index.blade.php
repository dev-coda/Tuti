@extends('layouts.admin')


@section('content')


<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl ">Administradores</h1>
        </div>
        <div class="items-center justify-between block sm:flex md:divide-x md:divide-gray-100 ">
            <div class="flex items-center mb-4 sm:mb-0">
               <x-search :home="route('admins.index')" />
            </div>
             <a href="{{ route('admins.create') }}"
                class="text-white bg-blue-700 hover:bg-primary-800 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-2.5 ">
                Nuevo admin
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
                                Nombre
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Email
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Estado
                            </th>
                           
                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 ">

                        <tr class="hover:bg-gray-100">
                        @foreach ($users as $admin)
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                <a class="flex flex-col text-gray-900  hover:text-blue-500" href="{{ route('admins.edit', $admin) }}">
                                    <span class="text-base font-semibold ">
                                        {{ $admin->name }}
                                    </span>
                                   
                                </a>
                            </td>

                            <td class="p-4 text-base  text-gray-900 whitespace-nowra">
                                {{$admin->email}}
                            </td>


                            <td class="p-4 text-base text-gray-900 whitespace-nowrap">
                                @if ($admin->status_id === 0)
                                    <span class="px-2 py-1 text-xs font-semibold text-red-600 bg-red-100 rounded">Inactivo</span>
                                @elseif ($admin->status_id === 1)
                                    <span class="px-2 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded">Activo</span>
                                @elseif ($admin->status_id === 2)
                                    <span class="px-2 py-1 text-xs font-semibold text-yellow-600 bg-yellow-100 rounded">Suspendido</span>
                                @endif
                            </td>

                            <td class="p-4 space-x-2 whitespace-nowrap text-end">
                                <a href="{{ route('admins.edit', $admin) }}"
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


                                <form action="{{ route('admins.destroy', $admin->id) }}" method="POST" onsubmit="return confirm('¿Estás seguro de eliminar este usuario?');" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-red-600 hover:bg-red-700 focus:ring-4 focus:ring-red-300">
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd"
                                                d="M6 2a1 1 0 000 2h8a1 1 0 100-2H6zM4 5a1 1 0 00-1 1v10a2 2 0 002 2h10a2 2 0 002-2V6a1 1 0 00-1-1H4zm4 4a1 1 0 112 0v4a1 1 0 11-2 0V9zm4 0a1 1 0 112 0v4a1 1 0 11-2 0V9z"
                                                clip-rule="evenodd"></path>
                                        </svg>
                                    Eliminar
                                    </button>
                                </form>
                              
                            </td>
                        </tr>

                        @endforeach
            


                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


{{ $users->links() }} 











@endsection
