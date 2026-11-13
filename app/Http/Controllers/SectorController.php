<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Ranking;
use App\Models\Tool;
use App\Models\ToolCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class SectorController extends Controller
{
    public function index(): View
    {
        return view('nest.explore', [
            'sectors' => ToolCategory::query()->active()->roots()->ordered()
                ->with(['children' => fn ($query) => $query->active()->ordered()])
                ->get(),
        ]);
    }

    public function show(string $slug): View
    {
        $sector = ToolCategory::query()->active()->where('slug', $slug)
            ->with(['parent', 'children' => fn ($query) => $query->active()->ordered()])
            ->firstOrFail();
        $sectorIds = $sector->familyIds();

        return view('nest.sector', [
            'sector' => $sector,
            'deals' => Deal::query()->active()->inSectors($sectorIds)->with('tool.logo.model')->ordered()->limit(6)->get(),
            'rankings' => Ranking::query()->published()->whereIn('tool_category_id', $sectorIds)->withCount('tools')->ordered()->limit(4)->get(),
            'tools' => Tool::query()->published()
                ->where(fn (Builder $query): Builder => $query->whereIn('tool_category_id', $sectorIds)
                    ->orWhereHas('secondaryCategories', fn (Builder $query): Builder => $query->whereIn('tool_categories.id', $sectorIds)))
                ->with('logo.model')->ordered()->paginate(24),
        ]);
    }
}
