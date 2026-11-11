<?php

namespace App\Policies;

use App\Models\CustomScript;
use App\Models\User;

class CustomScriptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->isSuperAdmin();
    }

    public function view(User $user, CustomScript $script): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, CustomScript $script): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, CustomScript $script): bool
    {
        return $this->viewAny($user);
    }
}
