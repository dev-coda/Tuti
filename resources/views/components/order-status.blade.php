@if($status == 0)
    <span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4 bg-yellow-100 text-yellow-800'>
        Pendiente
    </span>
@endif

@if($status == 1)
    <span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4 bg-green-100 text-green-800'>
        Procesada
    </span>
@endif
   


@if($status == 2)
    <span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4 bg-red-100 text-red-800'>
        Error 
    </span>
@endif


@if($status == 3)
    <span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4 bg-red-100 text-red-800'>
        Error webservice
    </span>
@endif

@if($status == 4)
    <span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4 bg-blue-100 text-blue-800'>
        Enviado
    </span>
@endif

@if($status == 5)
    <span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4 bg-green-100 text-green-800'>
        Entregado
    </span>
@endif

@if($status == 6)
    <span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4 bg-gray-100 text-gray-800'>
        Cancelado
    </span>
@endif

@if($status == 7)
    <span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4 bg-purple-100 text-purple-800'>
        En espera
    </span>
@endif