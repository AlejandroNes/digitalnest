<?php

namespace App\Filament\Resources\Tools;

use App\Filament\Forms\Components\MediaPicker;
use App\Filament\Resources\Tools\Pages\CreateTool;
use App\Filament\Resources\Tools\Pages\EditTool;
use App\Filament\Resources\Tools\Pages\ListTools;
use App\Filament\Support\ContentFields;
use App\Filament\Support\SeoFields;
use App\Models\Tool;
use App\ToolPlatform;
use App\ToolPricingModel;
use App\ToolSpanishSupport;
use App\ToolStatus;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class ToolResource extends Resource
{
    protected static ?string $model = Tool::class;

    protected static ?string $modelLabel = 'herramienta';

    protected static ?string $pluralModelLabel = 'Herramientas';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Herramientas';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Group::make()->columnSpan(['default' => 1, 'lg' => 2])->schema([
                    Section::make('Identidad')->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            ContentFields::title('name', 'Nombre'),
                            ContentFields::slug(),
                        ]),
                        TextInput::make('tagline')->label('Veredicto en una frase')->required()->maxLength(160)
                            ->helperText('Se muestra en las tarjetas y como descripción por defecto.'),
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextInput::make('website_url')->label('Web oficial')->url()->rules(['url:http,https'])
                                ->required()->maxLength(2048),
                            TextInput::make('affiliate_url')->label('Enlace de afiliado')->url()->rules(['url:http,https'])
                                ->maxLength(2048)->helperText('Opcional. Si existe, el botón "Visitar" lo usa y se marca como patrocinado.'),
                        ]),
                    ]),
                    Section::make('Ficha')->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Textarea::make('best_for')->label('Para quién es')->rows(3)->maxLength(2000)
                                ->required(fn (Get $get): bool => self::isPublishing($get)),
                            Textarea::make('not_for')->label('Para quién no es')->rows(3)->maxLength(2000),
                        ]),
                        ContentFields::richText('review', 'Prueba y análisis')
                            ->required(fn (Get $get): bool => self::isPublishing($get))
                            ->helperText('Cuenta qué probaste, cómo y qué resultado obtuviste.'),
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Repeater::make('pros')->label('Ventajas')->simple(TextInput::make('text')->required()->maxLength(255))
                                ->maxItems(10)->defaultItems(0)->reorderable(),
                            Repeater::make('cons')->label('Desventajas')->simple(TextInput::make('text')->required()->maxLength(255))
                                ->maxItems(10)->defaultItems(0)->reorderable(),
                        ]),
                    ]),
                    Section::make('Precio y disponibilidad')->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Select::make('pricing_model')->label('Modelo de precio')->options(ToolPricingModel::class)
                                ->required()->rule(Rule::enum(ToolPricingModel::class)),
                            TextInput::make('starting_price_usd')->label('Precio desde (USD/mes)')->numeric()
                                ->minValue(0)->maxValue(99999)->prefix('$'),
                            Toggle::make('has_free_plan')->label('Tiene plan gratis usable')->default(false),
                            Toggle::make('requires_card')->label('Pide tarjeta para probarla')->default(false),
                        ]),
                        TextInput::make('free_plan_notes')->label('Límites del plan gratis')->maxLength(255)
                            ->placeholder('10 videos al mes con marca de agua'),
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Select::make('spanish_support')->label('Español')->options(ToolSpanishSupport::class)
                                ->required()->rule(Rule::enum(ToolSpanishSupport::class)),
                            Select::make('latam_payments')->label('Pagos desde Latinoamérica')
                                ->options([1 => 'Sí', 0 => 'No'])->placeholder('Sin verificar'),
                        ]),
                        CheckboxList::make('platforms')->label('Plataformas')->options(ToolPlatform::class)->columns(4),
                        Textarea::make('privacy_notes')->label('Privacidad y datos')->rows(3)->maxLength(2000)
                            ->helperText('¿Entrena con tus datos? ¿Dónde los guarda?'),
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextInput::make('deal_notes')->label('Oferta actual')->maxLength(255),
                            DatePicker::make('deal_expires_at')->label('La oferta caduca el'),
                        ]),
                    ]),
                    SeoFields::section(),
                ]),
                Group::make()->columnSpan(['default' => 1, 'lg' => 1])->schema([
                    Section::make('Publicación')->schema([
                        Select::make('status')->label('Estado')->options(ToolStatus::class)->live()
                            ->default(ToolStatus::Draft->value)->required()->rule(Rule::enum(ToolStatus::class)),
                        DateTimePicker::make('published_at')->label('Fecha de publicación')
                            ->helperText('Vacío: se publica al guardar.'),
                        DatePicker::make('tested_at')->label('Probada el')->maxDate('today')
                            ->required(fn (Get $get): bool => self::isPublishing($get)),
                        DatePicker::make('reviewed_at')->label('Revisada el')->maxDate('today'),
                        TextInput::make('rating')->label('Nota (1–10)')->numeric()->integer()->minValue(1)->maxValue(10),
                        Toggle::make('is_featured')->label('Recomendada')->default(false),
                        ContentFields::order(),
                    ]),
                    Section::make('Clasificación')->schema([
                        Select::make('tool_category_id')->label('Categoría principal')->relationship('category', 'name')
                            ->searchable()->preload()->required()->rules(['integer', 'exists:tool_categories,id']),
                        Select::make('secondaryCategories')->label('Categorías secundarias')->multiple()
                            ->relationship('secondaryCategories', 'name')->preload(),
                        Select::make('alternatives')->label('Alternativas')->multiple()->searchable()->preload()
                            ->relationship('alternatives', 'name', fn (Builder $query, ?Tool $record): Builder => $query
                                ->when($record, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()))),
                    ]),
                    Section::make('Imágenes')->schema([
                        MediaPicker::make('logo_id')->label('Logo')->imageContext('logo'),
                        MediaPicker::make('image_id')->label('Captura principal')->imageContext('content'),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['category', 'logo.model']))
            ->stackedOnMobile()
            ->columns([
                ImageColumn::make('logo_url')->label('Logo')
                    ->state(fn (Tool $record): ?string => $record->logoUrl())
                    ->square()->size(40)->checkFileExistence(false),
                TextColumn::make('name')->label('Nombre')->searchable()->sortable()->weight('medium')
                    ->description(fn (Tool $record): string => $record->tagline),
                TextColumn::make('category.name')->label('Categoría')->badge()->color('gray')->sortable(),
                TextColumn::make('pricing_model')->label('Precio')->badge(),
                IconColumn::make('is_featured')->label('Recomendada')->boolean(),
                TextColumn::make('status')->label('Estado')->badge()->sortable(),
                TextColumn::make('tested_at')->label('Probada')->date('d/m/Y')->sortable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('tool_category_id')->label('Categoría')->relationship('category', 'name'),
                SelectFilter::make('status')->label('Estado')->options(ToolStatus::class),
                SelectFilter::make('pricing_model')->label('Precio')->options(ToolPricingModel::class),
                TernaryFilter::make('is_featured')->label('Recomendada'),
            ])
            ->recordActions([EditAction::make()->label('Editar'), DeleteAction::make()->label('Eliminar')])
            ->emptyStateHeading('No hay herramientas')
            ->emptyStateDescription('Crea tu primera ficha de herramienta probada.')
            ->emptyStateIcon(Heroicon::OutlinedWrenchScrewdriver)
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTools::route('/'),
            'create' => CreateTool::route('/create'),
            'edit' => EditTool::route('/{record}/edit'),
        ];
    }

    private static function isPublishing(Get $get): bool
    {
        $status = $get('status');

        return $status === ToolStatus::Published || $status === ToolStatus::Published->value;
    }
}
