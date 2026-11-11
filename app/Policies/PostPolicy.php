<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use App\Permission;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewPost);
    }

    public function view(User $user, Post $record): bool
    {
        return $user->hasPermission(Permission::ViewPost);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreatePost);
    }

    public function update(User $user, Post $record): bool
    {
        return $user->hasPermission(Permission::UpdatePost);
    }

    public function delete(User $user, Post $record): bool
    {
        return $user->hasPermission(Permission::DeletePost);
    }
}
