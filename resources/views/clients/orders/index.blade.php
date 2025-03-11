@extends('layouts.page')


@section('head')

    @include('elements.seo', ['title'=>'Ordenes' ])

@endsection


@section('content')
    
<section class="w-full xl:px-5 px-0">
   
<h2 class="text-2xl font-bold mb-5">Historial de ordenes</h2>
<div class="relative overflow-x-auto">
    <table class="w-full text-sm text-left rtl:text-right text-gray-500 ">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50  ">
            <tr>
                <th scope="col" class="px-6 py-3">
                    Fecha
                </th>
                <th scope="col" class="px-6 py-3 text-center">
                    Productos
                </th>
                @role('seller')
                    <th class="px-6 py-4">
                        Cliente
                    </th>
                @endrole
                <th scope="col" class="px-6 py-3">
                    Total
                </th>
                <th scope="col" class="px-6 py-3">
                    
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach ($orders as $order)
                <tr class="bg-white border-b  ">
                    <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap ">
                        {{$order->created_at->subHour(5)->format('Y-m-d H:i')}}
                    </th>
                    <td class="px-6 py-4 text-center">
                        {{$order->products_count}}
                    </td>
                    @role('seller')
                        <td class="px-6 py-4">
                            {{$order->user->name}}
                        </td>
                    @endrole
                    <td class="px-6 py-4">
                        ${{number_format(($order->total+$order->discount) - $order->discount)}}
                    </td>
                    <td class="px-6 py-4 text-end">
                        <a href="{{route('clients.orders.show', $order)}}" class="rounded py-1 px-2 text-white bg-secondary ">
                            Ver orden
                        </a>
                    </td>
                </tr>
            @endforeach
           
          
        </tbody>
    </table>
<div class="col-span-10">{{$orders->links()}} </div>
</div>


</section>






@endsection


@section('scripts')
 

@endsection
