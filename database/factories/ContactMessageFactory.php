<?php

namespace Database\Factories;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ContactMessage> */
class ContactMessageFactory extends Factory
{
    public function definition(): array
    {
        return ['submission_key' => (string) Str::uuid(), 'name' => fake()->name(), 'email' => fake()->safeEmail(), 'message' => fake()->paragraph()];
    }
}
