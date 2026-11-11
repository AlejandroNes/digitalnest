<?php

namespace App\Policies;

use App\Models\Redirect;
use App\Models\User;
use App\Permission;

class RedirectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewRedirect);
    }

    public function view(User $user, Redirect $record): bool
    {
        return $user->hasPermission(Permission::ViewRedirect);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateRedirect);
    }

    public function update(User $user, Redirect $record): bool
    {
        return $user->hasPermission(Permission::UpdateRedirect);
    }

    public function delete(User $user, Redirect $record): bool
    {
        return $user->hasPermission(Permission::DeleteRedirect);
    }
}
