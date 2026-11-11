<?php

namespace Database\Factories;

use App\Models\SearchSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SearchSetting> */
class SearchSettingFactory extends Factory
{
    public function definition(): array
    {
        return ['id' => 1, 'default_title' => fake()->company(), 'default_description' => fake()->sentence()];
    }
}
