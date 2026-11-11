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
        $content = Post::query()->published()->with(['image.model', 'seoImage'])->where('slug', $slug)->firstOrFail();

        return view('public.content', ['content' => $content, 'body' => $content->content]);
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
