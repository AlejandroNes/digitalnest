<?php

namespace App\Filament\Resources\ToolCategories;

use App\Filament\Resources\ToolCategories\Pages\CreateToolCategory;
use App\Filament\Resources\ToolCategories\Pages\EditToolCategory;
use App\Filament\Resources\ToolCategories\Pages\ListToolCategories;
use App\Filament\Support\ContentFields;
use App\Models\ToolCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ToolCategoryResource extends Resource
{
    protected static ?string $model = ToolCategory::class;

    protected static ?string $modelLabel = 'categoría de herramientas';

    protected static ?string $pluralModelLabel = 'Categorías de herramientas';

    protected static ?string $navigationLabel = 'Categorías';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Herramientas';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detalles de la categoría')
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        ContentFields::title('name', 'Nombre'),
                        ContentFields::slug(),
                    ]),
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        Select::make('parent_id')->label('Sector padre')
                            ->relationship('parent', 'name', fn ($query, ?ToolCategory $record) => $query->whereNull('parent_id')->when($record, fn ($query) => $query->whereKeyNot($record->getKey())))
                            ->searchable()->preload()->placeholder('Ninguno (sector principal)'),
                        TextInput::make('icon')->label('Icono')->maxLength(64)->placeholder('sparkles')
                            ->helperText('Nombre de Heroicon, sin prefijo.'),
                        ColorPicker::make('accent_color')->label('Color de acento')->regex('/^#[0-9A-Fa-f]{6}$/'),
                    ]),
                    Textarea::make('description')->label('Introducción')->rows(4)->maxLength(2000)
                        ->helperText('Se muestra al inicio de la página de la categoría. Explica qué tareas resuelven estas herramientas.')
                        ->columnSpanFull(),
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        ContentFields::order(),
                        ContentFields::active(),
                    ]),
                ]),
            Section::make('SEO')->collapsible()->schema([
                TextInput::make('seo_title')->label('Título SEO')->maxLength(255),
                Textarea::make('seo_description')->label('Descripción SEO')->maxLength(320),
                Toggle::make('seo_noindex')->label('Excluir de indexación')->default(false)->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable()->wrap(),
                TextColumn::make('parent.name')->label('Sector padre')->placeholder('—')->sortable(),
                TextColumn::make('slug')->label('Slug')->searchable()->color('gray'),
                TextColumn::make('tools_count')->label('Herramientas')->counts('tools')->badge()->color('info')->sortable(),
                TextColumn::make('sort_order')->label('Orden')->sortable(),
                IconColumn::make('is_active')->label('Activa')->boolean()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Disponibilidad')->placeholder('Todas')
                    ->trueLabel('Activas')->falseLabel('Inactivas'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No hay categorías de herramientas')
            ->emptyStateDescription('Crea categorías por tarea: escribir, imagen, video, automatización…')
            ->emptyStateIcon(Heroicon::OutlinedFolder)
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListToolCategories::route('/'),
            'create' => CreateToolCategory::route('/create'),
            'edit' => EditToolCategory::route('/{record}/edit'),
        ];
    }
}
