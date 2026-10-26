{{--
    Everything a crawler or a social network reads about a page.
    The payload is built by App\Support\Seo (see Seo::page / Seo::poemPage), so
    no view has to think about titles, lengths or structured data.
--}}
@php
    $seo = $seo ?? \App\Support\Seo::page([
        'title' => config('app.name'),
        'description' => \App\Support\Seo::defaultDescription(),
        'canonical' => \App\Support\Seo::canonical(request()->path()),
    ]);
@endphp

<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
@if ($seo['keywords'] !== '')
    <meta name="keywords" content="{{ $seo['keywords'] }}">
@endif
<meta name="author" content="{{ \App\Support\Seo::POET }}">
<meta name="robots" content="{{ $seo['robots'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
<link rel="alternate" hreflang="{{ $seo['language'] }}" href="{{ $seo['canonical'] }}">
<link rel="alternate" hreflang="x-default" href="{{ $seo['canonical'] }}">

<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:locale" content="{{ $seo['locale'] }}">
<meta property="og:type" content="{{ $seo['type'] }}">
<meta property="og:title" content="{{ $seo['og_title'] }}">
<meta property="og:description" content="{{ $seo['og_description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">

@if ($seo['image'])
    <meta property="og:image" content="{{ $seo['image']['url'] }}">
    <meta property="og:image:alt" content="{{ $seo['image']['alt'] }}">
    @if ($seo['image']['width'] > 0 && $seo['image']['height'] > 0)
        <meta property="og:image:width" content="{{ $seo['image']['width'] }}">
        <meta property="og:image:height" content="{{ $seo['image']['height'] }}">
    @endif
@endif

@if ($seo['article'])
    @if ($seo['article']['published'] ?? null)
        <meta property="article:published_time" content="{{ $seo['article']['published'] }}">
    @endif
    @if ($seo['article']['modified'] ?? null)
        <meta property="article:modified_time" content="{{ $seo['article']['modified'] }}">
    @endif
    <meta property="article:author" content="{{ \App\Support\Seo::POET }}">
    @if ($seo['article']['section'] ?? null)
        <meta property="article:section" content="{{ $seo['article']['section'] }}">
    @endif
    @foreach ($seo['article']['tags'] ?? [] as $tag)
        <meta property="article:tag" content="{{ $tag }}">
    @endforeach
@endif

<meta name="twitter:card" content="{{ $seo['image'] ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo['og_title'] }}">
<meta name="twitter:description" content="{{ $seo['og_description'] }}">
@if ($seo['image'])
    <meta name="twitter:image" content="{{ $seo['image']['url'] }}">
    <meta name="twitter:image:alt" content="{{ $seo['image']['alt'] }}">
@endif

<script type="application/ld+json">{!! $seo['graph_json'] !!}</script>
