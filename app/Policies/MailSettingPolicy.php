<?php

namespace App\Policies;

use App\Models\MailSetting;
use App\Models\User;
use App\Permission;

class MailSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewSettings);
    }

    public function view(User $user, MailSetting $setting): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, MailSetting $setting): bool
    {
        return $user->hasPermission(Permission::UpdateSettings);
    }

    public function sendTest(User $user, MailSetting $setting): bool
    {
        return $user->hasPermission(Permission::TestMail);
    }
}
