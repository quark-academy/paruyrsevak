@php
    use App\Support\Seo;

    $seoTitle = filled($poem->meta_title) ? $poem->meta_title : $poem->title.' — '.config('app.name');
    $seoDescription = Seo::descriptionForPoem($poem);
    $seoCanonical = Seo::forPoem($poem);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ config('poems.player.background') }}">

    {{-- Dynamic SEO from the database --}}
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">

    {{-- Canonical: https://subdomain.domain.am/{slug} --}}
    <link rel="canonical" href="{{ $seoCanonical }}">

    <meta name="robots" content="index, follow, max-image-preview:large">

    {{-- Open Graph / Twitter --}}
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoCanonical }}">
    <meta property="og:locale" content="hy_AM">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=noto-sans-armenian:400,500,600,700" rel="stylesheet">

    @if ($poem->hasAudio())
        <link rel="preload" as="audio" href="{{ $poem->audioUrl() }}">
    @endif

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/poem-shorts.js'])
    @endif

    {{-- Structured data --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'name' => $poem->title,
            'headline' => $poem->title,
            'inLanguage' => 'hy',
            'url' => $seoCanonical,
            'author' => ['@type' => 'Person', 'name' => 'Պարույր Սևակ'],
            'text' => $poem->excerpt(400),
            'dateModified' => $poem->updated_at?->toAtomString(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
</head>
<body class="min-h-[100dvh] bg-[#0b0a09] text-stone-100 antialiased"
      style="font-family: 'Noto Sans Armenian', ui-sans-serif, system-ui, sans-serif;">

<div id="poem-shorts">
    <div class="grid min-h-[100dvh] place-items-center">
        <div class="h-16 w-16 animate-pulse rounded-full border-2 border-amber-300/40 border-t-amber-300"></div>
    </div>
</div>

<script type="application/json" id="poem-shorts-payload">
    @json($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
</script>

<noscript>
    <main class="mx-auto max-w-2xl px-5 py-10">
        <h1 class="text-2xl font-bold">{{ $poem->title }}</h1>
        <p class="mt-1 text-sm text-stone-400">Պարույր Սևակ</p>
        @if ($poem->hasAudio())
            <audio controls src="{{ $poem->audioUrl() }}" class="mt-4 w-full"></audio>
        @endif
        <div class="mt-6 whitespace-pre-line text-lg leading-relaxed">{{ $poem->content }}</div>
        <p class="mt-8 text-sm text-stone-400">
            <a class="underline" href="{{ route('poems.index') }}">Բոլոր բանաստեղծությունները</a>
        </p>
    </main>
</noscript>

</body>
</html>
