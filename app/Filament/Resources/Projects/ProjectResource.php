<?php

namespace App\Filament\Resources\Projects;

use App\Filament\Forms\Components\MediaPicker;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Models\Project;
use App\Models\User;
use App\ProjectStatus;
use App\Rules\MediaSelection;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $modelLabel = 'proyecto';

    protected static ?string $pluralModelLabel = 'Proyectos';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = 'Portafolio';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Group::make()
                    ->columnSpan(['default' => 1, 'lg' => 2])
                    ->schema([
                        Section::make('Información del proyecto')
                            ->schema([
                                TextInput::make('title')->label('Título')->required()->maxLength(255),
                                MediaPicker::make('image_id')->label('Imagen principal')->imageContext('portfolio')->required(),
                                Textarea::make('description')->label('Descripción')->rows(6)->maxLength(50000)
                                    ->helperText('Texto opcional que se mostrará al abrir el proyecto en el sitio web.'),
                            ]),
                    ]),
                Group::make()
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
                        Section::make('Publicación y Categoría')
                            ->schema([
                                Select::make('status')->label('Estado')->options(ProjectStatus::class)
                                    ->default(ProjectStatus::Draft->value)->required()->rule(Rule::enum(ProjectStatus::class)),
                                Select::make('project_category_id')
                                    ->label('Categoría')->relationship('category', 'name')
                                    ->searchable()->preload()->required()->placeholder('Selecciona una categoría')
                                    ->rules(['integer', 'exists:project_categories,id']),
                            ]),
                        Section::make('Detalles adicionales')
                            ->schema([
                                TextInput::make('client')->label('Cliente / Empresa')->maxLength(255),
                                DatePicker::make('completed_at')->label('Fecha de realización')->maxDate('now'),
                                TextInput::make('external_url')->label('Enlace externo')->url()->rules(['url:http,https'])
                                    ->maxLength(2048)->placeholder('https://ejemplo.com'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['category', 'image.model']))
            ->stackedOnMobile()
            ->columns([
                ImageColumn::make('image_url')->label('Imagen')
                    ->state(fn (Project $record): ?string => $record->imageUrl())
                    ->square()->size(56)->checkFileExistence(false),
                TextColumn::make('title')->label('Título')->searchable()->sortable()->wrap()->weight('medium'),
                TextColumn::make('category.name')->label('Categoría')->badge()->color('gray')
                    ->placeholder('Sin categoría')->sortable(),
                TextColumn::make('client')->label('Cliente / Empresa')->searchable()->wrap()->placeholder('—'),
                SelectColumn::make('status')->label('Estado')->options(ProjectStatus::class)
                    ->rules(['required', Rule::enum(ProjectStatus::class)])
                    ->disabled(fn (Project $record): bool => ! self::canUpdateProject($record))
                    ->updateStateUsing(function (Project $record, string $state): string {
                        $user = self::currentUser();
                        abort_unless($user && Gate::forUser($user)->allows('update', $record), 403);
                        if ($state === ProjectStatus::Published->value) {
                            $validator = Validator::make($record->getAttributes(), [
                                'title' => ['required', 'string', 'max:255'],
                                'image_id' => ['required', 'integer', new MediaSelection('portfolio')],
                                'project_category_id' => ['required', 'integer', 'exists:project_categories,id'],
                            ]);
                            if ($validator->fails()) {
                                Notification::make()->title('No se pudo publicar el proyecto')
                                    ->body('Edita el proyecto y comprueba su título, categoría e imagen antes de publicarlo.')
                                    ->danger()->send();

                                return $record->status->value;
                            }
                        }
                        $record->update(['status' => $state]);
                        Notification::make()->title($state === ProjectStatus::Published->value ? 'Proyecto publicado' : 'Proyecto en borrador')
                            ->success()->send();

                        return $state;
                    }),
            ])
            ->filters([
                SelectFilter::make('project_category_id')->label('Categoría')->relationship('category', 'name'),
                SelectFilter::make('status')->label('Estado')->options(ProjectStatus::class),
            ])
            ->recordActions([EditAction::make()->label('Editar'), DeleteAction::make()->label('Eliminar')])
            ->emptyStateHeading('No hay proyectos registrados')
            ->emptyStateDescription('Añade una imagen y los datos de tu primer trabajo.')
            ->emptyStateIcon(Heroicon::OutlinedSquares2x2)
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }

    private static function canUpdateProject(Project $record): bool
    {
        $user = self::currentUser();

        return $user && Gate::forUser($user)->allows('update', $record);
    }

    private static function currentUser(): ?User
    {
        return once(fn (): ?User => auth()->id() ? User::query()->find(auth()->id()) : null);
    }
}
