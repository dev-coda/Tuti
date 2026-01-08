@extends('layouts.admin')


@section('content')

<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">{{$user->name}}</h1>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Información Personal</h3>
            {{ Aire::open()->route('users.update', $user)->bind($user)}}
                <div class="grid grid-cols-2 gap-5">

                    {{ Aire::input('name', 'Nombre Completo')->groupClass('mb-0') }}
                    {{ Aire::email('email', 'Correo Electrónico')->groupClass('mb-0') }}

                    {{ Aire::input('document', 'Número de Documento')->groupClass('mb-0') }}
                    {{ Aire::input('phone', 'Teléfono Fijo')->groupClass('mb-0') }}

                    {{ Aire::input('mobile_phone', 'Teléfono Móvil')->groupClass('mb-0') }}
                    {{ Aire::input('whatsapp', 'Número de WhatsApp')->groupClass('mb-0') }}

                </div>

                <h4 class="mt-6 mb-4 text-lg font-semibold text-gray-700">Información Empresarial</h4>
                <div class="grid grid-cols-2 gap-5">

                    {{ Aire::input('business_name', 'Razón Social')->groupClass('mb-0') }}
                    {{ Aire::input('account_num', 'Número de Cuenta')->groupClass('mb-0') }}

                    {{ Aire::input('customer_type', 'Tipo de Cliente')->groupClass('mb-0') }}
                    {{ Aire::input('price_group', 'Grupo de Precios')->groupClass('mb-0') }}

                    {{ Aire::input('tax_group', 'Grupo de Impuestos')->groupClass('mb-0') }}
                    {{ Aire::input('line_discount', 'Descuento de Línea')->groupClass('mb-0') }}

                </div>

                <h4 class="mt-6 mb-4 text-lg font-semibold text-gray-700">Ubicación y Estado</h4>
                <div class="grid grid-cols-2 gap-5">

                    {{ Aire::input('city_code', 'Código de Ciudad')->groupClass('mb-0') }}
                    {{ Aire::input('county_id', 'Código de Departamento')->groupClass('mb-0') }}

                    {{ Aire::input('balance', 'Saldo de Cuenta')->groupClass('mb-0') }}
                    {{ Aire::input('quota_value', 'Valor de Cupo de Crédito')->groupClass('mb-0') }}

                    {{ Aire::input('customer_status', 'Estado del Cliente')->groupClass('mb-0') }}
                    {{ Aire::input('order_sequence', 'Secuencia de Orden')->groupClass('mb-0') }}

                    <div class="col-span-2">
                        <label class="flex items-center">
                            {{ Aire::checkbox('is_locked', 'Cuenta Bloqueada')->groupClass('mb-0') }}
                        </label>
                    </div>

                </div>

                <div class="col-span-6 justify-between  items-center mt-5 space-x-2 flex">

                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Actualizar')->variant()->submit() }}
                        <a href="{{ route('users.index') }}">Cancelar</a>
                    </p>               
                </div>
            {{ Aire::close() }}
        </div>
    </div>

    <div>
       
     
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Contraseña</h3>
          
            {{ Aire::open()->route('users.password', $user)}}
                {{ Aire::password('password', 'Contraseña')->groupClass('mb-5') }}
                {{ Aire::password('password_confirmation', 'Confirme Contraseña')->groupClass('mb-5') }}
                {{ Aire::submit('Actualizar')->variant()->submit() }}
            {{ Aire::close() }}
        
        </div>


    </div>

</div>

<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Direcciones</h1>
    </div>

        <div class="col-span-3">
            

            <div class="relative overflow-x-auto">
                <table class="w-full text-sm text-left rtl:text-right text-gray-500 ">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50  ">
                        <tr>
                            <th scope="col" class="px-6 py-3">
                               Zona
                            </th>
                            <th scope="col" class="px-6 py-3">
                                Ruta
                            </th>
                            <th scope="col" class="px-6 py-3">
                                Día de Recorrido
                            </th>
                            <th scope="col" class="px-6 py-3">
                                Dirección
                            </th>
                            <th scope="col" class="px-6 py-3">
                                Código Rutero
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($user->zones as $zone)

                            <tr class="bg-white border-b  ">
                                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap ">
                                    {{$zone->zone}}
                                </th>
                                <td class="px-6 py-4">
                                    {{$zone->route}}
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $days = ['1' => 'Lunes', '2' => 'Martes', '3' => 'Miércoles', '4' => 'Jueves', '5' => 'Viernes', '6' => 'Sábado', '7' => 'Domingo'];
                                        echo $days[$zone->day] ?? $zone->day;
                                    @endphp
                                </td>
                                <td class="px-6 py-4">
                                    {{$zone->address}}
                                </td>
                                <td class="px-6 py-4">
                                    {{$zone->code}}
                                </td>
                            </tr>
                        @endforeach
                      
                    </tbody>
                </table>
            </div>

        </div>

  

</div>

<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Historial de Pedidos</h1>
    </div>

    <div class="col-span-3">
        <div class="relative overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3">Fecha</th>
                        <th scope="col" class="px-6 py-3">Dirección</th>
                        <th scope="col" class="px-6 py-3">Número de Pedido</th>
                        <th scope="col" class="px-6 py-3">Valor</th>
                        <th scope="col" class="px-6 py-3">Unidades</th>
                        <th scope="col" class="px-6 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr class="bg-white border-b">
                            <td class="px-6 py-4">{{ $order['date'] }}</td>
                            <td class="px-6 py-4">{{ $order['address'] ?? 'No disponible' }}</td>
                            <td class="px-6 py-4">
                                <a href="{{ route('orders.edit', $order['order_number']) }}" class="text-blue-500 hover:underline">
                                    {{ $order['order_number'] }}
                                </a>
                            </td>
                            <td class="px-6 py-4">${{ number_format($order['value'], 2, ',', '.') }}</td>
                            <td class="px-6 py-4">{{ $order['units'] }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded 
                                    {{ $order['status'] == 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                                    {{ $order['status'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-4">
                {{ $orders->links() }}
            </div>
        </div>
    </div>
</div>



@endsection



@section('scripts')
    
<script>
    $(function(){
        $('#states').change(function(){
            const state = $(this).val()
            
            const url = `{{ route('cities.index') }}?state=${state}`

            axios.get(url).then(function(response){
                console.log(response.data)
                const cities = response.data

                $('#cities').empty()

                cities.forEach(city => {
                    $('#cities').append(`<option value="${city.id}">${city.name}</option>`)
                });

            })

        })
    })
</script>

@endsection