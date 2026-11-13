<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\User;
use App\Permission;

class DealPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewDeal);
    }

    public function view(User $user, Deal $record): bool
    {
        return $user->hasPermission(Permission::ViewDeal);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateDeal);
    }

    public function update(User $user, Deal $record): bool
    {
        return $user->hasPermission(Permission::UpdateDeal);
    }

    public function delete(User $user, Deal $record): bool
    {
        return $user->hasPermission(Permission::DeleteDeal);
    }

    public function replicate(User $user, Deal $record): bool
    {
        return $user->hasPermission(Permission::CreateDeal);
    }
}
