<?php

namespace App\Policies;

use App\Models\ToolCategory;
use App\Models\User;
use App\Permission;

class ToolCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewToolCategory);
    }

    public function view(User $user, ToolCategory $record): bool
    {
        return $user->hasPermission(Permission::ViewToolCategory);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateToolCategory);
    }

    public function update(User $user, ToolCategory $record): bool
    {
        return $user->hasPermission(Permission::UpdateToolCategory);
    }

    public function delete(User $user, ToolCategory $record): bool
    {
        return $user->hasPermission(Permission::DeleteToolCategory) && ! $record->tools()->exists();
    }
}
