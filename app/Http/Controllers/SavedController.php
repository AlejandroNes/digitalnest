<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Saved deals live only in the visitor's browser; the server just renders cards for the slugs it is sent.
 */
class SavedController extends Controller
{
    public const MAX_SAVED = 50;

    public function index(): View
    {
        return view('nest.saved');
    }

    public function cards(Request $request): View
    {
        $slugs = $request->validate([
            'slugs' => ['nullable', 'array', 'max:'.self::MAX_SAVED],
            'slugs.*' => ['string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
        ])['slugs'] ?? [];

        $deals = $slugs === [] ? collect() : Deal::query()->visible()->whereIn('slug', $slugs)
            ->with('tool.logo.model')->get()
            ->sortBy(fn (Deal $deal): int|false => array_search($deal->slug, $slugs, true));

        return view('nest.saved-cards', ['deals' => $deals]);
    }
}
