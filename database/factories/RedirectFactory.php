<?php

namespace Database\Factories;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Redirect> */
class RedirectFactory extends Factory
{
    public function definition(): array
    {
        return ['source' => '/old-'.fake()->unique()->slug(), 'destination' => '/', 'status_code' => 301, 'is_active' => true];
    }
}
