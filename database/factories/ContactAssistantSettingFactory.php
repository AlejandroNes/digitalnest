<?php

namespace Database\Factories;

use App\Models\ContactAssistantSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactAssistantSetting> */
class ContactAssistantSettingFactory extends Factory
{
    public function definition(): array
    {
        return ['id' => 1, 'enabled' => false, 'destination_whatsapp' => '+34911234567'];
    }

    public function enabled(): static
    {
        return $this->state(fn (): array => ['enabled' => true]);
    }
}
