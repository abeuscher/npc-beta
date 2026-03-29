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
    $layout = match ($pageType) {
        'member' => 'layouts.portal',
        'system' => (auth('portal')->check() ? 'layouts.portal' : 'layouts.public'),
        default  => 'layouts.public',
    };
@endphp

@extends($layout, [
    'title'         => $page->meta_title ?? $page->title,
    'description'   => $page->meta_description,
    'inlineStyles'  => $inlineStyles ?? '',
    'inlineScripts' => $inlineScripts ?? '',
    'bodyClass'     => $bodyClass,
])

@section('content')
    <article>
        <x-page-widgets :blocks="$blocks ?? []" />
    </article>
@endsection
