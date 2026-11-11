<?php

namespace App\Livewire;

use App\Models\CustomScript;
use App\Models\SearchSetting;
use App\Models\User;
use App\Rules\ScriptMarkup;
use Closure;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithoutUrlPagination;

class CustomScriptManager extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions, InteractsWithSchemas, InteractsWithTable, RestrictsFileUploadsToSchemaComponents, WithoutUrlPagination;

    public function mount(): void
    {
        $this->authorizeManager();
    }

    private function authorizeManager(): void
    {
        $user = auth()->id() ? User::query()->find(auth()->id()) : null;
        abort_unless($user && SearchSetting::supportsIntegrations(), 403);
        Gate::forUser($user)->authorize('viewAny', CustomScript::class);
    }

    private function authorizeMutation(string $ability, CustomScript|string $record): void
    {
        $user = auth()->id() ? User::query()->find(auth()->id()) : null;
        abort_unless($user, 403);
        Gate::forUser($user)->authorize($ability, $record);
    }

    /** @return array<\Filament\Schemas\Components\Component> */
    private function fields(): array
    {
        return [
            TextInput::make('name')->label('Nombre')->required()->maxLength(255),
            Select::make('position')->label('Ubicación')->options(CustomScript::POSITIONS)->default('head')
                ->required()->rule(Rule::in(array_keys(CustomScript::POSITIONS)))->live(),
            Select::make('purpose')->label('Finalidad')->options(CustomScript::PURPOSES)->default('statistics')
                ->required()->rule(Rule::in(array_keys(CustomScript::PURPOSES)))->live()
                ->helperText('Analítica, publicidad y widgets se cargan únicamente tras aceptar esa finalidad. Verificación solo admite metaetiquetas sin seguimiento.'),
            Textarea::make('code')->label('Código HTML')->required()->rows(12)->maxLength(ScriptMarkup::MAX_BYTES)
                ->extraInputAttributes(['class' => 'font-mono', 'spellcheck' => 'false', 'autocapitalize' => 'off'])
                ->helperText('Pega el fragmento con sus etiquetas. No incluyas PHP, contraseñas ni claves privadas. Se respetará la CSP configurada en el servidor.')
                ->rules(fn (Get $get, ?CustomScript $record): array => [
                    new ScriptMarkup($get('purpose') ?? '', $get('position') ?? ''),
                    function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        if (is_string($value) && CustomScript::query()->where('code_hash', hash('sha256', trim($value)))
                            ->when($record, fn ($query) => $query->whereKeyNot($record->id))->exists()) {
                            $fail('Este fragmento ya existe. Edita la integración original para evitar duplicados.');
                        }
                    },
                ]),
            Toggle::make('is_active')->label('Activo')->default(false)->required(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table->query(CustomScript::query()->with(['updatedBy', 'createdBy']))->modelLabel('fragmento')->pluralModelLabel('fragmentos')
            ->stackedOnMobile()->defaultSort('id', 'desc')->paginationPageOptions([10, 25])
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->wrap()->weight('medium'),
                TextColumn::make('position')->label('Ubicación')->formatStateUsing(fn (string $state): string => CustomScript::POSITIONS[$state] ?? $state),
                TextColumn::make('purpose')->label('Finalidad')->badge()->formatStateUsing(fn (string $state): string => CustomScript::PURPOSES[$state] ?? $state),
                TextColumn::make('is_active')->label('Estado')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Activo' : 'Inactivo')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('updatedBy.name')->label('Modificado por')->placeholder('Sin registro'),
                TextColumn::make('updated_at')->label('Último cambio')->dateTime('d/m/Y H:i')->sortable(),
            ])->headerActions([
                CreateAction::make()->label('Añadir fragmento')->modalHeading('Añadir código personalizado')->createAnother(false)
                    ->modalWidth(Width::ThreeExtraLarge)->modalSubmitActionLabel('Guardar fragmento')->modalCancelActionLabel('Cancelar')
                    ->schema($this->fields())->authorize(fn (): bool => auth()->user()?->can('create', CustomScript::class) ?? false)
                    ->using(function (array $data): CustomScript {
                        $this->authorizeMutation('create', CustomScript::class);

                        return CustomScript::query()->create($data);
                    }),
            ])->recordActions([
                EditAction::make()->label('Editar')->modalHeading('Editar código personalizado')->modalWidth(Width::ThreeExtraLarge)
                    ->modalSubmitActionLabel('Guardar cambios')->modalCancelActionLabel('Cancelar')->schema($this->fields())
                    ->authorize(fn (CustomScript $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->using(function (CustomScript $record, array $data): CustomScript {
                        $this->authorizeMutation('update', $record);
                        $record->update($data);

                        return $record;
                    }),
                DeleteAction::make()->label('Eliminar')->modalHeading('Eliminar fragmento')->modalSubmitActionLabel('Eliminar')->modalCancelActionLabel('Cancelar')
                    ->authorize(fn (CustomScript $record): bool => auth()->user()?->can('delete', $record) ?? false)
                    ->using(function (CustomScript $record): bool {
                        $this->authorizeMutation('delete', $record);

                        return (bool) $record->delete();
                    }),
            ])->emptyStateHeading('No hay código personalizado')->emptyStateDescription('Añade únicamente fragmentos de proveedores de confianza. Se crean inactivos.');
    }

    public function render(): View
    {
        $this->authorizeManager();

        return view('livewire.custom-script-manager');
    }
}
