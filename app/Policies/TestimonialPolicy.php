<?php

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;
use App\Permission;

class TestimonialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewTestimonial);
    }

    public function view(User $user, Testimonial $record): bool
    {
        return $user->hasPermission(Permission::ViewTestimonial);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateTestimonial);
    }

    public function update(User $user, Testimonial $record): bool
    {
        return $user->hasPermission(Permission::UpdateTestimonial);
    }

    public function delete(User $user, Testimonial $record): bool
    {
        return $user->hasPermission(Permission::DeleteTestimonial);
    }
}
