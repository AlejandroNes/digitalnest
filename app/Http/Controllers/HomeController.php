<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Ranking;
use App\Models\Tool;
use App\Models\ToolCategory;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $dealRelations = ['tool.logo.model'];

        return view('nest.home', [
            'featuredDeals' => Deal::query()->active()->with($dealRelations)->ordered()->limit(6)->get(),
            'expiringDeals' => Deal::query()->active()->expiringWithin(3)->with($dealRelations)->orderBy('expires_at')->limit(6)->get(),
            'sectors' => ToolCategory::query()->active()->roots()->ordered()->get(),
            'ranking' => Ranking::query()->published()->ordered()
                ->with(['tools' => fn ($query) => $query->published()->with('logo.model')->limit(5)])
                ->first(),
            'latestTools' => Tool::query()->published()->with('logo.model')->latest('published_at')->limit(6)->get(),
        ]);
    }
}
