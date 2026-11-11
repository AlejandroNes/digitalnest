<?php

namespace Database\Factories;

use App\Models\LegalPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LegalPage> */
class LegalPageFactory extends Factory
{
    public function definition(): array
    {
        return ['type' => 'terms', 'content' => '<p>Contenido legal.</p>', 'is_published' => false];
    }
}
