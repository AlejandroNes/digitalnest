<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Permission;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewProject);
    }

    public function view(User $user, Project $record): bool
    {
        return $user->hasPermission(Permission::ViewProject);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateProject);
    }

    public function update(User $user, Project $record): bool
    {
        return $user->hasPermission(Permission::UpdateProject);
    }

    public function delete(User $user, Project $record): bool
    {
        return $user->hasPermission(Permission::DeleteProject);
    }
}
