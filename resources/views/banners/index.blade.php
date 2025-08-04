@extends('layouts.admin')


@section('content')


<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl ">Banners</h1>
        </div>
        <div class="items-center flex  space-x-5">
           
            <a href="{{ route('banners.create') }}"
                class="text-white bg-blue-700 hover:bg-primary-800 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-2.5 ">
                Nuevo banner
            </a>
            {{-- <form class="" id='form'>
                {{Aire::select([1=>'Principal', 2=>'Lateral'], 'type_id')->value(request()->get('type_id', 1))->groupClass('mb-0')}}
            </form> --}}
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
                           
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Banner
                            </th>
                              <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Posición
                            </th>
                             <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Url
                            </th>
                           
                            
                          
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase "></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 ">
                        @foreach ($banners as $banner)
                        <tr class="hover:bg-gray-100">
                            
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                <a class="" href="{{ route('banners.edit', $banner) }}">
                                    <img src="{{asset('storage/'.$banner->path)}}" class="h-20">
                                </a>
                            </td>

                             <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                @if($banner->type_id == 1)
                                    Principal
                                @elseif($banner->type_id == 2)
                                    Lateral
                                @elseif($banner->type_id == 3)
                                    Intermedio
                                @else
                                    Desconocido
                                @endif
                            </td>

                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                {{ $banner->url }}
                            </td>
                           

                            <td class="p-4 space-x-2 whitespace-nowrap text-end">
                                <a href="{{ route('banners.edit', $banner) }}"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 ">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z">
                                        </path>
                                        <path fill-rule="evenodd"
                                            d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    Editar
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




@endsection




@section('scripts')
    
<script src="{{ asset('js/jquery.js') }}" ></script>
<script>

    $(document).ready(function(){
        $('select').on('change', function(){
            $('#form').submit();
        })
    })

</script>


@endsection