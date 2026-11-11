<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaAsset> */
class MediaAssetFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'alt_text' => fake()->sentence()];
    }
}
