<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use App\Permission;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewService);
    }

    public function view(User $user, Service $record): bool
    {
        return $user->hasPermission(Permission::ViewService);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateService);
    }

    public function update(User $user, Service $record): bool
    {
        return $user->hasPermission(Permission::UpdateService);
    }

    public function delete(User $user, Service $record): bool
    {
        return $user->hasPermission(Permission::DeleteService);
    }
}
