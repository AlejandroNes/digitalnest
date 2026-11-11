<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = User::query()->findOrFail(auth()->id());
        Gate::forUser($actor)->authorize('create', User::class);
        if (! Gate::forUser($actor)->allows('assignRoleOnCreate', [User::class, $data['role']])) {
            throw ValidationException::withMessages(['data.role' => 'No puedes asignar un rol con permisos que no posees.']);
        }

        return $data;
    }
}
