<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Widget demo — {{ $handle }}</title>

    @vite(['resources/scss/public.scss', 'resources/js/public.js'])

    @php
        $__widgetManifest = null;
        $__manifestPath = public_path('build/widgets/manifest.json');
        if (file_exists($__manifestPath)) {
            $__widgetManifest = json_decode(file_get_contents($__manifestPath), true);
        }
    @endphp
    @if ($__widgetManifest && ! empty($__widgetManifest['css']))
        <link rel="stylesheet" href="/build/widgets/{{ $__widgetManifest['css'] }}">
    @endif

    @if (! empty($rendered['styles']))
        <style>{!! $rendered['styles'] !!}</style>
    @endif

    <style>
        html, body { margin: 0; padding: 0; }
        body { width: 800px; height: 500px; overflow: hidden; background: #ffffff; }
        .np-widget-demo__wrap { width: 100%; height: 100%; box-sizing: border-box; }
    </style>
</head>
<body class="np-widget-demo">
    <div class="np-widget-demo__wrap" @if (! empty($appearance['inline_style'])) style="{{ $appearance['inline_style'] }}" @endif>
        {!! $rendered['html'] !!}
    </div>

    @if ($__widgetManifest && ! empty($__widgetManifest['js']))
        <script src="/build/widgets/{{ $__widgetManifest['js'] }}"></script>
    @endif

    @if (! empty($rendered['scripts']))
        <script>{!! $rendered['scripts'] !!}</script>
    @endif
</body>
</html>
