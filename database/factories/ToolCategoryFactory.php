<?php

namespace Database\Factories;

use App\Models\ToolCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ToolCategory> */
class ToolCategoryFactory extends Factory
{
    protected $model = ToolCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => 0,
            'seo_title' => null,
            'seo_description' => null,
            'seo_noindex' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
