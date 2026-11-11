<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Support\MediaUploadAction;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Locked;

class ListMediaAssets extends ListRecords
{
    protected static string $resource = MediaAssetResource::class;

    protected string $view = 'filament.media.library';

    /** @var list<array{filename: string, status: string, errors: list<string>, asset_id: int|null}> */
    #[Locked]
    public array $mediaUploadResults = [];

    public function getSubheading(): ?string
    {
        return 'Organiza tus imágenes y reutilízalas en el contenido del sitio.';
    }

    protected function getHeaderActions(): array
    {
        return [
            MediaUploadAction::make(),
        ];
    }
}
