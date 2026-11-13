<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum ToolSpanishSupport: string implements HasLabel
{
    case Full = 'full';
    case Partial = 'partial';
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::Full => 'Funciona bien en español',
            self::Partial => 'Español parcial',
            self::None => 'Solo en inglés',
        };
    }
}
