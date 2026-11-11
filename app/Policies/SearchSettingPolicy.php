<?php

namespace App\Policies;

use App\Models\SearchSetting;
use App\Models\User;
use App\Permission;

class SearchSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewSettings);
    }

    public function view(User $user, SearchSetting $setting): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SearchSetting $setting): bool
    {
        return $user->hasPermission(Permission::UpdateSettings);
    }
}
