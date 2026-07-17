@extends('layouts.admin')

@section('content')

    <div class="p-4 bg-white border-b border-gray-200">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl mb-4">Nuevo tamaño de empaque</h1>

        <form action="{{ route('package-types.store') }}" method="POST">
            @csrf
            @include('package-types._form')
        </form>
    </div>

@endsection
