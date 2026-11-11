<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;
use App\Permission;

class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewContactMessage);
    }

    public function view(User $user, ContactMessage $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ContactMessage $record): bool
    {
        return $user->hasPermission(Permission::UpdateContactMessage);
    }

    public function delete(User $user, ContactMessage $record): bool
    {
        return $user->hasPermission(Permission::DeleteContactMessage);
    }
}
