<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Permission;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    /** @var list<string>|null */
    private ?array $pendingPermissions = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->getRecord()->isSuperAdmin()) {
            abort(403, 'La cuenta superadministradora está protegida y no puede ser modificada desde la gestión de usuarios.');
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $data['custom_permissions'] = $record->permissions()->pluck('name')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $target = $this->getRecord();
        $actor = User::query()->findOrFail(auth()->id());
        Gate::forUser($actor)->authorize('update', $target);

        if ($target->isSuperAdmin()) {
            abort(403, 'La cuenta superadministradora está protegida y no puede ser modificada desde la gestión de usuarios.');
        }

        if ($target->is($actor)) {
            unset($data['role'], $data['is_active']);
        }

        if (isset($data['role']) && ($data['role'] === UserRole::SuperAdmin->value || $data['role'] === UserRole::SuperAdmin)) {
            throw ValidationException::withMessages(['data.role' => 'El rol de superadministrador no puede ser asignado.']);
        }

        if (isset($data['role']) && $data['role'] !== $target->role?->value
            && ! Gate::forUser($actor)->allows('assignRole', [$target, $data['role']])) {
            throw ValidationException::withMessages(['data.role' => 'No puedes asignar un rol con permisos que no posees.']);
        }
        if (isset($data['custom_permissions']) && ! $target->is($actor)) {
            $existing = $target->permissions()->pluck('name')->all();
            foreach (array_diff($data['custom_permissions'], $existing) as $name) {
                if (! Gate::forUser($actor)->allows('grantPermission', [$target, Permission::from($name)])) {
                    throw ValidationException::withMessages(['data.custom_permissions' => 'No puedes conceder un permiso que no posees.']);
                }
            }
            $this->pendingPermissions = $data['custom_permissions'];
        } else {
            $this->pendingPermissions = null;
        }

        unset($data['custom_permissions']);

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        $actor = User::query()->findOrFail(auth()->id());

        if ($this->pendingPermissions !== null && ! $record->isSuperAdmin() && ! $record->is($actor)) {
            $record->syncPermissions($this->pendingPermissions, $actor);
        }
        $this->pendingPermissions = null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetPassword')
                ->label('Restablecer contraseña')
                ->icon(Heroicon::OutlinedKey)
                ->color('warning')
                ->modalHeading('Restablecer contraseña')
                ->modalDescription(fn (): string => "Define una nueva contraseña para {$this->getRecord()->name}.")
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
                ->action(function (array $data): void {
                    Gate::authorize('update', $this->getRecord());

                    if ($this->getRecord()->isSuperAdmin()) {
                        abort(403, 'La cuenta superadministradora está protegida y no puede modificarse.');
                    }

                    $this->getRecord()->update([
                        'password' => Hash::make($data['password']),
                    ]);

                    Notification::make()
                        ->title('Contraseña restablecida correctamente')
                        ->success()
                        ->send();
                })
                ->hidden(fn (): bool => $this->getRecord()->isSuperAdmin() || ! auth()->user()?->can('update', $this->getRecord())),

            DeleteAction::make()
                ->modalHeading('¿Eliminar usuario?')
                ->modalDescription('Esta acción es irreversible y eliminará definitivamente los datos del usuario.')
                ->hidden(fn (): bool => $this->getRecord()->isSuperAdmin() || $this->getRecord()->is(auth()->user()) || ! auth()->user()?->can('delete', $this->getRecord())),
        ];
    }
}
