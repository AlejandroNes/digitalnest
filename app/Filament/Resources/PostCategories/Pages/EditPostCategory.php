<?php

namespace App\Filament\Resources\PostCategories\Pages;

use App\Filament\Resources\PostCategories\PostCategoryResource;
use App\Filament\Support\BlogEditor;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Locked;

class EditPostCategory extends EditRecord
{
    protected static string $resource = PostCategoryResource::class;

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

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->databaseTransaction()->using(fn (Model $record): bool => BlogEditor::delete($record, $this->editingRevision)),
        ];
    }
}
