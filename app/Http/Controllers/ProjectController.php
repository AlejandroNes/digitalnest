<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'categoria' => ['nullable', 'string', 'max:255'],
            'proyecto' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return view('public.projects.index', ['initialProjectSlug' => $data['proyecto'] ?? null]);
    }

    public function show(string $slug): RedirectResponse
    {
        $project = Project::query()->published()->where('slug', $slug)->firstOrFail();

        return redirect()->route('projects.index', ['proyecto' => $project->slug], 301);
    }
}
