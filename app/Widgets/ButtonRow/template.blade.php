@php
    $buttons = is_array($config['buttons'] ?? null) ? $config['buttons'] : [];
    $alignment = in_array($config['alignment'] ?? 'left', ['left', 'center', 'right'], true)
        ? $config['alignment']
        : 'left';
@endphp

@if (!empty($buttons))
    <div class="widget-button-row">
        <x-widget-buttons :buttons="$buttons" :alignment="$alignment" />
    </div>
@endif
