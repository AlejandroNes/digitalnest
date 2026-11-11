<?php

namespace Database\Factories;

use App\Models\ToolCategory;
use App\Models\Workflow;
use App\PublicationStatus;
use App\WorkflowDifficulty;
use App\WorkflowProfession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Workflow> */
class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(5),
            'slug' => fake()->unique()->slug(3),
            'summary' => fake()->sentence(10),
            'goal' => fake()->sentence(),
            'intro' => '<p>'.fake()->paragraph().'</p>',
            'result' => '<p>'.fake()->paragraph().'</p>',
            'image_id' => null,
            'tool_category_id' => ToolCategory::factory(),
            'professions' => [WorkflowProfession::Freelancer->value],
            'difficulty' => WorkflowDifficulty::Basic,
            'time_minutes' => 30,
            'cost_usd' => 0,
            'tested_at' => now()->subWeek()->toDateString(),
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'is_featured' => false,
            'sort_order' => 0,
            'seo_noindex' => false,
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
