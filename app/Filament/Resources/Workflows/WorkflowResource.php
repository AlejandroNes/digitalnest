<?php

namespace App\Filament\Resources\Workflows;

use App\Filament\Forms\Components\MediaPicker;
use App\Filament\Resources\Workflows\Pages\CreateWorkflow;
use App\Filament\Resources\Workflows\Pages\EditWorkflow;
use App\Filament\Resources\Workflows\Pages\ListWorkflows;
use App\Filament\Support\ContentFields;
use App\Filament\Support\SeoFields;
use App\Models\Workflow;
use App\PublicationStatus;
use App\WorkflowDifficulty;
use App\WorkflowProfession;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class WorkflowResource extends Resource
{
    protected static ?string $model = Workflow::class;

    protected static ?string $modelLabel = 'flujo de trabajo';

    protected static ?string $pluralModelLabel = 'Flujos de trabajo';

    protected static ?string $navigationLabel = 'Flujos';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = 'Flujos y plantillas';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Group::make()->columnSpan(['default' => 1, 'lg' => 2])->schema([
                    Section::make('Flujo')->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            ContentFields::title('title', 'Título'),
                            ContentFields::slug(),
                        ]),
                        TextInput::make('summary')->label('Resumen en una frase')->required()->maxLength(200),
                        Textarea::make('goal')->label('Qué consigues al terminar')->required()->rows(2)->maxLength(1000),
                        ContentFields::richText('intro', 'Antes de empezar')->required(false)
                            ->helperText('Cuándo usar este flujo y qué necesitas tener listo.'),
                    ]),
                    Section::make('Pasos')->schema([
                        Repeater::make('steps')->label('')->relationship()->orderColumn('position')
                            ->schema([
                                TextInput::make('title')->label('Título del paso')->required()->maxLength(255),
                                ContentFields::richText('body', 'Instrucciones')->required(false),
                                Grid::make(['default' => 1, 'md' => 2])->schema([
                                    Select::make('tool_id')->label('Herramienta usada')->relationship('tool', 'name')
                                        ->searchable()->preload(),
                                    Select::make('template_id')->label('Plantilla usada')->relationship('template', 'title')
                                        ->searchable()->preload(),
                                ]),
                                MediaPicker::make('image_id')->label('Captura del paso')->imageContext('content'),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->collapsible()->cloneable()->defaultItems(1)->minItems(1)->maxItems(30)
                            ->addActionLabel('Añadir paso'),
                    ]),
                    Section::make('Resultado')->schema([
                        ContentFields::richText('result', 'Qué salió en nuestra prueba')
                            ->required(fn (Get $get): bool => self::isPublishing($get)),
                    ]),
                    SeoFields::section(),
                ]),
                Group::make()->columnSpan(['default' => 1, 'lg' => 1])->schema([
                    Section::make('Publicación')->schema([
                        Select::make('status')->label('Estado')->options(PublicationStatus::class)->live()
                            ->default(PublicationStatus::Draft->value)->required()->rule(Rule::enum(PublicationStatus::class)),
                        DateTimePicker::make('published_at')->label('Fecha de publicación')->helperText('Vacío: se publica al guardar.'),
                        DatePicker::make('tested_at')->label('Probado el')->maxDate('today')
                            ->required(fn (Get $get): bool => self::isPublishing($get)),
                        Toggle::make('is_featured')->label('Recomendado')->default(false),
                        ContentFields::order(),
                    ]),
                    Section::make('Datos del flujo')->schema([
                        Select::make('tool_category_id')->label('Tarea')->relationship('category', 'name')
                            ->searchable()->preload()->required()->rules(['integer', 'exists:tool_categories,id']),
                        CheckboxList::make('professions')->label('Para quién es')->options(WorkflowProfession::class)
                            ->required()->columns(2),
                        Select::make('difficulty')->label('Dificultad')->options(WorkflowDifficulty::class)
                            ->required()->rule(Rule::enum(WorkflowDifficulty::class)),
                        TextInput::make('time_minutes')->label('Tiempo (minutos)')->numeric()->integer()
                            ->minValue(1)->maxValue(6000)->required(),
                        TextInput::make('cost_usd')->label('Coste (USD/mes)')->numeric()->minValue(0)->maxValue(99999)
                            ->prefix('$')->helperText('0 si todo es gratis; vacío si no se ha calculado.'),
                        MediaPicker::make('image_id')->label('Imagen de portada o resultado')->imageContext('content'),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category')->withCount('steps'))
            ->stackedOnMobile()
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->sortable()->wrap()->weight('medium')
                    ->description(fn (Workflow $record): string => $record->summary),
                TextColumn::make('category.name')->label('Tarea')->badge()->color('gray'),
                TextColumn::make('difficulty')->label('Dificultad')->badge(),
                TextColumn::make('steps_count')->label('Pasos')->sortable(),
                IconColumn::make('is_featured')->label('Recomendado')->boolean(),
                TextColumn::make('status')->label('Estado')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('tool_category_id')->label('Tarea')->relationship('category', 'name'),
                SelectFilter::make('status')->label('Estado')->options(PublicationStatus::class),
                SelectFilter::make('difficulty')->label('Dificultad')->options(WorkflowDifficulty::class),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No hay flujos de trabajo')
            ->emptyStateDescription('Crea tu primer caso paso a paso.')
            ->emptyStateIcon(Heroicon::OutlinedQueueList)
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkflows::route('/'),
            'create' => CreateWorkflow::route('/create'),
            'edit' => EditWorkflow::route('/{record}/edit'),
        ];
    }

    private static function isPublishing(Get $get): bool
    {
        $status = $get('status');

        return $status === PublicationStatus::Published || $status === PublicationStatus::Published->value;
    }
}
