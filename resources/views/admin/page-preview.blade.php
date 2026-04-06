@php
    use Illuminate\Support\Str;
    $pageType  = $page->type ?? 'default';
    $pageSlug  = Str::afterLast($page->slug, '/');
    $bodyClass = match ($pageType) {
        'post'   => 'post-' . $pageSlug,
        'event'  => 'event-' . $pageSlug,
        'member' => 'member-page-' . $pageSlug,
        'system' => 'system-page-' . $pageSlug,
        default  => 'page-' . $pageSlug . ' page-type-' . $pageType,
    };
@endphp

@extends('layouts.public', [
    'page'          => $page,
    'title'         => ($page->meta_title ?? $page->title) . ' — Preview',
    'description'   => $page->meta_description,
    'inlineStyles'  => $inlineStyles ?? '',
    'inlineScripts' => $inlineScripts ?? '',
    'bodyClass'     => $bodyClass . ' admin-preview',
])

@section('content')
    <article>
        @foreach ($blocks as $block)
            @php
                $sc = $block['style_config'] ?? [];
                $styleProps = [];
                $paddingKeys = ['padding_top' => 'padding-top', 'padding_right' => 'padding-right', 'padding_bottom' => 'padding-bottom', 'padding_left' => 'padding-left'];
                $marginKeys  = ['margin_top'  => 'margin-top',  'margin_right'  => 'margin-right',  'margin_bottom'  => 'margin-bottom',  'margin_left'  => 'margin-left'];
                foreach (array_merge($paddingKeys, $marginKeys) as $key => $cssProp) {
                    $val = isset($sc[$key]) && $sc[$key] !== '' ? (int) $sc[$key] : null;
                    if ($val !== null) {
                        $styleProps[] = $cssProp . ':' . $val . 'px';
                    }
                }
                $inlineStyle = implode(';', $styleProps);
                $instanceFullWidth = $sc['full_width'] ?? null;
                $isFullWidth = $instanceFullWidth !== null ? (bool) $instanceFullWidth : ($block['full_width'] ?? false);
            @endphp
            <div
                class="widget widget--{{ $block['handle'] }}"
                id="widget-{{ $block['instance_id'] }}"
                @if ($inlineStyle) style="{{ $inlineStyle }}" @endif
                data-preview-widget-id="{{ $block['instance_id'] }}"
            >
                {{-- Transparent click-target overlay --}}
                <div
                    class="preview-widget-handle"
                    data-widget-id="{{ $block['instance_id'] }}"
                    title="{{ $block['label'] ?? $block['handle'] }}"
                >
                    <span class="preview-widget-handle__label">{{ $block['label'] ?? $block['handle'] }}</span>
                </div>

                @if ($isFullWidth)
                    {!! $block['html'] !!}
                @else
                    <div class="site-container">
                        {!! $block['html'] !!}
                    </div>
                @endif
            </div>
        @endforeach
    </article>

    <style>
        .widget[data-preview-widget-id] {
            position: relative;
        }
        .preview-widget-handle {
            position: absolute;
            inset: 0;
            z-index: 100;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .preview-widget-handle:hover {
            background: rgba(59, 130, 246, 0.08);
            outline: 2px solid rgba(59, 130, 246, 0.5);
            outline-offset: -2px;
        }
        .preview-widget-handle__label {
            position: absolute;
            top: 4px;
            left: 4px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
            color: white;
            background: rgba(59, 130, 246, 0.8);
            border-radius: 3px;
            opacity: 0;
            transition: opacity 0.15s ease;
            pointer-events: none;
        }
        .preview-widget-handle:hover .preview-widget-handle__label {
            opacity: 1;
        }
    </style>

    <script>
        document.querySelectorAll('.preview-widget-handle').forEach(handle => {
            handle.addEventListener('click', () => {
                const widgetId = handle.dataset.widgetId;
                window.parent.postMessage({ type: 'preview-widget-clicked', widgetId }, '*');
            });
        });
    </script>
@endsection
