<?php

namespace App\Http\Controllers;

use App\Models\Ranking;
use Illuminate\Contracts\View\View;

class RankingController extends Controller
{
    public function index(): View
    {
        return view('nest.rankings.index', [
            'rankings' => Ranking::query()->published()->ordered()
                ->with(['category', 'tools' => fn ($query) => $query->published()->with('logo.model')])
                ->paginate(20),
        ]);
    }

    public function show(string $slug): View
    {
        $ranking = Ranking::query()->published()->where('slug', $slug)
            ->with(['category', 'tools' => fn ($query) => $query->published()->with([
                'logo.model',
                'deals' => fn ($query) => $query->active()->ordered(),
            ])])
            ->firstOrFail();

        return view('nest.rankings.show', ['ranking' => $ranking]);
    }
}
