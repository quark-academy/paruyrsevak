<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Վահանակ') · {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=noto-sans-armenian:400,500,600,700" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-stone-100 text-stone-900 antialiased"
      style="font-family: 'Noto Sans Armenian', ui-sans-serif, system-ui, sans-serif;">

<header class="border-b border-stone-800 bg-stone-900 text-stone-100">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-stone-100 text-sm font-bold text-stone-900">ՊՍ</span>
            <div class="text-sm leading-tight">
                <div class="font-semibold">Ադմին վահանակ</div>
                <div class="text-[11px] text-stone-400">{{ config('app.name') }}</div>
            </div>
        </div>

        @auth
            <nav class="flex flex-wrap items-center gap-1 text-sm">
                <a href="{{ route('admin.dashboard') }}"
                   class="rounded-lg px-3 py-1.5 {{ request()->routeIs('admin.dashboard') ? 'bg-stone-700' : 'hover:bg-stone-800' }}">Գլխավոր</a>
                <a href="{{ route('admin.poems.index') }}"
                   class="rounded-lg px-3 py-1.5 {{ request()->routeIs('admin.poems.*') ? 'bg-stone-700' : 'hover:bg-stone-800' }}">Բանաստեղծություններ</a>
                <a href="{{ route('home') }}" target="_blank" class="rounded-lg px-3 py-1.5 hover:bg-stone-800">Կայք ↗</a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg px-3 py-1.5 text-rose-300 hover:bg-stone-800">Ելք</button>
                </form>
            </nav>
        @endauth
    </div>
</header>

<div class="mx-auto max-w-6xl px-4 py-6">
    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</div>

@stack('scripts')
</body>
</html>
