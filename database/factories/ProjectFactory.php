<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(),
            'short_description' => fake()->paragraph(),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'image_id' => null,
            'project_category_id' => null,
            'client' => fake()->company(),
            'location' => fake()->city(),
            'completed_at' => fake()->date(),
            'external_url' => fake()->url(),
            'status' => ProjectStatus::Draft,
            'is_featured' => false,
            'sort_order' => 0,
            'seo_title' => null,
            'seo_description' => null,
            'seo_image_id' => null,
            'seo_canonical' => null,
            'seo_noindex' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProjectStatus::Published,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_featured' => true,
        ]);
    }

    public function withCategory(?ProjectCategory $category = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'project_category_id' => $category?->id ?? ProjectCategory::factory(),
        ]);
    }
}
