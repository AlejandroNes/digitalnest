<?php

namespace App\Filament\Resources\ToolCategories\Pages;

use App\Filament\Resources\ToolCategories\ToolCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditToolCategory extends EditRecord
{
    protected static string $resource = ToolCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
