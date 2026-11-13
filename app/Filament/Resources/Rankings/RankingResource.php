<?php

namespace App\Filament\Resources\Rankings;

use App\Filament\Resources\Rankings\Pages\CreateRanking;
use App\Filament\Resources\Rankings\Pages\EditRanking;
use App\Filament\Resources\Rankings\Pages\ListRankings;
use App\Filament\Resources\Rankings\RelationManagers\ToolsRelationManager;
use App\Filament\Support\ContentFields;
use App\Models\Ranking;
use App\PublicationStatus;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class RankingResource extends Resource
{
    protected static ?string $model = Ranking::class;

    protected static ?string $modelLabel = 'ranking';

    protected static ?string $pluralModelLabel = 'Rankings';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = 'Ofertas';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Group::make()->columnSpan(['default' => 1, 'lg' => 2])->schema([
                    Section::make('Ranking')->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            ContentFields::title('title', 'Título'),
                            ContentFields::slug(),
                        ]),
                        Select::make('tool_category_id')->label('Sector')->relationship('category', 'name')
                            ->searchable()->preload(),
                        Textarea::make('intro')->label('Introducción')->rows(5)->maxLength(5000),
                    ]),
                    Section::make('SEO')->collapsible()->collapsed()->schema([
                        TextInput::make('seo_title')->label('Título SEO')->maxLength(255),
                        Textarea::make('seo_description')->label('Descripción SEO')->maxLength(320),
                        Toggle::make('seo_noindex')->label('Excluir de indexación')->default(false)->required(),
                    ]),
                ]),
                Group::make()->columnSpan(['default' => 1, 'lg' => 1])->schema([
                    Section::make('Publicación')->schema([
                        Select::make('status')->label('Estado')->options(PublicationStatus::class)
                            ->default(PublicationStatus::Draft->value)->required()->rule(Rule::enum(PublicationStatus::class)),
                        DateTimePicker::make('published_at')->label('Fecha de publicación')->helperText('Vacío: se publica al guardar.'),
                        Toggle::make('is_featured')->label('Destacado')->default(false),
                        ContentFields::order(),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->sortable()->wrap()->weight('medium'),
                TextColumn::make('category.name')->label('Sector')->placeholder('—'),
                TextColumn::make('tools_count')->label('Apps')->counts('tools')->badge()->color('info'),
                IconColumn::make('is_featured')->label('Destacado')->boolean(),
                TextColumn::make('status')->label('Estado')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(PublicationStatus::class),
                SelectFilter::make('category')->label('Sector')->relationship('category', 'name')->preload(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No hay rankings')
            ->emptyStateDescription('Crea listas como «Las mejores IA para vídeo».')
            ->emptyStateIcon(Heroicon::OutlinedTrophy)
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [ToolsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRankings::route('/'),
            'create' => CreateRanking::route('/create'),
            'edit' => EditRanking::route('/{record}/edit'),
        ];
    }
}
