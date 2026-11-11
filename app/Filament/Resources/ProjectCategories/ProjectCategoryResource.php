<?php

namespace App\Filament\Resources\ProjectCategories;

use App\Filament\Resources\ProjectCategories\Pages\CreateProjectCategory;
use App\Filament\Resources\ProjectCategories\Pages\EditProjectCategory;
use App\Filament\Resources\ProjectCategories\Pages\ListProjectCategories;
use App\Filament\Support\ContentFields;
use App\Models\ProjectCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
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

class ProjectCategoryResource extends Resource
{
    protected static ?string $model = ProjectCategory::class;

    protected static ?string $modelLabel = 'categoría de proyecto';

    protected static ?string $pluralModelLabel = 'Categorías de proyectos';

    protected static ?string $navigationLabel = 'Categorías de proyectos';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Portafolio';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detalles de la categoría')
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        ContentFields::title('name', 'Nombre de la categoría'),
                        ContentFields::slug(),
                    ]),
                    Textarea::make('description')
                        ->label('Descripción')
                        ->maxLength(1000)
                        ->rows(3)
                        ->placeholder('Descripción opcional sobre esta categoría de proyectos.')
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label('Categoría activa')
                        ->default(true)
                        ->required()
                        ->helperText('Determina si la categoría está disponible para clasificar proyectos.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->color('gray'),
                TextColumn::make('projects_count')
                    ->label('Proyectos vinculados')
                    ->counts('projects')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedNoSymbol)
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Disponibilidad')
                    ->placeholder('Todas')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No hay categorías de proyectos')
            ->emptyStateDescription('Crea categorías para organizar y clasificar tus proyectos.')
            ->emptyStateIcon(Heroicon::OutlinedFolder)
            ->defaultSort('name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjectCategories::route('/'),
            'create' => CreateProjectCategory::route('/create'),
            'edit' => EditProjectCategory::route('/{record}/edit'),
        ];
    }
}
