<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Portfolio extends Component
{
    use WithPagination;

    #[Url(as: 'categoria', history: true)]
    public string $category = '';

    #[Locked]
    public ?int $selectedProjectId = null;

    public function mount(?string $initialProjectSlug = null): void
    {
        if (filled($initialProjectSlug)) {
            $this->selectedProjectId = Project::query()->published()->where('slug', $initialProjectSlug)->firstOrFail()->id;
        }
    }

    public function filterByCategory(string $slug): void
    {
        $this->category = $slug;
        $this->updatedCategory();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
        $this->closeProject();
    }

    public function openProject(int $id): void
    {
        abort_unless(Project::query()->published()->whereKey($id)->exists(), 404);
        $this->selectedProjectId = $id;
    }

    public function closeProject(): void
    {
        $this->selectedProjectId = null;
    }

    public function render(): View
    {
        $query = Project::query()->published()->with(['category', 'image.model'])->orderByDesc('id');
        if ($this->category !== '') {
            $query->whereHas('category', fn (Builder $query): Builder => $query->active()->where('slug', $this->category));
        }
        $categories = ProjectCategory::query()->active()
            ->whereHas('projects', fn (Builder $query): Builder => $query->published())
            ->orderBy('name')->get();
        $selectedProject = $this->selectedProjectId
            ? Project::query()->published()->with(['category', 'image.model'])->findOrFail($this->selectedProjectId)
            : null;

        return view('livewire.portfolio', [
            'projects' => $query->paginate(9),
            'categories' => $categories,
            'selectedProject' => $selectedProject,
        ]);
    }
}
