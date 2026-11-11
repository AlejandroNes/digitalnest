<?php

namespace Database\Factories;

use App\Models\CustomScript;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomScript> */
class CustomScriptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'code' => '<meta name="example-verification" content="'.fake()->uuid().'">',
            'position' => 'head', 'purpose' => 'necessary', 'is_active' => false,
        ];
    }
}
