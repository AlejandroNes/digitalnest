<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\Workflow;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $featured = Tool::query()->published()->where('is_featured', true)
            ->with(['category', 'logo.model'])->ordered()->limit(5)->get();

        if ($featured->isEmpty()) {
            $featured = Tool::query()->published()->with(['category', 'logo.model'])
                ->orderByDesc('rating')->orderBy('name')->limit(5)->get();
        }

        return view('public.home', [
            'featured' => $featured,
            'categories' => ToolCategory::query()->active()
                ->withCount(['tools' => fn (Builder $query): Builder => $query->published()])
                ->whereHas('tools', fn (Builder $query): Builder => $query->published())
                ->ordered()->get(),
            'latest' => Tool::query()->published()->with(['category', 'logo.model'])
                ->latest('published_at')->limit(4)->get(),
            'toolCount' => Tool::query()->published()->count(),
            'workflows' => Workflow::query()->published()->withCount('steps')->ordered()->limit(3)->get(),
        ]);
    }
}
