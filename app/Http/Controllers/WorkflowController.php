<?php

namespace App\Http\Controllers;

use App\Models\ToolCategory;
use App\Models\Workflow;
use App\WorkflowDifficulty;
use App\WorkflowProfession;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    private const PER_PAGE = 18;

    public function index(Request $request): View
    {
        $data = $request->validate([
            'para' => ['nullable', Rule::enum(WorkflowProfession::class)],
            'tarea' => ['nullable', 'string', 'max:255'],
            'nivel' => ['nullable', Rule::enum(WorkflowDifficulty::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $profession = isset($data['para']) ? WorkflowProfession::from($data['para']) : null;
        $difficulty = isset($data['nivel']) ? WorkflowDifficulty::from($data['nivel']) : null;
        $task = isset($data['tarea']) ? ToolCategory::query()->active()->where('slug', $data['tarea'])->first() : null;

        $workflows = Workflow::query()->published()
            ->with(['category'])->withCount('steps')
            ->when($profession, fn (Builder $query, WorkflowProfession $profession): Builder => $query->forProfession($profession))
            ->when($difficulty, fn (Builder $query, WorkflowDifficulty $difficulty): Builder => $query->where('difficulty', $difficulty))
            ->when($task, fn (Builder $query, ToolCategory $task): Builder => $query->where('tool_category_id', $task->id))
            ->ordered()
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('public.workflows.index', [
            'workflows' => $workflows,
            'tasks' => ToolCategory::query()->active()
                ->whereHas('workflows', fn (Builder $query): Builder => $query->published())->ordered()->get(['id', 'name', 'slug']),
            'filters' => ['para' => $profession, 'nivel' => $difficulty, 'tarea' => $task],
        ]);
    }

    public function show(string $slug): View
    {
        $workflow = Workflow::query()->published()
            ->with(['category', 'image.model', 'seoImage', 'steps.tool.logo.model', 'steps.tool.category', 'steps.template', 'steps.image.model'])
            ->where('slug', $slug)->firstOrFail();

        return view('public.workflows.show', [
            'workflow' => $workflow,
            'tools' => $workflow->publishedTools(),
            'templates' => $workflow->publishedTemplates(),
        ]);
    }
}
