<?php

namespace App\Policies;

use App\Models\ProjectCategory;
use App\Models\User;
use App\Permission;

class ProjectCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewProjectCategory);
    }

    public function view(User $user, ProjectCategory $record): bool
    {
        return $user->hasPermission(Permission::ViewProjectCategory);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateProjectCategory);
    }

    public function update(User $user, ProjectCategory $record): bool
    {
        return $user->hasPermission(Permission::UpdateProjectCategory);
    }

    public function delete(User $user, ProjectCategory $record): bool
    {
        return $user->hasPermission(Permission::DeleteProjectCategory) && ! $record->projects()->exists();
    }
}
