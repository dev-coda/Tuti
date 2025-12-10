@extends('layouts.page')


@section('head')

@include('elements.seo', [
'title'=>'Resultados de busqueda',
'description'=>'Resultados de busqueda'
])

@endsection


@section('content')

<div class="grid grid-cols-12 w-full gap-5 max-w-[90vw] mx-auto">
    <div class="col-span-12">
        <div id="filters"
        data-brands="{{ json_encode($brands->map(function($brand) use ($params) {
            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'url' => route('search', $params['order'] . '/' . $params['category_id'] . '/' . $brand->id . '?q=' . $params['q'])
            ];
        })) }}"
        data-categories="{{ json_encode($categories->map(function($category) use ($params) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'url' => route('search', $params['order'] . '/' . $category->id . '/' . $params['brand_id'] . '?q=' . $params['q'])
            ];
        })) }}"></div>
    </div>
</div>
{{-- Add sorting and filtering --}}



<section class="w-full grid grid-cols-1 gap-x-10 xl:gap-y-0 gap-y-2 md:gap-y-10">
    @php
        $vacationModeEnabled = \App\Models\Setting::getByKey('vacation_mode_enabled');
        $isVacationMode = ($vacationModeEnabled === '1' || $vacationModeEnabled === 1 || $vacationModeEnabled === true);
        $vacationDate = \App\Models\Setting::getByKey('vacation_mode_date');
        $formattedVacationDate = $vacationDate ? \Carbon\Carbon::parse($vacationDate)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') : 'pronto';
    @endphp

    @if($isVacationMode)
    <div class="mb-6">
        <div class="bg-orange-50 border-l-4 border-orange-400 p-4 rounded-lg shadow-sm">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-orange-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm text-orange-700">
                        <span class="font-semibold">Tuti está de vacaciones.</span> Te esperamos nuevamente <span class="font-medium">{{ $formattedVacationDate }}</span>. ¡Gracias!
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if (count($products) == 0)
    <h1 class="text-2xl font-bold mt-5">No se encontraron resultados</h1>
    @else
    <h1 class="text-2xl font-bold mt-2 md:mt-5">Resultados de búsqueda: {{request()->q}}</h1>
    <div class="my-4 md:my-10">
         <div class="grid grid-cols-1 xl:grid-cols-4 gap-x-5 gap-y-2 md:gap-y-5  ">
            @foreach ($products as $product)
            <x-product :product="$product" :bodega-code="$bodegaCode ?? null" />
            @endforeach
        </div>
    </div>

    {{ $products->withQueryString()->links() }}
    @endif

</section>





{{-- <div class="container mx-auto" >
    <div class="w-full md:block md:w-auto py-10" id="navbar-multi-level">
        <ul class="flex flex-col font-medium p-4 md:p-0 mt-4 border border-gray-100 rounded-lg bg-gray-50 md:flex-row md:space-x-8 md:mt-0 md:border-0 md:bg-white  md: ">
        <li>
            <a href="#" class="block py-2 pl-3 pr-4 text-white bg-blue-700 rounded md:bg-transparent md:text-blue-700 md:p-0 md:  md:" aria-current="page">Home</a>
        </li>
        <li>
            <button id="dropdownNavbarLink" data-dropdown-toggle="dropdownNavbar" class="flex items-center justify-between w-full py-2 pl-3 pr-4  text-gray-700 hover:bg-gray-50 md:hover:bg-transparent  md:hover:text-blue-700 md:p-0 md:w-auto ">Productos <svg class="w-5 h-5 ml-1" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg></button>
            <!-- Dropdown menu -->
            <div id="dropdownNavbar" class="z-10 font-normal bg-white divide-y divide-gray-100 rounded-lg shadow w-44   hidden">
                <ul class="py-2 text-sm text-gray-700 " aria-labelledby="dropdownLargeButton">
                    @foreach ($categories as $category)
                        <li>
                            <a href="{{ route('category', $category->slug) }}" class="block px-4 py-2 hover:bg-gray-100">{{ $category->name }}</a>
</li>

@endforeach
<li>
    <a href="#" class="block px-4 py-2 hover:bg-gray-100  ">Dashboard</a>
</li>
<li>
    <a href="#" class="block px-4 py-2 hover:bg-gray-100  ">Dashboard</a>
</li>
<li aria-labelledby="dropdownNavbarLink">
    <button id="doubleDropdownButton" data-dropdown-toggle="doubleDropdown" data-dropdown-placement="right-start" type="button" class="flex items-center justify-between w-full px-4 py-2 hover:bg-gray-100  ">Dropdown<svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
        </svg></button>
    <div id="doubleDropdown" class="z-10 hidden bg-white divide-y divide-gray-100 rounded-lg shadow w-44 " style="position: absolute; inset: 0px auto auto 0px; margin: 0px; transform: translate3d(10px, 0px, 0px);" data-popper-placement="right-start" data-popper-reference-hidden="" data-popper-escaped="">
        <ul class="py-2 text-sm text-gray-700 " aria-labelledby="doubleDropdownButton">
            <li>
                <a href="#" class="block px-4 py-2 hover:bg-gray-100   ">Overview</a>
            </li>
            <li>
                <a href="#" class="block px-4 py-2 hover:bg-gray-100   ">My downloads</a>
            </li>
            <li>
                <a href="#" class="block px-4 py-2 hover:bg-gray-100   ">Billing</a>
            </li>
            <li>
                <a href="#" class="block px-4 py-2 hover:bg-gray-100   ">Rewards</a>
            </li>
        </ul>
    </div>
</li>
<li>
    <a href="#" class="block px-4 py-2 hover:bg-gray-100  ">Earnings</a>
</li>
</ul>
<div class="py-1">
    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100   ">Sign out</a>
</div>
</div>
</li>
<li>
    <a href="#" class="block py-2 pl-3 pr-4 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:border-0 md:hover:text-blue-700 md:p-0  md:   md:">Services</a>
</li>
<li>
    <a href="#" class="block py-2 pl-3 pr-4 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:border-0 md:hover:text-blue-700 md:p-0  md:   md:">Pricing</a>
</li>
<li>
    <a href="#" class="block py-2 pl-3 pr-4 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:border-0 md:hover:text-blue-700 md:p-0  md:   md:">Contact</a>
</li>
</ul>
</div>
</div> --}}

<script>
    //   for (let index = 0; index < 100; index++) {

    //         const n = index + 500;
    //         window.open(`http://localhost:5173/?userid=${n}&username=Daniel${n}`, '_blank');

    //     }
</script>
@endsection


@section('scripts')
<script src="{{asset('assets/owl.carousel.min.js')}}"></script>

<script>
    $('.owl-carousel').owlCarousel({
        loop: true,
        margin: 10,
        nav: false,
        items: 1,

    })
</script>

@endsection