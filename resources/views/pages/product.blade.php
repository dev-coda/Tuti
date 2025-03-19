@extends('layouts.page')

@section('head')
@include('elements.seo', [
'title'=>$product->name,
'description'=> $product->sort_description
])
@endsection

@section('content')



<div class="grid grid-cols-12 w-full gap-y-5 gap-x-5">

    <div class="col-span-12">
        <ul class="flex  space-x-2 text-gray-500">
            <li><a href="#">Inicio</a></li>
            @if($product->category)
            <li>></li>
            <li><a href="{{route('category', $product->category->slug)}}">{{$product->category->name}}</a></li>
            @endif
            {{-- <li>></li> --}}
            {{-- <li><a href="{{route('category',$product->categories->first()->slug)}}">{{$product->categories->first()->name}}</a></li> --}}
        </ul>
    </div>

    <div class="xl:col-span-6 col-span-12">
        <div class="grid grid-cols-12 gap-5">
            <div class="xl:col-span-3 col-span-12 xl:order-1 order-2">
                <ul class="grid xl:grid-cols-1 grid-cols-4 gap-2">
                    @foreach ($product->images as $image)
                    <li class="border p-2">
                        <a href="">
                            <img src="{{asset('storage/'.$image->path)}}" alt="">
                        </a>
                    </li>
                    @endforeach

                </ul>
            </div>
            <div class="xl:col-span-9 col-span-12 xl:order-2 order-1">
                <div class="border p-2">
                    @if($product->images->first())
                    <img src="{{asset('storage/'.$product->images->first()->path)}}" alt="">
                    @endif
                </div>

            </div>
        </div>
    </div>
    <form action="{{ route('cart.add', $product->id) }}" method="POST" class="xl:col-span-6 col-span-12 space-y-4">
        @csrf
        <h1 class="text-2xl">{{$product->name}}</h1>

        <div class="flex justify-start items-center space-x-2">
            <strong class="text-xl">${{currency($product->final_price['price'])}}</strong>
            @if ($product->final_price['has_discount'])
            <span class="line-through text-gray-500 text-xl">${{currency($product->final_price['old'])}}</span>
            @endif
        </div>

        <p>
            {{$product->description}}
        </p>

        {{-- <ul class="list-disc pl-5">
            <li>Bombillo led A60 7W</li>
            <li>Multivoltaje</li>
            <li>10.000 horas de duración</li>
            <li>Luz Fría</li>
        </ul> --}}

        {{-- <div class="flex flex-col space-y-2">
            <strong>Presentación</strong>
            <ul class="flex  space-x-2 font-bold">
                <li class="border rounded px-2 py-1">12und</li>
                <li class="border rounded px-2 py-1">12und</li>
                <li class="border rounded px-2 py-1">12und</li>
            </ul>
        </div> --}}


        @if ($product->variation && $product->items->count())
        <span class="text-xl">{{ $product->variation->name }}:</span>
        <select name="variation_id" id="selectPrice">
            @foreach ($product->items->where('pivot.enabled', 1) as $item)
            <option data-price="{{ $item->pivot->price }}" value="{{ $item->pivot->variation_item_id }}">{{ $item->name }}</option>
            @endforeach
        </select>
        @endif

        <div class="bg-blue3 flex items-center justify-center">
            <button type="button" id='increment' class="text-blue1 text-5xl">-</button>
            <input type="numeric" id='quantity' name='quantity' class="w-20 text-center rounded border-0 mx-2 text-xl px-4 focus:ring-0 focus:outline-none" value="{{$product->step}}">
            <button type="button" id='decrement' class="text-blue1 text-5xl">+</button>
        </div>

        <div>

            <button class="bg-secondary w-full flex items-end justify-center text-xl space-x-2 py-2 rounded hover:bg-opacity-90 text-white">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                </svg>
                <span>
                    Añadir al Carrito
                </span>
            </button>

        </div>



    </form>

    {{-- @if($product->description)
    <div class="xl:col-span-6 col-span-12 py-5">
            <h3 class="font-bold text-xl mb-2">Detalles del producto</h3>
            <p>
                {{$product->description}}
    </p>
</div>
@endif --}}



@if($related->count())
<div class="col-span-12 py-5">
    <h3 class="font-bold text-xl mb-2">Productos Relacionados</h3>
    <div class="grid grid-cols-2 xl:grid-cols-6 gap-5 ">
        @foreach ($related as $p)
        <x-product :product="$p" />
        @endforeach
    </div>
</div>
@endif





</div>

@endsection




@section('scripts')

<script>
    $(function() {
        const step = parseInt('{{$product->step}}');

        $('#increment').on('click', function() {
            let quantity = parseInt($('#quantity').val())
            quantity = quantity - step
            if (quantity < step) {
                quantity = step
            }
            $('#quantity').val(quantity)
        })

        $('#decrement').on('click', function() {
            let quantity = parseInt($('#quantity').val())
            quantity = quantity + step
            $('#quantity').val(quantity)
        })

        //make sure the quantity is a multiple of the step
        $('#quantity').on('change', function() {
            let quantity = parseInt($(this).val())
            if (quantity % step != 0) {
                quantity = Math.floor(quantity / step) * step
                $(this).val(quantity)
            }
        })

        // const discount =  '{{$product->finalPrice['discount']}}'

        // $('#selectPrice').on('change', function(){

        //     let price = $(this).find(':selected').data('price')

        //     if(discount){
        //         $('#oldprice').html(currency(parseInt(price)))
        //         price = price - (price * (discount/100))
        //     }
        //     console.log(currency(price));
        //     $('#price').html(currency(price))
        // })




    })
</script>


@endsection