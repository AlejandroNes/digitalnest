<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Filament\Support\BlogEditor;
use App\Models\Post;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Locked;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    #[Locked]
    public string $editingRevision = '';

    protected function afterFill(): void
    {
        $this->editingRevision = BlogEditor::revision($this->getRecord());
    }

    protected function beforeValidate(): void
    {
        $this->record = BlogEditor::lockForEditing($this->getRecord(), $this->editingRevision);
        $this->form->model($this->record);
    }

    protected function afterSave(): void
    {
        $this->record->refresh();
        $this->editingRevision = BlogEditor::revision($this->getRecord());
    }

    protected function authorizeAccess(): void
    {
        BlogEditor::authorize('update', $this->getRecord());
        parent::authorizeAccess();
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        try {
            parent::save($shouldRedirect, $shouldSendSavedNotification);
        } catch (QueryException $exception) {
            $this->record = $this->getRecord()->fresh();
            BlogEditor::rethrowWriteConflict($exception);
        }
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $oldSlug = $record->slug;
        $wasPublic = Post::query()->published()->whereKey($record->getKey())->exists();
        if (($data['slug'] ?? $oldSlug) !== $oldSlug) {
            BlogEditor::validatePostSlug($data['slug']);
        }
        $record->update($data);
        if ($wasPublic && $oldSlug !== $record->slug) {
            BlogEditor::preservePostUrl($record, $oldSlug);
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->databaseTransaction()->using(fn (Model $record): bool => BlogEditor::delete($record, $this->editingRevision)),
        ];
    }
}
