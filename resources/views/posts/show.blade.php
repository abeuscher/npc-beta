@extends('layouts.public')

@section('content')
<main>
    <article>
        <header>
            <h1>{{ $post->title }}</h1>
            <p>
                @if ($post->author)
                    By {{ $post->author->name }} &mdash;
                @endif
                @if ($post->published_at)
                    <time datetime="{{ $post->published_at->toIso8601String() }}">
                        {{ $post->published_at->format('F j, Y') }}
                    </time>
                @endif
            </p>
        </header>

        <div class="post-content">
            {!! $post->content !!}
        </div>

        <footer>
            <a href="{{ route('posts.index') }}">&larr; Back to {{ config('site.name', 'News') }}</a>
        </footer>
    </article>
</main>
@endsection
