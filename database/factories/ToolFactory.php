<?php

namespace Database\Factories;

use App\Models\Tool;
use App\Models\ToolCategory;
use App\ToolPlatform;
use App\ToolPricingModel;
use App\ToolSpanishSupport;
use App\ToolStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tool> */
class ToolFactory extends Factory
{
    protected $model = Tool::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'tagline' => fake()->sentence(8),
            'logo_id' => null,
            'image_id' => null,
            'tool_category_id' => ToolCategory::factory(),
            'website_url' => fake()->url(),
            'affiliate_url' => null,
            'best_for' => fake()->sentence(),
            'not_for' => null,
            'review' => '<p>'.fake()->paragraph().'</p>',
            'pros' => [fake()->sentence(4), fake()->sentence(4)],
            'cons' => [fake()->sentence(4)],
            'pricing_model' => ToolPricingModel::Freemium,
            'has_free_plan' => true,
            'free_plan_notes' => null,
            'starting_price_usd' => 10,
            'requires_card' => false,
            'spanish_support' => ToolSpanishSupport::Full,
            'latam_payments' => true,
            'platforms' => [ToolPlatform::Web->value],
            'privacy_notes' => null,
            'deal_notes' => null,
            'deal_expires_at' => null,
            'tested_at' => now()->subWeek()->toDateString(),
            'reviewed_at' => null,
            'rating' => 8,
            'status' => ToolStatus::Draft,
            'published_at' => null,
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
            'status' => ToolStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes): array => ['is_featured' => true]);
    }
}
