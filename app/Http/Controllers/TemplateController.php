<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\Tool;
use App\TemplateType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class TemplateController extends Controller
{
    private const PER_PAGE = 24;

    public function index(Request $request): View
    {
        $data = $request->validate([
            'tipo' => ['nullable', Rule::enum(TemplateType::class)],
            'herramienta' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $type = isset($data['tipo']) ? TemplateType::from($data['tipo']) : null;
        $tool = isset($data['herramienta']) ? Tool::query()->published()->where('slug', $data['herramienta'])->first() : null;

        $templates = Template::query()->published()
            ->with(['tools' => fn ($query) => $query->published()])
            ->when($type, fn (Builder $query, TemplateType $type): Builder => $query->where('type', $type))
            ->when($tool, fn (Builder $query, Tool $tool): Builder => $query->whereHas('tools', fn (Builder $query): Builder => $query->whereKey($tool->id)))
            ->ordered()
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('public.templates.index', [
            'templates' => $templates,
            'toolOptions' => Tool::query()->published()
                ->whereHas('templates', fn (Builder $query): Builder => $query->published())->orderBy('name')->get(['id', 'name', 'slug']),
            'filters' => ['tipo' => $type, 'herramienta' => $tool],
        ]);
    }

    public function show(string $slug): View
    {
        $template = $this->publishedTemplate($slug);

        return view('public.templates.show', [
            'template' => $template,
            'tools' => $template->tools()->published()->with(['logo.model', 'category'])->orderBy('name')->get(),
            'workflows' => $template->steps()->with('workflow')->get()->pluck('workflow')->filter(fn ($workflow) => $workflow?->isPublished())->unique('id')->values(),
        ]);
    }

    public function download(string $slug): Response
    {
        $template = $this->publishedTemplate($slug);

        return response($template->content, 200, [
            'Content-Type' => $template->format->mimeType().'; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$template->downloadFilename().'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function publishedTemplate(string $slug): Template
    {
        return Template::query()->published()->where('slug', $slug)->firstOrFail();
    }
}
