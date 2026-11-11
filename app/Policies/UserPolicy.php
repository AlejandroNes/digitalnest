<?php

namespace App\Policies;

use App\Models\RolePermission;
use App\Models\User;
use App\Permission;
use App\UserRole;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewUsers);
    }

    public function view(User $user, User $record): bool
    {
        return $user->hasPermission(Permission::ViewUsers);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateUsers);
    }

    public function update(User $user, User $record): bool
    {
        if (! $user->hasPermission(Permission::UpdateUsers)) {
            return false;
        }

        // The superadministrator account is strictly protected and cannot be modified via resource management
        if ($record->isSuperAdmin() || $record->isDesignatedSuperAdmin()) {
            return false;
        }

        return true;
    }

    public function delete(User $user, User $record): bool
    {
        if (! $user->hasPermission(Permission::DeleteUsers)) {
            return false;
        }

        // Superadministrator is strictly protected against deletion by anyone
        if ($record->isSuperAdmin() || $record->isDesignatedSuperAdmin()) {
            return false;
        }

        // Users cannot delete their own accounts
        return ! $user->is($record);
    }

    public function assignRole(User $user, User $record, UserRole|string $role): bool
    {
        if (! $this->update($user, $record)) {
            return false;
        }

        // No user can be assigned the superadmin role via ordinary administration
        if ($role === UserRole::SuperAdmin || $role === UserRole::SuperAdmin->value) {
            return false;
        }

        // Superadmin's role cannot be modified or demoted
        if ($record->isSuperAdmin() || $record->isDesignatedSuperAdmin()) {
            return false;
        }

        // Administrators cannot escalate their own role
        if ($user->is($record)) {
            return false;
        }

        return $this->canGrantRole($user, $role);
    }

    public function assignRoleOnCreate(User $user, UserRole|string $role): bool
    {
        return $this->create($user) && $this->canGrantRole($user, $role);
    }

    private function canGrantRole(User $user, UserRole|string $role): bool
    {
        $role = is_string($role) ? UserRole::tryFrom($role) : $role;
        if (! $user->is_active || ! $role || $role === UserRole::SuperAdmin) {
            return false;
        }
        foreach (RolePermission::permissionsFor($role) as $permission) {
            if (! $user->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    public function grantPermission(User $user, User $record, Permission $permission): bool
    {
        if (! $this->update($user, $record)) {
            return false;
        }

        // Superadmin permissions are intrinsic and cannot be modified
        if ($record->isSuperAdmin() || $record->isDesignatedSuperAdmin()) {
            return false;
        }

        // Administrators cannot elevate their own privileges
        if ($user->is($record)) {
            return false;
        }

        // Administrators cannot grant permissions they do not possess
        return $user->isSuperAdmin() || $user->hasPermission($permission);
    }
}
