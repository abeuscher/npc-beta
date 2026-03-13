@extends('layouts.public', [
    'title'       => $page->meta_title ?? $page->title,
    'description' => $page->meta_description,
])

@section('content')
    <main>
        <h1>{{ $page->title }}</h1>

        <div class="page-content">
            {!! $page->content !!}
        </div>
    </main>
@endsection
