<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Post;
use App\Models\Ranking;
use App\Models\Tool;
use App\Models\ToolCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SearchController extends Controller
{
    private const LIMIT = 8;

    public function __invoke(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim(preg_replace('/\s+/u', ' ', (string) ($data['q'] ?? '')));

        $results = mb_strlen($term) >= 2 ? [
            'deals' => Deal::query()->active()->with('tool.logo.model')
                ->where(fn (Builder $query): Builder => $this->matches($query, $term, ['title', 'description'])
                    ->orWhereHas('tool', fn (Builder $query): Builder => $this->matches($query, $term, ['name'])))
                ->ordered()->limit(self::LIMIT)->get(),
            'sectors' => ToolCategory::query()->active()
                ->where(fn (Builder $query): Builder => $this->matches($query, $term, ['name']))
                ->ordered()->limit(self::LIMIT)->get(),
            'rankings' => Ranking::query()->published()
                ->where(fn (Builder $query): Builder => $this->matches($query, $term, ['title']))
                ->ordered()->limit(self::LIMIT)->get(),
            'tools' => Tool::query()->published()->with(['category', 'logo.model'])
                ->where(fn (Builder $query): Builder => $this->matches($query, $term, ['name', 'tagline']))
                ->ordered()->limit(self::LIMIT)->get(),
            'posts' => Post::query()->published()->with(['image.model', 'categories'])
                ->where(fn (Builder $query): Builder => $this->matches($query, $term, ['title', 'excerpt']))
                ->newest()->limit(self::LIMIT)->get(),
        ] : [];

        return view('nest.search', [
            'term' => $term,
            'results' => $results,
            'total' => collect($results)->sum(fn (Collection $items): int => $items->count()),
        ]);
    }

    /**
     * Case-insensitive match of every word against any of the columns, with LIKE wildcards escaped.
     *
     * @param  list<string>  $columns
     */
    private function matches(Builder $query, string $term, array $columns): Builder
    {
        foreach (array_slice(explode(' ', $term), 0, 5) as $word) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($word)).'%';
            $query->where(function (Builder $query) use ($columns, $pattern): void {
                foreach ($columns as $column) {
                    $query->orWhereRaw('LOWER('.$query->getGrammar()->wrap($query->qualifyColumn($column)).") LIKE ? ESCAPE '!'", [$pattern]);
                }
            });
        }

        return $query;
    }
}
