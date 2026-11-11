<?php

namespace App\Filament\Resources\Tools\Pages;

use App\Filament\Resources\Tools\ToolResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTool extends EditRecord
{
    protected static string $resource = ToolResource::class;

    public function getTitle(): string
    {
        return 'Editar herramienta';
    }

    public function getBreadcrumb(): string
    {
        return 'Editar';
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Guardar cambios');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Eliminar herramienta'),
        ];
    }
}
