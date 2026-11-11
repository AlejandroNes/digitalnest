<?php

namespace App\Filament\Resources\Services;

use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Support\ContentFields;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $modelLabel = 'servicio';

    protected static ?string $pluralModelLabel = 'Servicios';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Sitio Corporativo';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            ContentFields::title('name', 'Nombre'), ContentFields::slug(),
            Textarea::make('summary')->label('Resumen')->maxLength(2000)->columnSpanFull(),
            ContentFields::richText('description', 'Descripción'), ContentFields::image()->imageContext('service'), ContentFields::order(), ContentFields::active(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->stackedOnMobile()->columns([
            TextColumn::make('name')->wrap()->label('Nombre')->searchable()->sortable(), TextColumn::make('sort_order')->wrap()->label('Orden')->sortable(), IconColumn::make('is_active')->label('Activo')->boolean(),
        ])->filters([TernaryFilter::make('is_active')->label('Activo')])->recordActions([EditAction::make()])->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
