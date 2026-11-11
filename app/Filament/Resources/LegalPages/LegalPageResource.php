<?php

namespace App\Filament\Resources\LegalPages;

use App\Filament\Resources\LegalPages\Pages\CreateLegalPage;
use App\Filament\Resources\LegalPages\Pages\EditLegalPage;
use App\Filament\Resources\LegalPages\Pages\ListLegalPages;
use App\Filament\Support\ContentFields;
use App\LegalPageType;
use App\Models\LegalPage;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class LegalPageResource extends Resource
{
    protected static ?string $model = LegalPage::class;

    protected static ?string $modelLabel = 'página legal';

    protected static ?string $pluralModelLabel = 'Páginas legales';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = 'Sitio Corporativo';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            Select::make('type')->label('Tipo')->options(LegalPageType::class)->required()->unique(ignoreRecord: true)
                ->rule(Rule::enum(LegalPageType::class))->disabledOn('edit'),
            ContentFields::richText('content', 'Contenido'), Toggle::make('is_published')->label('Publicado')->default(false)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->stackedOnMobile()->columns([
            TextColumn::make('type')->wrap()->label('Página')->badge(), IconColumn::make('is_published')->label('Publicada')->boolean(), TextColumn::make('content_updated_at')->wrap()->label('Contenido actualizado')->dateTime(), TextColumn::make('revision')->wrap()->label('Versión'),
        ])->filters([])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalPages::route('/'),
            'create' => CreateLegalPage::route('/create'),
            'edit' => EditLegalPage::route('/{record}/edit'),
        ];
    }
}
