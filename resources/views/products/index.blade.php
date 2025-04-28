@extends('layouts.admin')


@section('content')


<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
    <div class="mb-4 flex justify-between">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl ">Productos</h1>
           <a href="/productexport">
                @svg('heroicon-o-arrow-down-on-square', 'w-8 h-8 text-blue-500')
            </a> 
        </div>
        <div class="items-center justify-between block sm:flex md:divide-x md:divide-gray-100 ">
            <div class="flex items-center mb-4 sm:mb-0">
               <x-search :home="route('products.index')" />
            </div>

            <a href="{{ route('products.create') }}"
            class="text-white bg-blue-700 hover:bg-primary-800 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-2.5 ">
                Nuevo producto
            </a>
{{--            
      
            <button id="dropdownDefaultButton" data-dropdown-toggle="dropdown" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2.5 text-center inline-flex items-center " type="button">Nuevo<svg class="w-4 h-4 ml-2" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></button>
            <!-- Dropdown menu -->
            <div id="dropdown" class="z-10 hidden bg-white divide-y divide-gray-100 rounded-lg shadow w-44 ">
                <ul class="py-2 text-sm text-gray-700" aria-labelledby="dropdownDefaultButton">
                    <li>
                        <a href="{{ route('products.create') }}" class="block px-4 py-2 hover:bg-gray-100 ">Producto</a>
                    </li>
                    <li>
                        <a href="#" class="block px-4 py-2 hover:bg-gray-100">Combiando</a>
                    </li>
                 
                 
                </ul>
            </div> --}}


          




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
                                <a href="{{ route('products.index', array_merge(request()->all(), ['sort_by' => 'name', 'order' => request('order') == 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center">
                                    Nombre
                                    @if (request('sort_by') == 'name')
                                        <span class="ml-2">{{ request('order') == 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </a>
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                <a href="{{ route('products.index', ['sort_by' => 'sku', 'order' => request('order') == 'asc' ? 'desc' : 'asc'] + request()->except('page')) }}">
                                    SKU
                                    @if (request('sort_by') == 'sku')
                                        <span class="ml-2">{{ request('order') == 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </a>
                            </th>
                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                <a href="{{ route('products.index', ['sort_by' => 'price', 'order' => request('order') == 'asc' ? 'desc' : 'asc'] + request()->except('page')) }}">
                                    Precio
                                    @if (request('sort_by') == 'price')
                                        <span class="ml-2">{{ request('order') == 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </a>
                            </th>
                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                <a href="{{ route('products.index', ['sort_by' => 'tax_id', 'order' => request('order') == 'asc' ? 'desc' : 'asc'] + request()->except('page')) }}">
                                    Impuesto
                                    @if (request('sort_by') == 'tax_id')
                                        <span class="ml-2">{{ request('order') == 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </a>
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                <a href="{{ route('products.index', ['sort_by' => 'active', 'order' => request('order') == 'asc' ? 'desc' : 'asc'] + request()->except('page')) }}">
                                    Estado
                                    @if (request('sort_by') == 'active')
                                        <span class="ml-2">{{ request('order') == 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </a>
                            </th>
            
                           
                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 ">

                        <tr class="hover:bg-gray-100">
                            @foreach ($products as $product)
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                <a class="flex flex-col text-gray-900  hover:text-blue-500" href="{{ route('products.edit', $product) }}">
                                    <span class="text-base font-semibold ">
                                        {{ $product->name }}
                                    </span>
                                    <span class="text-sm font-normal text-gray-500 ">
                                        {{ $product->slug }}
                                    </span>
                                </a>
                            </td>
                            <td class="p-4 text-xs  text-gray-900 whitespace-nowra">
                                {{ $product->sku }}
                            </td>
                            <td class="p-4 text-base font-medium text-gray-900 whitespace-nowra">
                                
                                @if ($product->discount)
                                    <div class="flex flex-col">
                                        <span>
                                            ${{ number_format($product->price * (1 - $product->discount/100),2) }}
                                            <small class="text-gray-500">({{ $product->discount }}%)</small>
                                        </span>
                                        <small class="line-through">${{ number_format($product->price,2) }}</small>
                                    </div>
                                    
                                @else
                                ${{ number_format($product->price,2) }}
                                @endif
                            </td>

                            <td class="p-4 text-base  text-gray-900 whitespace-nowra">
                               {{$product->tax->name}} <small>({{$product->tax->tax}}%)</small>
                            </td>
                            <td class="p-4 text-base  text-gray-900 whitespace-nowra">
                                <div class="flex items-center">
                                    <div @class([
                                        'inline-block w-4 h-4 mr-2 rounded-full', 
                                        'bg-green-700' => $product->active,
                                        'bg-red-700' => !$product->active
                                        ])></div>
                                    {{ $product->active ? 'Activo' : 'Inactivo' }}
                                </div>
                            </td>
                           

                            <td class="p-4 space-x-2 whitespace-nowrap text-end">

                              
                                <a href="{{ route('products.edit', $product) }}"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 border border-blue-700  space-x-2">
                                    <x-heroicon-o-pencil-square class="h-4 w-4"/>
                                    <span>Editar</span>
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


{{ $products->appends(request()->except('page'))->links() }}











@endsection
