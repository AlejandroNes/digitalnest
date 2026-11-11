<?php

namespace App\Policies;

use App\Models\Faq;
use App\Models\User;
use App\Permission;

class FaqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewFaq);
    }

    public function view(User $user, Faq $record): bool
    {
        return $user->hasPermission(Permission::ViewFaq);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateFaq);
    }

    public function update(User $user, Faq $record): bool
    {
        return $user->hasPermission(Permission::UpdateFaq);
    }

    public function delete(User $user, Faq $record): bool
    {
        return $user->hasPermission(Permission::DeleteFaq);
    }
}
