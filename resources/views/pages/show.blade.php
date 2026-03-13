@extends('layouts.public', [
    'title'       => $page->meta_title ?? $page->title,
    'description' => $page->meta_description,
])

@section('content')
    <main>
        <h1>{{ $page->title }}</h1>

        @if ($page->content)
            <div class="page-content">
                {!! $page->content !!}
            </div>
        @endif

        <x-page-widgets :widgets="$widgets" />
    </main>
@endsection
