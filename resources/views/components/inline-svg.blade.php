@php
    /**
     * Renders an SVG file inline in the HTML, sanitized for safe embedding.
     *
     * Props:
     *   $path  — Path to the SVG file on the public disk (or absolute path)
     *   $media — Spatie Media object (alternative to $path)
     *   $class — CSS class to inject into the <svg> tag
     */

    use App\Services\Media\SvgSanitizer;
    use Illuminate\Support\Facades\Storage;

    $svgContent = null;

    if (!empty($media)) {
        $fullPath = $media->getPath();
        if (file_exists($fullPath)) {
            $svgContent = file_get_contents($fullPath);
        }
    } elseif (!empty($path)) {
        if (str_starts_with($path, '/') || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $svgContent = file_exists($path) ? file_get_contents($path) : null;
        } else {
            $svgContent = Storage::disk('public')->exists($path)
                ? Storage::disk('public')->get($path)
                : null;
        }
    }

    $sanitized = $svgContent ? SvgSanitizer::sanitize($svgContent) : null;

    // Inject class attribute into the <svg> tag if provided
    if ($sanitized && !empty($class)) {
        $sanitized = preg_replace('/<svg\s/', '<svg class="' . e($class) . '" ', $sanitized, 1);
    }

    // Inject any extra attributes
    $attrs = $attributes->except(['path', 'media', 'class'])->toHtml();
    if ($sanitized && $attrs) {
        $sanitized = preg_replace('/<svg\s/', "<svg {$attrs} ", $sanitized, 1);
    }
@endphp

@if ($sanitized)
    {!! $sanitized !!}
@endif
