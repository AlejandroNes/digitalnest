<?php

namespace App\Filament\Resources\Faqs;

use App\Filament\Resources\Faqs\Pages\CreateFaq;
use App\Filament\Resources\Faqs\Pages\EditFaq;
use App\Filament\Resources\Faqs\Pages\ListFaqs;
use App\Filament\Support\ContentFields;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static ?string $modelLabel = 'pregunta frecuente';

    protected static ?string $pluralModelLabel = 'Preguntas frecuentes';

    protected static ?string $recordTitleAttribute = 'question';

    protected static string|\UnitEnum|null $navigationGroup = 'Sitio Corporativo';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            TextInput::make('question')->label('Pregunta')->required()->maxLength(255),
            ContentFields::richText('answer', 'Respuesta'), ContentFields::order(), ContentFields::active(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->stackedOnMobile()->columns([
            TextColumn::make('question')->wrap()->label('Pregunta')->searchable()->sortable()->limit(80), TextColumn::make('sort_order')->wrap()->label('Orden')->sortable(), IconColumn::make('is_active')->label('Activo')->boolean(),
        ])->filters([TernaryFilter::make('is_active')->label('Activo')])->recordActions([EditAction::make()])->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqs::route('/'),
            'create' => CreateFaq::route('/create'),
            'edit' => EditFaq::route('/{record}/edit'),
        ];
    }
}
