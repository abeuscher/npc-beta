@extends('layouts.public', [
    'title'         => $page->meta_title ?? $page->title,
    'description'   => $page->meta_description,
    'inlineStyles'  => $inlineStyles ?? '',
    'inlineScripts' => $inlineScripts ?? '',
])

@section('content')
    <main>
        <h1>{{ $page->title }}</h1>

        @if ($page->content)
            <div class="page-content">
                {!! $page->content !!}
            </div>
        @endif

        <x-page-widgets :blocks="$blocks ?? []" />
    </main>
@endsection
