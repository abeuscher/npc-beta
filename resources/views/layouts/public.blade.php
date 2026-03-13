<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? config('app.name') }}</title>

    @if (!empty($description))
        <meta name="description" content="{{ $description }}">
    @endif

    {{-- Optional Pico CSS — enable via THEME_PICO=true in .env --}}
    @if (config('theme.pico', false))
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    @endif

    {{-- Custom stylesheet hook — push styles onto this stack from any view --}}
    @stack('styles')
</head>
<body>

    @yield('content')

    @stack('scripts')
</body>
</html>
