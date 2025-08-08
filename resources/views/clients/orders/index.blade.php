@extends('layouts.page')


@section('head')

    @include('elements.seo', ['title'=>'Ordenes' ])

@endsection


@section('content')
    
<section class="w-full xl:px-5 px-0">
   
    <h2 class="text-2xl font-bold mb-5">Historial de ordenes</h2>

    <form method="GET" action="{{ route('clients.orders.index') }}" class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar cliente</label>
                <input type="text" name="q" id="orders-filter-q" value="{{ request('q') }}" class="w-full border-gray-300 rounded-md" placeholder="Nombre del cliente...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}" class="w-full border-gray-300 rounded-md">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}" class="w-full border-gray-300 rounded-md">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Filtrar</label>
                <select name="status_id" class="w-full border-gray-300 rounded-md">
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected((string)request('status_id','') === (string)$value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    <div class="relative overflow-x-auto">
        <table class="w-full text-sm text-left rtl:text-right text-gray-500 ">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50  ">
                <tr>
                    <th scope="col" class="px-6 py-3">Fecha</th>
                    <th scope="col" class="px-6 py-3 text-center">Productos</th>
                    <th scope="col" class="px-6 py-3 text-center">Unidades</th>
                    @role('seller')
                        <th class="px-6 py-4">Cliente</th>
                    @endrole
                    <th scope="col" class="px-6 py-3">Estado</th>
                    <th scope="col" class="px-6 py-3">Total</th>
                    <th scope="col" class="px-6 py-3"></th>
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
                        <td class="px-6 py-4 text-center">
                            {{$order->products_sum_quantity ?? 0}}
                        </td>
                        @role('seller')
                            <td class="px-6 py-4">
                                {{$order->user->name}}
                            </td>
                        @endrole
                        <td class="px-6 py-4">
                            <x-order-status :status="$order->status_id" />
                        </td>
                        <td class="px-6 py-4">
                            ${{number_format(($order->total+$order->discount) - $order->discount)}}
                        </td>
                        <td class="px-6 py-4 text-end space-x-2">
                            <a href="{{route('clients.orders.show', $order)}}" class="rounded py-1 px-2 text-white bg-secondary ">Ver orden</a>
                            <form action="{{ route('clients.orders.reorder', $order) }}" method="POST" class="inline">
                                @csrf
                                <button class="rounded py-1 px-2 text-white bg-orange-600 hover:bg-orange-700">Volver a pedir</button>
                            </form>
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
<script>
    (function(){
        const input = document.getElementById('orders-filter-q');
        if(!input) return;
        let t;
        input.addEventListener('input', function(){
            clearTimeout(t);
            t = setTimeout(() => {
                const params = new URLSearchParams(window.location.search);
                params.set('q', input.value || '');
                window.location = `${window.location.pathname}?${params.toString()}`;
            }, 350);
        });

        // submit form on date/status change
        document.querySelectorAll("input[name='from_date'], input[name='to_date'], select[name='status_id']").forEach(el => {
            el.addEventListener('change', function(){
                this.form.submit();
            });
        });
    })();
</script>
@endsection
