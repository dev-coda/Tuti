@extends('layouts.page')


@section('head')
    @include('elements.seo', [
        'title'=>$label->name, 
        'description'=> $label->description
        ])
@endsection

@section('content')
    

<div class="w-full">
    <h1 class="text-3xl mb-5">{{ $label->name }}</h1>
</div>
<div class="grid grid-cols-4 gap-4">
    @foreach ($products as $product)

        <div class="max-w-sm bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700">
            <a href="#">
                <img class="rounded-t-lg" src="/docs/images/blog/image-1.jpg" alt="" />
            </a>
            <div class="p-5">
                
                <a href="{{route('product', $product->slug)}}" class="text-gray-900  hover:text-blue-500">
                    <h5 class="mb-2 text-2xl font-bold tracking-tight">{{ $product->name }}</h5>
                </a>
                {{-- <p class="mb-5 font-normal text-gray-700 dark:text-gray-400">
                    {!! $product->short_description !!}
                </p> --}}


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







@endsection