<?php

namespace App\Filament\Resources\Templates;

use App\Filament\Resources\Templates\Pages\CreateTemplate;
use App\Filament\Resources\Templates\Pages\EditTemplate;
use App\Filament\Resources\Templates\Pages\ListTemplates;
use App\Filament\Support\ContentFields;
use App\Filament\Support\SeoFields;
use App\Models\Template;
use App\PublicationStatus;
use App\TemplateFormat;
use App\TemplateType;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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

class TemplateResource extends Resource
{
    protected static ?string $model = Template::class;

    protected static ?string $modelLabel = 'plantilla';

    protected static ?string $pluralModelLabel = 'Plantillas';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = 'Flujos y plantillas';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Group::make()->columnSpan(['default' => 1, 'lg' => 2])->schema([
                    Section::make('Plantilla')->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            ContentFields::title('title', 'Título'),
                            ContentFields::slug(),
                        ]),
                        TextInput::make('summary')->label('Resumen en una frase')->required()->maxLength(200),
                        Textarea::make('content')->label('Contenido para copiar')->required()->rows(14)->maxLength(100000)
                            ->extraInputAttributes(['style' => 'font-family: ui-monospace, monospace; font-size: .9rem'])
                            ->helperText('Se muestra y se descarga tal cual, sin formato.')
                            ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                $format = $get('format');
                                if (($format === TemplateFormat::Json || $format === TemplateFormat::Json->value) && json_validate((string) $value) === false) {
                                    $fail('El contenido no es un JSON válido.');
                                }
                            }]),
                        Repeater::make('variables')->label('Huecos a rellenar')
                            ->simple(TextInput::make('text')->required()->maxLength(80)->placeholder('[tu nicho]'))
                            ->defaultItems(0)->maxItems(20),
                    ]),
                    Section::make('Uso')->schema([
                        ContentFields::richText('usage', 'Cómo usarla')->required(false),
                        Textarea::make('example_output')->label('Ejemplo de resultado')->rows(6)->maxLength(20000),
                    ]),
                    SeoFields::section(),
                ]),
                Group::make()->columnSpan(['default' => 1, 'lg' => 1])->schema([
                    Section::make('Publicación')->schema([
                        Select::make('status')->label('Estado')->options(PublicationStatus::class)
                            ->default(PublicationStatus::Draft->value)->required()->rule(Rule::enum(PublicationStatus::class)),
                        DateTimePicker::make('published_at')->label('Fecha de publicación')->helperText('Vacío: se publica al guardar.'),
                        DatePicker::make('tested_at')->label('Probada el')->maxDate('today'),
                        Toggle::make('is_featured')->label('Recomendada')->default(false),
                        ContentFields::order(),
                    ]),
                    Section::make('Clasificación')->schema([
                        Select::make('type')->label('Tipo')->options(TemplateType::class)->required()->rule(Rule::enum(TemplateType::class)),
                        Select::make('format')->label('Formato')->options(TemplateFormat::class)->live()
                            ->default(TemplateFormat::Text->value)->required()->rule(Rule::enum(TemplateFormat::class)),
                        Select::make('tools')->label('Funciona con')->multiple()->relationship('tools', 'name')
                            ->searchable()->preload(),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->sortable()->wrap()->weight('medium')
                    ->description(fn (Template $record): string => $record->summary),
                TextColumn::make('type')->label('Tipo')->badge(),
                TextColumn::make('format')->label('Formato')->badge()->color('gray'),
                IconColumn::make('is_featured')->label('Recomendada')->boolean(),
                TextColumn::make('status')->label('Estado')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tipo')->options(TemplateType::class),
                SelectFilter::make('status')->label('Estado')->options(PublicationStatus::class),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No hay plantillas')
            ->emptyStateDescription('Crea tu primer prompt o automatización lista para copiar.')
            ->emptyStateIcon(Heroicon::OutlinedDocumentDuplicate)
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTemplates::route('/'),
            'create' => CreateTemplate::route('/create'),
            'edit' => EditTemplate::route('/{record}/edit'),
        ];
    }
}
