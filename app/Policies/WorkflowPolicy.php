<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workflow;
use App\Permission;

class WorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewWorkflow);
    }

    public function view(User $user, Workflow $record): bool
    {
        return $user->hasPermission(Permission::ViewWorkflow);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateWorkflow);
    }

    public function update(User $user, Workflow $record): bool
    {
        return $user->hasPermission(Permission::UpdateWorkflow);
    }

    public function delete(User $user, Workflow $record): bool
    {
        return $user->hasPermission(Permission::DeleteWorkflow);
    }
}
