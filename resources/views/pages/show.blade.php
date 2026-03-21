@php
    use Illuminate\Support\Str;
    $pageType  = $page->type ?? 'default';
    $pageSlug  = Str::afterLast($page->slug, '/');
    $bodyClass = match ($pageType) {
        'post'  => 'post-' . $pageSlug,
        'event' => 'event-' . $pageSlug,
        default => 'page-' . $pageSlug . ' page-type-' . $pageType,
    };
@endphp

@extends('layouts.public', [
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
