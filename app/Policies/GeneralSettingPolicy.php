<?php

namespace App\Policies;

use App\Models\GeneralSetting;
use App\Models\User;
use App\Permission;

class GeneralSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewSettings);
    }

    public function view(User $user, GeneralSetting $setting): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, GeneralSetting $setting): bool
    {
        return $user->hasPermission(Permission::UpdateSettings);
    }
}
