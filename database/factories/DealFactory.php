<?php

namespace Database\Factories;

use App\DealType;
use App\Models\Deal;
use App\Models\Tool;
use App\PublicationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Deal> */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    public function definition(): array
    {
        return [
            'tool_id' => Tool::factory()->published(),
            'type' => DealType::Discount,
            'title' => fake()->sentence(4),
            'slug' => fake()->unique()->slug(3),
            'description' => fake()->sentence(),
            'code' => null,
            'discount_percent' => null,
            'original_price' => 100,
            'deal_price' => 60,
            'currency' => 'USD',
            'url' => null,
            'starts_at' => null,
            'expires_at' => now()->addWeeks(2),
            'verified_at' => now(),
            'is_exclusive' => false,
            'is_featured' => false,
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PublicationStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function coupon(?string $code = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => DealType::Coupon,
            'code' => $code ?? strtoupper(fake()->bothify('SAVE##??')),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => now()->subDay()]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes): array => ['is_featured' => true]);
    }
}
