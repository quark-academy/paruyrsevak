@extends('layouts.admin')

@section('title', 'Նոր բանաստեղծություն')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-bold">Նոր բանաստեղծություն</h1>
        <p class="text-sm text-stone-500">Ավելացրու վերնագիրը և տեքստը։</p>
    </div>

    <form method="POST" action="{{ route('admin.poems.store') }}">
        @csrf
        @include('admin.poems._form', ['poem' => $poem])
    </form>
@endsection
