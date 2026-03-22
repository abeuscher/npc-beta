@php
    use Illuminate\Support\Str;
    $pageType  = $page->type ?? 'default';
    $pageSlug  = Str::afterLast($page->slug, '/');
    $bodyClass = match ($pageType) {
        'post'   => 'post-' . $pageSlug,
        'event'  => 'event-' . $pageSlug,
        'member' => 'member-page-' . $pageSlug,
        default  => 'page-' . $pageSlug . ' page-type-' . $pageType,
    };
    $layout = $pageType === 'member' ? 'layouts.portal' : 'layouts.public';
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
        <h1>{{ $page->title }}</h1>

        <x-page-widgets :blocks="$blocks ?? []" />
    </article>
@endsection
