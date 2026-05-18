@php
    /**
     * Inline-editable prose node (session 304). Renders the annotated
     * wrapper so the page-builder's in-place editor has a stable anchor —
     * including when the value is blank (builder only). Public output is
     * unchanged: a blank node renders nothing; a filled node renders its
     * content plus the long-dormant data-config-* hooks (inert on public).
     * The in-place editor seeds from the RAW config value via the store,
     * never from this rendered node — so token/inline-image processing on
     * display never round-trips through inline editing.
     *
     * Passed: $tag, $class, $key (config path), $type (text|richtext),
     * $value (the value the template would normally echo), $label,
     * $always (optional — when true the wrapper always renders even when
     * blank on public too; required for subgrid-track divs whose absence
     * would break cross-column alignment).
     * Inherited: $inlineEditing (true only on the builder canvas render).
     */
    $inlineEditing = $inlineEditing ?? false;
    $always  = $always ?? false;
    $isRich  = ($type ?? 'text') === 'richtext';
    $rawVal  = (string) ($value ?? '');
    $isEmpty = $isRich ? trim(strip_tags($rawVal)) === '' : $rawVal === '';
@endphp
@if ($always || $inlineEditing || ! $isEmpty)
<{{ $tag }} class="{{ $class }}" data-config-key="{{ $key }}" data-config-type="{{ $isRich ? 'richtext' : 'text' }}"@if ($inlineEditing) data-config-placeholder="{{ $label ?? '' }}"@endif>{!! $isRich ? $rawVal : e($rawVal) !!}</{{ $tag }}>
@endif
