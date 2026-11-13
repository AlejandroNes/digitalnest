<?php

namespace App\Filament\Resources\Rankings\Pages;

use App\Filament\Resources\Rankings\RankingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRankings extends ListRecords
{
    protected static string $resource = RankingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo ranking'),
        ];
    }
}
