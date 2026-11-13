<?php

namespace App\Policies;

use App\Models\Tool;
use App\Models\User;
use App\Permission;

class ToolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewTool);
    }

    public function view(User $user, Tool $record): bool
    {
        return $user->hasPermission(Permission::ViewTool);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateTool);
    }

    public function update(User $user, Tool $record): bool
    {
        return $user->hasPermission(Permission::UpdateTool);
    }

    public function delete(User $user, Tool $record): bool
    {
        return $user->hasPermission(Permission::DeleteTool);
    }
}
