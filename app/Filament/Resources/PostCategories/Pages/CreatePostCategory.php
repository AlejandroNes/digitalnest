<?php

namespace App\Filament\Resources\PostCategories\Pages;

use App\Filament\Resources\PostCategories\PostCategoryResource;
use App\Filament\Support\BlogEditor;
use App\Models\PostCategory;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\QueryException;

class CreatePostCategory extends CreateRecord
{
    protected static string $resource = PostCategoryResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected function authorizeAccess(): void
    {
        BlogEditor::authorize('create', PostCategory::class);
        parent::authorizeAccess();
    }

    public function create(bool $another = false): void
    {
        try {
            parent::create($another);
        } catch (QueryException $exception) {
            $this->record = null;
            $this->form->model(PostCategory::class);
            BlogEditor::rethrowWriteConflict($exception);
        }
    }
}
