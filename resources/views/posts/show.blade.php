@extends('layouts.public', [
    'title'         => $page->meta_title ?? $page->title,
    'description'   => $page->meta_description,
    'inlineStyles'  => $inlineStyles ?? '',
    'inlineScripts' => $inlineScripts ?? '',
])

@section('content')
    <main>
        <h1>{{ $page->title }}</h1>

        <x-page-widgets :blocks="$blocks ?? []" />

        <footer>
            <a href="{{ route('posts.index') }}">&larr; Back to News</a>
        </footer>
    </main>
@endsection
