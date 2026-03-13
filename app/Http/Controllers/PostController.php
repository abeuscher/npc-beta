<?php

namespace App\Http\Controllers;

use App\Models\Post;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::where('is_published', true)
            ->orderBy('published_at', 'desc')
            ->paginate(15);

        return view('posts.index', [
            'posts'       => $posts,
            'title'       => 'News',
            'description' => 'Latest news and updates.',
        ]);
    }

    public function show(string $slug)
    {
        $post = Post::where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if (! $post) {
            abort(404);
        }

        return view('posts.show', [
            'post'        => $post,
            'title'       => $post->title,
            'description' => $post->excerpt ?? '',
        ]);
    }
}
