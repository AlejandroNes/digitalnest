<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    public function getTitle(): string
    {
        return 'Editar proyecto';
    }

    public function getBreadcrumb(): string
    {
        return 'Editar';
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Guardar cambios');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Cancelar');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['description'] = $this->record->portfolioDescription();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['description'] ?? '') === $this->record->portfolioDescription()) {
            unset($data['description']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Eliminar proyecto'),
        ];
    }
}
