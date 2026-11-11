<?php

namespace App\Filament\Resources\Testimonials;

use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Filament\Resources\Testimonials\Pages\EditTestimonial;
use App\Filament\Resources\Testimonials\Pages\ListTestimonials;
use App\Filament\Support\ContentFields;
use App\Models\Testimonial;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static ?string $modelLabel = 'testimonio';

    protected static ?string $pluralModelLabel = 'Testimonios';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Sitio Corporativo';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(255),
            TextInput::make('position')->label('Cargo')->maxLength(255), TextInput::make('company')->label('Empresa')->maxLength(255),
            Textarea::make('quote')->label('Testimonio')->required()->maxLength(5000)->columnSpanFull(),
            ContentFields::image()->imageContext('testimonial'), ContentFields::order(), ContentFields::active(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->stackedOnMobile()->columns([
            TextColumn::make('name')->wrap()->label('Nombre')->searchable()->sortable(), TextColumn::make('company')->wrap()->label('Empresa')->searchable(), TextColumn::make('sort_order')->wrap()->label('Orden')->sortable(), IconColumn::make('is_active')->label('Activo')->boolean(),
        ])->filters([TernaryFilter::make('is_active')->label('Activo')])->recordActions([EditAction::make()])->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTestimonials::route('/'),
            'create' => CreateTestimonial::route('/create'),
            'edit' => EditTestimonial::route('/{record}/edit'),
        ];
    }
}
