@extends('layouts.admin')


@section('content')



<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4 flex justify-between">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl ">Interesados</h1>
            <a href="/contactexport">
                @svg('heroicon-o-arrow-down-on-square', 'w-8 h-8 text-blue-500')
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
                           
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Nombre
                            </th>
                          
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Celular
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Tienda / Ciudad
                            </th>

                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Nit / Cedula
                            </th>
                          
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">Fecha</th>

                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Estado
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 ">
                        @foreach ($contacts as $contact)
                        <tr class="hover:bg-gray-100">
                            
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                             
                                <div class="flex flex-col">
                                    <span>{{ $contact->name }}</span>
                                    <small>{{$contact->email}}</small>
                                </div>
                            </td>
                           
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                {{ $contact->phone }}
                            </td>
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span>{{ $contact->business_name }}</span>
                                    <small>{{$contact->city}}</small>
                                </div>
                            </td>
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                {{ $contact->nit}}
                            </td>
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                {{ $contact->created_at->subHours(5)}}
                            </td>
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                {{$contact->state}}
                            </td>
                            
                        
                        </tr>

                        @endforeach
            


                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


{{ $contacts->links() }} 

@endsection







