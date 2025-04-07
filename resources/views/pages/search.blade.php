@extends('layouts.page')


@section('head')

    @include('elements.seo', [
        'title'=>'Resultados de busqueda', 
        'description'=>'Resultados de busqueda'
        ])

@endsection


@section('content')
    
{{-- Add sorting and filtering --}}
    <div class="col-span-10 pt-3">
        <div class="flex md:justify-between md:items-center flex-col md:flex-row pr-3">
            <div class="flex items-start md:items-center space-x-2 flex-col md:flex-row">
                <span class="w-full">Ordenar por:</span>
                <select name="sort" id="sort" class="border border-gray-200 rounded-md p-2 w-full" onchange="window.location.href=this.value">
                    <option {{ $params['order'] === '1' ? 'selected' : '' }} value="{{ route('search', 1 . '/' . $params['category_id'] . '/' . $params['brand_id'] . '?q='. $params['q']) }}">Más reciente</option>
                    <option {{ $params['order'] === '2' ? 'selected' : '' }}  value="{{ route('search', 2 . '/' . $params['category_id'] . '/' . $params['brand_id'] . '?q='. $params['q']  ) }}">Precio: Menor a Mayor</option>
                    <option {{ $params['order'] === '3' ? 'selected' : '' }}  value="{{ route('search', 3 . '/' . $params['category_id'] . '/' . $params['brand_id'] . '?q='. $params['q']  ) }}">Precio: Mayor a Menor</option>
                    <option {{ $params['order'] === '4' ? 'selected' : '' }}  value="{{ route('search', 4 . '/' . $params['category_id'] . '/' . $params['brand_id'] . '?q='. $params['q']  ) }}">Nombre A-Z</option>
                    <option {{ $params['order'] === '5' ? 'selected' : '' }}  value="{{ route('search', 5 . '/' . $params['category_id'] . '/' . $params['brand_id'] . '?q='. $params['q'] ) }}">Nombre Z-A</option>               
                </select>
            </div>
            <div class="flex items-center space-x-2 flex-wrap md:flex-nowrap">
                <span class="w-full">Filtrar por:</span> 
                <select name="filter" id="filter" class="border border-gray-200 rounded-md p-2 w-full" onchange="window.location.href=this.value">
                <option value="#" disabled><b>Marca</b></option>
                @foreach ($brands as $brand)
                    <option {{ $params['brand_id'] == $brand->id ? 'selected' : '' }} value="{{ route('search', $params['order'] . '/' . $params['category_id'] . '/' . $brand->id . '?q='. $params['q'] ) }}">&nbsp;&nbsp;&nbsp;{{ $brand->name }}</option>
                    @endforeach
                <option value="#" disabled><b>Categoria</b></option>   
                @foreach ($categories as $categoryItem)
                    <option {{ $params['category_id'] == $categoryItem->id ? 'selected' : '' }} value="{{ route('search', $params['order'] . '/' . $categoryItem->id . '/' . $params['brand_id'] . '?q='. $params['q']  ) }}">&nbsp;&nbsp;&nbsp;{{ $categoryItem->name }}</option>
                @endforeach   
                </select>

            </div>
        </div>   
    </div


<section class="w-full grid grid-cols-1 gap-x-10 xl:gap-y-0 gap-y-10">
    @if (count($products) == 0)
        <h1 class="text-2xl font-bold mt-5">No se encontraron resultados</h1>
    @else
    <h1 class="text-2xl font-bold mt-5">Resultados de búsqueda: {{request()->q}}</h1>
    <div class="my-10">
        <div class="grid grid-cols-2 xl:grid-cols-6 gap-5 ">
            @foreach ($products as $product)
                <x-product :product="$product"/>
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
                    <button id="doubleDropdownButton" data-dropdown-toggle="doubleDropdown" data-dropdown-placement="right-start" type="button" class="flex items-center justify-between w-full px-4 py-2 hover:bg-gray-100  ">Dropdown<svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg></button>
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
            loop:true,
            margin:10,
            nav:false,
            items: 1,

        })
    </script>

@endsection