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
                $ac = $block['appearance_config'] ?? [];
                $styleProps = [];

                $bgColor = $ac['background']['color'] ?? null;
                if (! empty($bgColor) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $bgColor)) {
                    $styleProps[] = 'background-color:' . $bgColor;
                }
                $textColor = $ac['text']['color'] ?? null;
                if (! empty($textColor) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $textColor)) {
                    $styleProps[] = 'color:' . $textColor;
                }

                $padding = $ac['layout']['padding'] ?? [];
                $margin  = $ac['layout']['margin'] ?? [];
                foreach (['top', 'right', 'bottom', 'left'] as $side) {
                    $val = isset($padding[$side]) && $padding[$side] !== '' ? (int) $padding[$side] : null;
                    if ($val !== null) {
                        $styleProps[] = 'padding-' . $side . ':' . $val . 'px';
                    }
                }
                foreach (['top', 'right', 'bottom', 'left'] as $side) {
                    $val = isset($margin[$side]) && $margin[$side] !== '' ? (int) $margin[$side] : null;
                    if ($val !== null) {
                        $styleProps[] = 'margin-' . $side . ':' . $val . 'px';
                    }
                }

                $inlineStyle = implode(';', $styleProps);
                $instanceFullWidth = $ac['layout']['full_width'] ?? null;
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
