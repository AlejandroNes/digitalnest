<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Filament\Support\BlogEditor;
use App\Models\Post;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\QueryException;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected function authorizeAccess(): void
    {
        BlogEditor::authorize('create', Post::class);
        parent::authorizeAccess();
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        BlogEditor::validatePostSlug($data['slug']);

        return $data;
    }

    public function create(bool $another = false): void
    {
        try {
            parent::create($another);
        } catch (QueryException $exception) {
            $this->record = null;
            $this->form->model(Post::class);
            BlogEditor::rethrowWriteConflict($exception);
        }
    }
}
