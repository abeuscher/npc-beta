@php
    $svgPath = base_path("vendor/blade-ui-kit/blade-heroicons/resources/svg/o-{$name}.svg");
    $svg = file_exists($svgPath) ? file_get_contents($svgPath) : '';
    // Inject any extra attributes (class, style, etc.) into the <svg> tag
    $attrs = $attributes->except('name')->toHtml();
    if ($attrs && $svg) {
        $svg = preg_replace('/<svg\s/', "<svg {$attrs} ", $svg, 1);
    }
@endphp
{!! $svg !!}
