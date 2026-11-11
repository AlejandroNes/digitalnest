<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Faq> */
class FaqFactory extends Factory
{
    public function definition(): array
    {
        return ['question' => fake()->sentence().'?', 'answer' => '<p>Respuesta de ejemplo.</p>', 'sort_order' => 0, 'is_active' => true];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
