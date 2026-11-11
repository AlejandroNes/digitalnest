<?php

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\User;
use App\Permission;

class MediaAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewMediaAsset);
    }

    public function view(User $user, MediaAsset $record): bool
    {
        return $user->hasPermission(Permission::ViewMediaAsset);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateMediaAsset);
    }

    public function update(User $user, MediaAsset $record): bool
    {
        return $user->hasPermission(Permission::UpdateMediaAsset);
    }

    public function delete(User $user, MediaAsset $record): bool
    {
        return $user->hasPermission(Permission::DeleteMediaAsset) && ! $record->isReferenced();
    }
}
