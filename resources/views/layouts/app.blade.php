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

    {{-- The author's monochrome mark is the site icon as well. --}}
    <link rel="icon" type="image/png" href="{{ asset('images/sevak-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/sevak-logo.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=noto-sans-armenian:400,500,600,700" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased"
      style="font-family: 'Noto Sans Armenian', ui-sans-serif, system-ui, sans-serif;">
{{-- No menus: the author's mark and his name are the whole header, and on a
     phone only Պ. Սևակ. The bar keeps a full width background. --}}
<header class="sticky top-0 z-30 border-b border-stone-200/70 bg-stone-50/90 backdrop-blur">
    <div class="mx-auto flex max-w-3xl items-center px-4 py-2.5">
        <a href="{{ route('home') }}" class="group flex min-w-0 items-center gap-2.5" aria-label="Պարույր Սևակ">
            <img src="{{ asset('images/sevak-logo.png') }}" alt=""
                 class="h-9 w-9 shrink-0 rounded-full bg-stone-100 object-cover ring-1 ring-stone-200 transition group-hover:ring-amber-300">

            {{-- Phones: the short form, nothing else. --}}
            <span class="truncate text-base font-semibold sm:hidden">Պ. Սևակ</span>

            <span class="hidden min-w-0 leading-tight sm:block">
                <span class="block truncate text-base font-semibold">Պարույր Սևակ</span>
                <span class="block text-[11px] font-normal text-stone-500">բանաստեղծությունների ձայնը</span>
            </span>
        </a>
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
