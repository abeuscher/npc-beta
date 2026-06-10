@extends('layouts.public', [
    'title'       => $siteName . ' Docs',
    'description' => 'Documentation for ' . $siteName . ' — guides and reference for every part of the product.',
    'bodyClass'   => 'docs-index',
])

@section('content')
    <div class="docs docs-container">
        <h1>Documentation</h1>
        <p class="docs-lede">Guides and reference for every part of {{ $siteName }}. Every article is also available as raw Markdown — append <code>.md</code> to its URL.</p>

        @foreach ($grouped as $category => $articles)
            <section class="docs-category">
                <h2>{{ \App\Http\Controllers\DocsController::categoryLabel($category) }}</h2>
                <ul class="docs-article-list">
                    @foreach ($articles as $article)
                        <li>
                            <a href="{{ route('docs.show', $article->slug) }}">{{ $article->title }}</a>
                            @if ($article->description)
                                <p class="docs-article-desc">{{ $article->description }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endsection
