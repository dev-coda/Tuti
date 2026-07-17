@extends('layouts.admin')

@section('content')

    <div class="p-4 bg-white border-b border-gray-200">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl mb-4">Editar tamaño de empaque: {{ $packageType->code }}</h1>

        <form action="{{ route('package-types.update', $packageType) }}" method="POST">
            @csrf
            @method('PUT')
            @include('package-types._form')
        </form>
    </div>

@endsection
