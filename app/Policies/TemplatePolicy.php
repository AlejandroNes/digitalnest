<?php

namespace App\Policies;

use App\Models\Template;
use App\Models\User;
use App\Permission;

class TemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewTemplate);
    }

    public function view(User $user, Template $record): bool
    {
        return $user->hasPermission(Permission::ViewTemplate);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateTemplate);
    }

    public function update(User $user, Template $record): bool
    {
        return $user->hasPermission(Permission::UpdateTemplate);
    }

    public function delete(User $user, Template $record): bool
    {
        return $user->hasPermission(Permission::DeleteTemplate);
    }
}
