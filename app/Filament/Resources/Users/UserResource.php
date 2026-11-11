<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Permission;
use App\UserRole;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración y Sistema';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            Section::make('Datos de la cuenta')
                ->schema([
                    TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                    TextInput::make('email')->label('Correo')->email()->required()->maxLength(255)->unique(ignoreRecord: true)
                        ->disabled(fn (?User $record): bool => $record?->isSuperAdmin() ?? false),
                    TextInput::make('password')->label('Contraseña')->password()->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->rule(Password::min(12)->mixedCase()->numbers()->symbols())
                        ->dehydrated(fn (?string $state): bool => filled($state)),
                    TextInput::make('password_confirmation')->label('Confirmar contraseña')->password()->revealable()
                        ->dehydrated(false)
                        ->visible(fn (string $operation): bool => $operation === 'create')
                        ->rule(fn (Get $get): ?string => filled($get('password_confirmation')) ? 'same:data.password' : null),
                    Select::make('role')->label('Rol')
                        ->options(fn (?User $record, Get $get): array => self::assignableRoles($record, $get('role')))
                        ->default(UserRole::Editor->value)
                        ->required()
                        ->rule(Rule::in([
                            UserRole::Admin->value,
                            UserRole::Administrator->value,
                            UserRole::Editor->value,
                            UserRole::User->value,
                        ]))
                        ->disabled(fn (?User $record): bool => $record?->isSuperAdmin() || ($record?->is(auth()->user()) ?? false))
                        ->live()
                        ->helperText('El rol define los permisos base. Solo puedes asignar roles cuyos permisos posees. La cuenta superadministradora está protegida.'),
                    Toggle::make('is_active')->label('Acceso activo')->default(true)->required()
                        ->disabled(fn (?User $record): bool => $record?->isSuperAdmin() || ($record?->is(auth()->user()) ?? false)),
                ])->columns(['default' => 1, 'md' => 2])->columnSpanFull(),

            Section::make('Permisos individuales específicos')
                ->description('Estos permisos se suman a los del rol. Retirar un permiso directo no elimina el acceso que ya concede el rol.')
                ->visible(fn (string $operation, ?User $record, Get $get): bool => $operation === 'edit' && ! ($record?->isSuperAdmin() ?? false) && ! in_array($get('role'), [UserRole::User, UserRole::User->value], true))
                ->schema([
                    CheckboxList::make('custom_permissions')
                        ->label('Permisos directos asignados')
                        ->options(fn (?User $record): array => self::permissionOptions($record))
                        ->searchable()
                        ->rules(['array'])
                        ->nestedRecursiveRules(['string', 'distinct', Rule::in(array_map(fn (Permission $permission): string => $permission->value, Permission::cases()))])
                        ->columns(['default' => 1, 'md' => 2])
                        ->disabled(fn (?User $record): bool => $record?->isSuperAdmin() || ($record?->is(auth()->user()) ?? false)),
                ])
                ->collapsed()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->stackedOnMobile()->columns([
            TextColumn::make('name')->wrap()->label('Nombre')->searchable()->sortable(),
            TextColumn::make('email')->wrap()->label('Correo')->searchable()->sortable(),
            TextColumn::make('role')->wrap()->label('Rol')->badge()->color(fn (UserRole $state): string => match ($state) {
                UserRole::SuperAdmin => 'danger',
                UserRole::Admin, UserRole::Administrator => 'primary',
                UserRole::Editor => 'info',
                UserRole::User => 'gray',
            })->formatStateUsing(fn (UserRole $state): string => $state->getLabel()),
            IconColumn::make('is_active')->label('Estado')->boolean()
                ->trueIcon(Heroicon::OutlinedCheckCircle)
                ->falseIcon(Heroicon::OutlinedNoSymbol)
                ->trueColor('success')
                ->falseColor('danger'),
            TextColumn::make('created_at')->wrap()->label('Creado')->dateTime('d/m/Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('role')->label('Rol')->options([
                UserRole::SuperAdmin->value => 'Superadministrador',
                UserRole::Admin->value => 'Administrador',
                UserRole::Editor->value => 'Editor',
                UserRole::User->value => 'Usuario',
            ])->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null,
                fn (Builder $query, string $role): Builder => $role === UserRole::Admin->value
                    ? $query->whereIn('role', [UserRole::Admin->value, UserRole::Administrator->value])
                    : $query->where('role', $role))),
            TernaryFilter::make('is_active')->label('Estado')->placeholder('Todos')->trueLabel('Activos')->falseLabel('Inactivos'),
        ])->recordActions([
            EditAction::make()
                ->hidden(fn (User $record): bool => $record->isSuperAdmin() || ! auth()->user()?->can('update', $record)),

            Action::make('resetPassword')
                ->label('Restablecer contraseña')
                ->icon(Heroicon::OutlinedKey)
                ->color('warning')
                ->modalHeading('Restablecer contraseña')
                ->modalDescription(fn (User $record): string => "Define una nueva contraseña segura para {$record->name}.")
                ->modalSubmitActionLabel('Actualizar contraseña')
                ->form([
                    TextInput::make('password')
                        ->label('Nueva contraseña')
                        ->password()
                        ->revealable()
                        ->required()
                        ->rule(Password::min(12)->mixedCase()->numbers()->symbols()),
                    TextInput::make('password_confirmation')
                        ->label('Confirmar nueva contraseña')
                        ->password()
                        ->revealable()
                        ->required()
                        ->same('password'),
                ])
                ->action(function (User $record, array $data): void {
                    Gate::authorize('update', $record);

                    if ($record->isSuperAdmin()) {
                        abort(403, 'La cuenta superadministradora está protegida y no puede restablecerse aquí.');
                    }

                    $record->update([
                        'password' => Hash::make($data['password']),
                    ]);

                    Notification::make()
                        ->title('Contraseña restablecida correctamente')
                        ->success()
                        ->send();
                })
                ->hidden(fn (User $record): bool => $record->isSuperAdmin() || ! auth()->user()?->can('update', $record)),

            Action::make('toggleActive')
                ->label(fn (User $record): string => $record->is_active ? 'Desactivar' : 'Activar')
                ->icon(fn (User $record): BackedEnum => $record->is_active ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
                ->color(fn (User $record): string => $record->is_active ? 'warning' : 'success')
                ->requiresConfirmation()
                ->modalHeading(fn (User $record): string => $record->is_active ? '¿Desactivar acceso?' : '¿Activar acceso?')
                ->modalDescription(fn (User $record): string => $record->is_active
                    ? "El usuario {$record->name} no podrá iniciar sesión en el panel administrativo mientras su cuenta esté desactivada."
                    : "El usuario {$record->name} recuperará el acceso al panel administrativo.")
                ->modalSubmitActionLabel(fn (User $record): string => $record->is_active ? 'Desactivar cuenta' : 'Activar cuenta')
                ->action(function (User $record): void {
                    Gate::authorize('update', $record);

                    if ($record->isSuperAdmin()) {
                        abort(403, 'La cuenta superadministradora no puede ser desactivada.');
                    }

                    if ($record->is(auth()->user())) {
                        abort(403, 'No puedes desactivar tu propia cuenta.');
                    }

                    $record->update([
                        'is_active' => ! $record->is_active,
                    ]);

                    Notification::make()
                        ->title($record->is_active ? 'Cuenta activada correctamente' : 'Cuenta desactivada correctamente')
                        ->success()
                        ->send();
                })
                ->hidden(fn (User $record): bool => $record->isSuperAdmin() || $record->is(auth()->user()) || ! auth()->user()?->can('update', $record)),

            DeleteAction::make()
                ->modalHeading('¿Eliminar usuario?')
                ->modalDescription('Esta acción es irreversible y eliminará definitivamente los datos de la cuenta.')
                ->hidden(fn (User $record): bool => $record->isSuperAdmin() || $record->is(auth()->user()) || ! auth()->user()?->can('delete', $record)),
        ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    private static function assignableRoles(?User $record, mixed $state): array
    {
        $actor = auth()->id() ? User::query()->find(auth()->id()) : null;
        if (! $actor) {
            return [];
        }
        $options = [];
        $adminRole = in_array($state, [UserRole::Administrator, UserRole::Administrator->value], true) ? UserRole::Administrator : UserRole::Admin;
        foreach ([$adminRole, UserRole::Editor, UserRole::User] as $role) {
            $allowed = $record?->exists
                ? Gate::forUser($actor)->allows('assignRole', [$record, $role])
                : Gate::forUser($actor)->allows('assignRoleOnCreate', [User::class, $role]);
            if ($allowed || $record?->role === $role || ($record?->is($actor) ?? false)) {
                $options[$role->value] = $role->getLabel();
            }
        }

        return $options;
    }

    /** @return array<string, string> */
    private static function permissionOptions(?User $record): array
    {
        $actor = auth()->id() ? User::query()->find(auth()->id()) : null;
        $existing = $record?->permissions()->pluck('name')->all() ?? [];

        return collect(Permission::cases())
            ->filter(fn (Permission $permission): bool => ($actor?->hasPermission($permission) ?? false) || in_array($permission->value, $existing, true))
            ->mapWithKeys(fn (Permission $permission): array => [$permission->value => $permission->getLabel()])->all();
    }
}
