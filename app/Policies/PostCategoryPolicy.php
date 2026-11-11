<?php

namespace App\Policies;

use App\Models\PostCategory;
use App\Models\User;
use App\Permission;

class PostCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewPostCategory);
    }

    public function view(User $user, PostCategory $record): bool
    {
        return $user->hasPermission(Permission::ViewPostCategory);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreatePostCategory);
    }

    public function update(User $user, PostCategory $record): bool
    {
        return $user->hasPermission(Permission::UpdatePostCategory);
    }

    public function delete(User $user, PostCategory $record): bool
    {
        return $user->hasPermission(Permission::DeletePostCategory) && ! $record->posts()->exists();
    }
}
