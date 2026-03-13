@extends('layouts.public')

@section('content')
<main>
    <h1>{{ $title }}</h1>

    @if ($posts->isEmpty())
        <p>No posts published yet. Check back soon.</p>
    @else
        @foreach ($posts as $post)
            <article>
                <h2>
                    <a href="{{ route('posts.show', $post->slug) }}">{{ $post->title }}</a>
                </h2>

                @if ($post->excerpt)
                    <p>{{ $post->excerpt }}</p>
                @endif

                <footer>
                    @if ($post->author)
                        <span>By {{ $post->author->name }}</span> &mdash;
                    @endif
                    @if ($post->published_at)
                        <time datetime="{{ $post->published_at->toIso8601String() }}">
                            {{ $post->published_at->format('F j, Y') }}
                        </time>
                    @endif
                    &mdash;
                    <a href="{{ route('posts.show', $post->slug) }}">Read more &rarr;</a>
                </footer>
            </article>
        @endforeach

        {{ $posts->links() }}
    @endif
</main>
@endsection
