<?php

namespace Database\Factories;

use App\Models\MailSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MailSetting> */
class MailSettingFactory extends Factory
{
    public function definition(): array
    {
        return ['id' => 1, 'notifications_enabled' => false, 'recipients' => ['admin@example.test']];
    }
}
