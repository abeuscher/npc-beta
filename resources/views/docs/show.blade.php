@extends('layouts.public', [
    'title'       => $article->title . ' — ' . $siteName . ' Docs',
    'description' => $article->description,
    'bodyClass'   => 'docs-article docs-article-' . $article->slug,
])

@section('content')
    <article class="docs docs-container">
        <nav class="docs-breadcrumbs" aria-label="Breadcrumb">
            <a href="{{ route('docs.index') }}">Docs</a>
            @foreach ($ancestors as $ancestor)
                <span aria-hidden="true">/</span>
                <a href="{{ route('docs.show', $ancestor->slug) }}">{{ $ancestor->title }}</a>
            @endforeach
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $article->title }}</span>
        </nav>

        <h1>{{ $article->title }}</h1>

        @if ($article->description)
            <p class="docs-lede">{{ $article->description }}</p>
        @endif

        <p class="docs-meta">
            @if ($article->last_updated)
                <span>Last updated {{ $article->last_updated->format('F j, Y') }}</span>
                <span aria-hidden="true">&middot;</span>
            @endif
            <a href="{{ route('docs.raw', $article->slug) }}">View as Markdown</a>
        </p>

        <div class="docs-body">
            {!! \Illuminate\Support\Str::markdown(app(\App\Services\HelpArticleService::class)->bodyWithoutLeadingH1($article->content)) !!}
        </div>

        @if (count($relatedArticles) > 0)
            <section class="docs-related">
                <h2>Related articles</h2>
                <ul>
                    @foreach ($relatedArticles as $related)
                        <li><a href="{{ route('docs.show', $related['slug']) }}">{{ $related['title'] }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <p class="docs-back"><a href="{{ route('docs.index') }}">&larr; All documentation</a></p>
    </article>
@endsection
