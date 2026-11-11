<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'slug' => fake()->unique()->slug(), 'summary' => fake()->sentence(), 'description' => '<p>Descripción del servicio.</p>', 'sort_order' => 0, 'is_active' => true];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
