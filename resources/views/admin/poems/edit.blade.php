@extends('layouts.admin')

@section('title', 'Խմբագրել՝ '.$poem->title)

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold">Խմբագրել</h1>
            <p class="text-sm text-stone-500">{{ $poem->title }}</p>
        </div>
        <a href="{{ route('admin.poems.timings', $poem) }}"
           class="rounded-lg bg-stone-200 px-4 py-2 text-sm font-medium hover:bg-stone-300">Կարաոկե ժամանակացույց →</a>
    </div>

    <form method="POST" action="{{ route('admin.poems.update', $poem) }}">
        @csrf
        @method('PUT')
        @include('admin.poems._form', ['poem' => $poem])
    </form>
@endsection
