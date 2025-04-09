@extends('layouts.page')


@section('head')
    @include('elements.seo', [
        'title'=>'Carrito de compras', 
        'description'=> 'Carrito de compras'
        ])
@endsection



@section('content')
    

@if($set_user)
<div class="grid grid-cols-1 w-full gap-y-5 gap-x-5 xl:px-72"  x-data="{'isModalOpen': false}" x-on:keydown.escape="isModalOpen=false">

    
    <div class="border rounded p-5 mt-5">
        <div>
            {{ Aire::open()->route('seller.setclient')}}
            
                <div class='grid grid-cols-1 gap-5'>
                    {{ Aire::input('document', 'Documento')->helpText('Nit sin dígito de verificación')->groupClass('mb-0') }} 
                </div>

                <div class="flex items-center  mt-4">
                    <x-primary-button >
                        Ingresar
                    </x-primary-button>
                </div>
            {{ Aire::close() }}

        
        
                

        </div>
    </div>
    


</div>
@else

    <div class="grid grid-cols-1 w-full gap-y-5 gap-x-5 xl:px-60"  x-data="{'isModalOpen': false}" x-on:keydown.escape="isModalOpen=false">


        @if($client)
            {{ Aire::open()->route('seller.removeclient')}}
                <div class="border rounded p-5">
                    <div class="flex justify-between">
                        <strong>{{$client->name}}</strong>

                        <button class="text-slate-500 hover:text-red-500">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>
            {{ Aire::close() }}
        @endif

        <div class="">

            @if($alertVendors)
                <div class="space-y-2 mb-5">
                    @foreach ($alertVendors as $alert)
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                            El vendor <strong>{{$alert->name}}</strong> requiere una compra mínima de <strong>${{currency($alert->minimum_purchase)}}</strong> para realizar el pedido compra <strong>${{currency($alert->minimum_purchase - $alert->current)}}</strong> completar esta compra.
                        </div>
                    @endforeach  
                </div> 
            @endif   
    


            <div class="border rounded p-5">
                <div>
                    {{ Aire::open()->route('cart.update')}}
      
                        <h3 class="mb-4">Productos</h3>
                        <div class="xl:text-base text-sm space-y-5">
                            @foreach ($products as $key => $product)
                                <div class="grid grid-cols-12 items-top gap-x-2">
                                    <a  href="{{route('product', $product->slug)}}" class="col-span-2 xl:flex hidden">
                                        <img src="{{asset('storage/'.$product->image)}}" alt="">  
                                    </a>
                                    <div class="col-span-4 xl:px-3 px-0 flex flex-col">
                                        <a href='{{route('product', $product->slug)}}'>{{$product->name}} </a>
                                    <div>
                                            <small class="text-slate-700">${{currency($product->final_price['old'])}}</small>
                                            @if($product->variation)
                                                <small class="text-slate-700">{{$product->variation->name}}  {{$product->item->name}}</small>
                                            @endif
                                    </div>
                                    </div>
                                    <div class="xl:col-span-3 col-span-5 px-3 flex flex-col">
                                        {{-- <input type="text" value="{{$product->quantity}}" class="shadow-sm bg-gray-50 border border-gray-300 text-gray-900 text-sm  focus:ring-primary-500 focus:border-primary-500 block w-full p-2 text-center rounded-sm"> --}}
                                        <div class=" py-1 flex items-center border border-qgray-border">
                                            <div class="flex justify-between items-center w-full">
                                                <button  data-step="{{$product->step}}" type="button" class="increment text-blue1 text-3xl text-qgray w-10">-</button>
                                                <input type="numeric" readonly  name='items[]' class="quantity w-10 text-center bg-transparent border-0 text-sm focus:ring-0 focus:outline-none"   value="{{$product->quantity}}">
                                                <button data-step="{{$product->step}}"   type="button" class="decrement text-blue1 text-3xl  w-10">+</button>
                                            </div>
                                        </div>
                                        
                                    </div>
                                    <div class="col-span-2 text-right">
                                        <strong class="">${{currency($product->final_price['price'] * $product->quantity)}}</strong>
                                        @if($product->final_price['has_discount'])
                                            <small class="line-through">${{currency($product->final_price['old'] * $product->quantity)}}</small>
                                        @endif
                                    </div>    
                                    <div class="items-start justify-center flex"> 
                                        <a href={{route('cart.remove', $key)}} class='hover:text-red-500 text-slate-400 mt-1'>
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>                              
                                        </a>
                                    </div>                   
                                </div>
                            @endforeach
                        </div>
                        <div class="flex justify-end my-5">
                            <button type="submit" class="bg-orange-600  text-white rounded py-1.5 px-3 text-sm block text-center hover:bg-orange-900">Actualizar</button> 
                        </div>

                    {{ Aire::close() }}

                    <div class="text-sm">
                        <hr class="my-4">

                        @php
                            $subtotal = $products->sum(function($product){
                                return $product->final_price['old'] * $product->quantity;
                            });

                            $discount = $products->sum(function($product){
                                return $product->final_price['old'] * $product->discount/100 * $product->quantity;
                            });
                        @endphp

            
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <strong>
                            ${{currency($subtotal)}}
                            </strong>
                        </div>
                        @if($discount)
                            <div class="flex justify-between">
                                <span>Descuento</span>
                                <strong>
                                    -${{currency($discount)}}
                                </strong>
                            </div>
                        @endif
                        <hr class="my-4">
                        <div class="flex justify-between">
                            <strong>Total</strong>
                            <strong>${{currency($subtotal-$discount)}}</strong>
                        </div>

                    
                        @if($alertVendors)
                            <button disabled class="bg-orange-600 opacity-50 w-full text-white rounded py-3 px-5 mt-5 block text-center">Realizar Pedido</button>
                        @else 
                            {{ Aire::open()->route('cart.process')}}

                                <div class="pt-5">
                                    {{ Aire::select($zones, 'zone_id', 'Dirección')->id('states')}}

                                    {{Aire::textarea('observations', 'Observaciones')->placeholder('Información adicional')->rows(3)}}
                                </div>



                                <button type="submit" class="bg-orange-600 w-full text-white rounded py-3 px-5 mt-5 block text-center hover:bg-orange-900">Realizar Pedido</button> 
                            {{ Aire::close() }}
                            
                        @endif


                    </div>
                
                        

                </div>
            </div>
        </div>


    </div>
@endif






@endsection


@section('scripts')

   <script>
      $(function(){

      

         const step =1;

         $('.increment').on('click', function(){
            const step = $(this).data('step')
            const form = $(this).parent()
            const quantityInput = form.find('.quantity')
         
            let quantity = parseInt(quantityInput.val())
            quantity = quantity - step
            if(quantity < step){
               quantity = step
            }
            quantityInput.val(quantity)
         })

         $('.decrement').on('click', function(){
            const step = $(this).data('step')
            const form = $(this).parent()
            const quantityInput = form.find('.quantity')
            
            let quantity = parseInt(quantityInput.val())
            console.log(quantity)
            quantity = quantity + step
            quantityInput.val(quantity)
         })
      })
   </script>


@endsection
