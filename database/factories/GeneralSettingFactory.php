<?php

namespace Database\Factories;

use App\Models\GeneralSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GeneralSetting> */
class GeneralSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'locale' => 'es',
            'timezone' => 'UTC',
            'maintenance_enabled' => false,
        ];
    }
}
