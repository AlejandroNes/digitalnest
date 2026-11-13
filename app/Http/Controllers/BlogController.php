<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    private const PER_PAGE = 12;

    public function index(Request $request): View
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return $this->listing(null);
    }

    public function category(Request $request, string $slug): View
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return $this->listing(PostCategory::query()->where('slug', $slug)->firstOrFail());
    }

    private function listing(?PostCategory $category): View
    {
        $posts = Post::query()->published()
            ->when($category, fn (Builder $query, PostCategory $category): Builder => $query->whereHas('categories', fn (Builder $query): Builder => $query->whereKey($category->id)))
            ->with(['image.model', 'categories'])
            ->newest()
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('public.posts.index', [
            'posts' => $posts,
            'category' => $category,
            'categories' => PostCategory::query()
                ->whereHas('posts', fn (Builder $query): Builder => $query->published())
                ->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }
}
