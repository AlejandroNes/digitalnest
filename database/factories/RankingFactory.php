<?php

namespace Database\Factories;

use App\Models\Ranking;
use App\Models\ToolCategory;
use App\PublicationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ranking> */
class RankingFactory extends Factory
{
    protected $model = Ranking::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'slug' => fake()->unique()->slug(4),
            'tool_category_id' => ToolCategory::factory(),
            'intro' => fake()->paragraph(),
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PublicationStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }
}
