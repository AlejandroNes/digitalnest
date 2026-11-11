<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    public function definition(): array
    {
        return ['title' => fake()->sentence(), 'slug' => fake()->unique()->slug(), 'excerpt' => fake()->sentence(), 'content' => '<p>Contenido del artículo.</p>', 'status' => 'draft', 'published_at' => null];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'published', 'published_at' => now()]);
    }
}
