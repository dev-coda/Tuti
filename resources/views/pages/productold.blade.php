@extends('layouts.page')

@section('head')
    @include('elements.seo', [
        'title'=>$product->name, 
        'description'=> $product->sort_description
        ])
@endsection

@section('content')
    

<h1 class="text-4xl font-bold w-full flex justify-between mb-5">
   <span> {{ $product->name }}</span>

   {{-- <small id='productPrice'>
        @if($product->items->count())
        {{ price($product->items->first()->pivot->price, $product->discount) }}
        @else
        {{ price($product->price, $product->discount) }}
        @endif
    </small> --}}

   <x-price :product='$product'  />


</h1>
@auth
<form action="{{ route('cart.add_guest') }}" method="POST" class='mt-5' class="w-full">
@else
<form action="{{ route('cart.add_guest') }}" method="POST" class='mt-5' class="w-full">
@endauth

    @csrf
    <input type="hidden" name='product_id' value="{{ $product->id }}">

    <p class="mb-5">
        {!! $product->short_description !!}
    </p>


    @if ($product->variation && $product->items->count())
        {{ $product->variation->name }}
        <select name="variation_id" id="selectPrice">
            @foreach ($product->items->where('pivot.enabled', 1) as $item)
                @php $fpVar = $product->getFinalPriceForUser(false, null, $item->id); @endphp
                <option
                    value="{{ $item->pivot->variation_item_id }}"
                    data-final-gross="{{ $fpVar['price'] }}"
                    data-old-gross="{{ $fpVar['old'] }}"
                    data-has-discount="{{ $fpVar['has_discount'] ? '1' : '0' }}"
                >{{ $item->name }}</option>
            @endforeach
        </select>
    @endif

    <div class="mt-5">
        @foreach ($product->labels as $label)
            
            <a href="{{ route('label', $label->slug) }}" class="inline-block bg-blue-500 hover:bg-blue-700 rounded-full px-3 py-1 text-sm font-semibold text-white mr-2">{{ $label->name }}</a>

        @endforeach
    </div>

    <div class="mt-5">
        Marca: <a href="{{ route('brand', $product->brand->slug) }}" class="text-blue-500">{{ $product->brand->name }}</a>
    </div>




    <input type="number" name='quantity' value='1'>
    <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 mr-2 mb-2 ">Agregar</button>

      
   
</form>






@if($product->related->count() )
<hr class="borde-b border-blue-500 my-10 w-full">
<div class="w-full">
    <h2 class="font-bold text-xl mb-5">Productos relacionados</h2>
</div>
<div class="grid grid-cols-5 gap-4">
    @foreach ($product->related as $product)

        <div class="max-w-sm bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700">
            <a href="#">
                {{-- <img class="rounded-t-lg" src="/docs/images/blog/image-1.jpg" alt="" /> --}}
            </a>
            <div class="p-5">
                
                <a href="{{route('product', $product->slug)}}" class="text-gray-900  hover:text-blue-500">
                    <h5 class="mb-2 text-2xl font-bold tracking-tight">{{ $product->name }}</h5>
                </a>
              


                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-orange-500 font-bold text-xl">${{ currency($product->final_price['price']) }}</span>
                    @if($product->final_price['has_discount'])
                    <span class="line-through text-gray-400 text-sm font-semibold">${{ currency($product->final_price['old']) }}</span>
                    @endif
                </div>
            
            </div>
        </div>
        
    @endforeach
</div>

@endif


@endsection


@section('scripts')
     
    <script>
        $(function(){
            function currency(n) {
                return new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Math.round(Number(n)));
            }
            $('#selectPrice').on('change', function(){
                var $o = $(this).find(':selected');
                var gross = parseFloat($o.data('final-gross'), 10);
                var oldGross = parseFloat($o.data('old-gross'), 10);
                var hasDisc = String($o.data('has-discount')) === '1';
                $('#price').html('$' + currency(gross));
                if (hasDisc) {
                    $('#oldprice').removeClass('hidden').html('$' + currency(oldGross));
                } else {
                    $('#oldprice').addClass('hidden').html('');
                }
            })
        })

    </script>


@endsection