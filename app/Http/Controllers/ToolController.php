<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use App\Models\ToolCategory;
use App\ToolPricingModel;
use App\ToolSpanishSupport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ToolController extends Controller
{
    private const PER_PAGE = 24;

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('public.tools.index', [
            'category' => null,
            'categories' => $this->categories(),
            'filters' => $filters,
            'tools' => $this->filteredQuery(Tool::query(), $filters)->paginate(self::PER_PAGE)->withQueryString(),
        ]);
    }

    public function category(Request $request, string $slug): View
    {
        $category = ToolCategory::query()->active()->where('slug', $slug)->firstOrFail();
        $filters = $this->filters($request);

        return view('public.tools.index', [
            'category' => $category,
            'categories' => $this->categories(),
            'filters' => $filters,
            'tools' => $this->filteredQuery(Tool::query()->inCategory($category), $filters)->paginate(self::PER_PAGE)->withQueryString(),
        ]);
    }

    public function show(string $slug): View
    {
        $tool = $this->publishedTool($slug);

        return view('public.tools.show', [
            'tool' => $tool,
            'alternatives' => $this->publishedAlternatives($tool),
        ]);
    }

    public function alternatives(string $slug): View
    {
        $tool = $this->publishedTool($slug);
        $alternatives = $this->publishedAlternatives($tool);
        abort_if($alternatives->isEmpty(), 404);

        return view('public.tools.alternatives', ['tool' => $tool, 'alternatives' => $alternatives]);
    }

    /**
     * @return array{precio: ?string, gratis: bool, espanol: bool}
     */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'precio' => ['nullable', Rule::enum(ToolPricingModel::class)],
            'gratis' => ['nullable', 'boolean'],
            'espanol' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return [
            'precio' => $data['precio'] ?? null,
            'gratis' => (bool) ($data['gratis'] ?? false),
            'espanol' => (bool) ($data['espanol'] ?? false),
        ];
    }

    /**
     * @param  array{precio: ?string, gratis: bool, espanol: bool}  $filters
     */
    private function filteredQuery(Builder $query, array $filters): Builder
    {
        return $query->published()
            ->with(['category', 'logo.model'])
            ->when($filters['precio'], fn (Builder $query, string $pricing): Builder => $query->where('pricing_model', $pricing))
            ->when($filters['gratis'], fn (Builder $query): Builder => $query->where('has_free_plan', true))
            ->when($filters['espanol'], fn (Builder $query): Builder => $query->where('spanish_support', ToolSpanishSupport::Full))
            ->ordered();
    }

    /**
     * @return Collection<int, ToolCategory>
     */
    private function categories(): Collection
    {
        return ToolCategory::query()->active()
            ->whereHas('tools', fn (Builder $query): Builder => $query->published())
            ->ordered()->get(['id', 'name', 'slug']);
    }

    private function publishedTool(string $slug): Tool
    {
        return Tool::query()->published()
            ->with(['category', 'secondaryCategories', 'logo.model', 'image.model', 'seoImage'])
            ->where('slug', $slug)->firstOrFail();
    }

    /**
     * @return Collection<int, Tool>
     */
    private function publishedAlternatives(Tool $tool): Collection
    {
        return $tool->alternatives()->published()->with(['category', 'logo.model'])->get();
    }
}
