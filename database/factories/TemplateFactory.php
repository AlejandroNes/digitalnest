<?php

namespace Database\Factories;

use App\Models\Template;
use App\PublicationStatus;
use App\TemplateFormat;
use App\TemplateType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Template> */
class TemplateFactory extends Factory
{
    protected $model = Template::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(4),
            'slug' => fake()->unique()->slug(3),
            'summary' => fake()->sentence(10),
            'type' => TemplateType::Prompt,
            'format' => TemplateFormat::Text,
            'content' => 'Actúa como '.fake()->jobTitle().'. Escribe [tema] para [público].',
            'usage' => '<p>'.fake()->paragraph().'</p>',
            'example_output' => fake()->paragraph(),
            'variables' => ['[tema]', '[público]'],
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
