<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#1c1917">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', \App\Support\Seo::defaultDescription())">
    <link rel="canonical" href="@yield('canonical', \App\Support\Seo::canonical(request()->path()))">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:url" content="@yield('canonical', \App\Support\Seo::canonical(request()->path()))">
    <meta name="twitter:card" content="summary">
    @stack('meta')

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=noto-sans-armenian:400,500,600,700" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased"
      style="font-family: 'Noto Sans Armenian', ui-sans-serif, system-ui, sans-serif;">
<header class="sticky top-0 z-30 border-b border-stone-200/70 bg-stone-50/85 backdrop-blur">
    <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-2.5">
        <a href="{{ route('home') }}" class="group flex items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-stone-900 text-[13px] font-bold text-amber-200 ring-1 ring-amber-300/30 transition group-hover:ring-amber-300/60">ՊՍ</span>
            <span class="text-base font-semibold leading-tight">
                Պարույր Սևակ
                <span class="block text-[11px] font-normal text-stone-500">բանաստեղծությունների ձայնը</span>
            </span>
        </a>

        <nav class="flex items-center gap-1">
            <a href="{{ route('poems.index') }}"
               class="rounded-full px-3 py-1.5 text-sm font-medium text-stone-600 transition hover:bg-stone-200/60 hover:text-stone-900">Բոլորը</a>
            <a href="{{ route('admin.dashboard') }}"
               class="rounded-full px-3 py-1.5 text-sm font-medium text-stone-600 transition hover:bg-stone-200/60 hover:text-stone-900">Վահանակ</a>
        </nav>
    </div>
</header>

<main class="mx-auto max-w-3xl px-4 py-6">
    @yield('content')
</main>

<footer class="mx-auto max-w-3xl px-4 pb-10 pt-4 text-center text-xs text-stone-400">
    © {{ date('Y') }} {{ config('app.name') }} · Նվիրված Պարույր Սևակին
</footer>

@stack('scripts')
</body>
</html>
