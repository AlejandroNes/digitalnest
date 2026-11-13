<?php

namespace App\Http\Controllers;

use App\Models\LegalPage;
use App\Models\Post;
use App\Models\Service;
use Illuminate\Contracts\View\View;

class PublicContentController extends Controller
{
    public function post(string $slug): View
    {
        $post = Post::query()->published()->with(['image.model', 'seoImage', 'categories'])->where('slug', $slug)->firstOrFail();
        $categoryIds = $post->categories->pluck('id');

        return view('public.posts.show', [
            'post' => $post,
            'related' => Post::query()->published()->whereKeyNot($post->id)
                ->when($categoryIds->isNotEmpty(), fn ($query) => $query->whereHas('categories', fn ($query) => $query->whereIn('post_categories.id', $categoryIds)))
                ->with(['image.model', 'categories'])->newest()->limit(3)->get(),
        ]);
    }

    public function service(string $slug): View
    {
        $content = Service::query()->active()->with(['image.model'])->where('slug', $slug)->firstOrFail();

        return view('public.content', ['content' => $content, 'body' => $content->description]);
    }

    public function legal(string $type): View
    {
        $content = LegalPage::query()->published()->where('type', $type)->firstOrFail();

        return view('public.content', ['content' => $content, 'body' => $content->content]);
    }
}
