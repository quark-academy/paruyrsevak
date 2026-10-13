<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Մուտք · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=noto-sans-armenian:400,500,600,700" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="grid min-h-screen place-items-center bg-stone-900 px-4 text-stone-100"
      style="font-family: 'Noto Sans Armenian', ui-sans-serif, system-ui, sans-serif;">

<form method="POST" action="{{ route('admin.login.attempt') }}"
      class="w-full max-w-sm rounded-2xl bg-stone-800 p-6 shadow-xl">
    @csrf

    <div class="mb-5 flex items-center gap-3">
        <span class="grid h-10 w-10 place-items-center rounded-full bg-stone-100 text-sm font-bold text-stone-900">ՊՍ</span>
        <div class="text-sm leading-tight">
            <div class="font-semibold">Ադմին վահանակ</div>
            <div class="text-[11px] text-stone-400">Մուտք գործելու համար</div>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-rose-500/40 bg-rose-500/10 px-3 py-2 text-xs text-rose-200">
            {{ $errors->first() }}
        </div>
    @endif

    <label class="mb-1 block text-xs text-stone-400" for="email">Էլ. փոստ</label>
    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
           class="mb-3 w-full rounded-lg border border-stone-600 bg-stone-900 px-3 py-2 text-sm text-stone-100 focus:border-stone-400 focus:outline-none">

    <label class="mb-1 block text-xs text-stone-400" for="password">Գաղտնաբառ</label>
    <input id="password" type="password" name="password" required
           class="mb-3 w-full rounded-lg border border-stone-600 bg-stone-900 px-3 py-2 text-sm text-stone-100 focus:border-stone-400 focus:outline-none">

    <label class="mb-4 flex items-center gap-2 text-xs text-stone-400">
        <input type="checkbox" name="remember" value="1" class="rounded border-stone-600 bg-stone-900">
        Հիշել ինձ
    </label>

    <button type="submit" class="w-full rounded-lg bg-stone-100 px-4 py-2 text-sm font-semibold text-stone-900 hover:bg-white">
        Մուտք
    </button>
</form>

</body>
</html>
