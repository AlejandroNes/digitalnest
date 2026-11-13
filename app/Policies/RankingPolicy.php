<?php

namespace App\Policies;

use App\Models\Ranking;
use App\Models\User;
use App\Permission;

class RankingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewRanking);
    }

    public function view(User $user, Ranking $record): bool
    {
        return $user->hasPermission(Permission::ViewRanking);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateRanking);
    }

    public function update(User $user, Ranking $record): bool
    {
        return $user->hasPermission(Permission::UpdateRanking);
    }

    public function delete(User $user, Ranking $record): bool
    {
        return $user->hasPermission(Permission::DeleteRanking);
    }
}
