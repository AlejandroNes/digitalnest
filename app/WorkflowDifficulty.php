<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum WorkflowDifficulty: string implements HasLabel
{
    case Basic = 'basic';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    public function getLabel(): string
    {
        return match ($this) {
            self::Basic => 'Básico',
            self::Intermediate => 'Intermedio',
            self::Advanced => 'Avanzado',
        };
    }
}
