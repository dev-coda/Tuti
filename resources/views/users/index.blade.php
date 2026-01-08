@extends('layouts.admin')


@section('content')


<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
    <div class="mb-4 flex justify-between">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl ">Clientes</h1>
            <a href="/userexport">
                @svg('heroicon-o-arrow-down-on-square', 'w-8 h-8 text-blue-500')
            </a>
        </div>
        <div class="items-center justify-between block sm:flex md:divide-x md:divide-gray-100 ">
            <div class="flex items-center mb-4 sm:mb-0">
               <x-search :home="route('users.index')" />
            </div>
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
                                Razón Social
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Teléfono
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Tipo de Cliente
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Saldo
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
                            @foreach ($users as $user)
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                <a class="flex flex-col text-gray-900  hover:text-blue-500" href="{{ route('users.edit', $user) }}">
                                    <span class="text-base font-semibold ">
                                        {{ $user->name }}
                                    </span>
                                     <small class="text-xs  text-slate-500">
                                        {{ $user->document }}
                                    </small>

                                </a>
                            </td>

                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                {{ $user->business_name ?: '-' }}
                            </td>

                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                {{ $user->phone ?: $user->mobile_phone ?: '-' }}
                            </td>

                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                {{ $user->customer_type ?: '-' }}
                            </td>

                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                ${{ number_format($user->balance ?? 0, 0, ',', '.') }}
                            </td>

                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                @if($user->is_locked)
                                    <span class="px-2 py-1 text-xs font-semibold text-white bg-red-500 rounded">Bloqueado</span>
                                @elseif($user->customer_status == 'No')
                                    <span class="px-2 py-1 text-xs font-semibold text-white bg-yellow-500 rounded">Inactivo</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold text-white bg-green-500 rounded">Activo</span>
                                @endif
                            </td>




                            <td class="p-4 space-x-2 whitespace-nowrap text-end">
                                <a href="{{ route('users.edit', $user) }}"
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

                        @endforeach
            


                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


{{ $users->withQueryString()->links() }} 











@endsection
