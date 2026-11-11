<?php

namespace App\Policies;

use App\Models\ContactAssistantSetting;
use App\Models\User;
use App\Permission;

class ContactAssistantSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewContactAssistantSettings);
    }

    public function view(User $user, ContactAssistantSetting $setting): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ContactAssistantSetting $setting): bool
    {
        return $user->hasPermission(Permission::UpdateContactAssistantSettings);
    }
}
