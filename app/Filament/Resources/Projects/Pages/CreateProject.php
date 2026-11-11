<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return 'Crear proyecto';
    }

    public function getBreadcrumb(): string
    {
        return 'Crear';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Crear proyecto');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Cancelar');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $base = Str::limit(Str::slug($data['title']), 200, '') ?: 'proyecto';
        $slug = $base;
        $suffix = 2;
        while (Project::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        $data['slug'] = $slug;

        return $data;
    }
}
