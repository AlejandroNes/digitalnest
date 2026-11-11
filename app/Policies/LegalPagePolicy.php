<?php

namespace App\Policies;

use App\Models\LegalPage;
use App\Models\User;
use App\Permission;

class LegalPagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewLegalPage);
    }

    public function view(User $user, LegalPage $record): bool
    {
        return $user->hasPermission(Permission::ViewLegalPage);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateLegalPage);
    }

    public function update(User $user, LegalPage $record): bool
    {
        return $user->hasPermission(Permission::UpdateLegalPage);
    }

    public function delete(User $user, LegalPage $record): bool
    {
        return $user->hasPermission(Permission::DeleteLegalPage);
    }
}
