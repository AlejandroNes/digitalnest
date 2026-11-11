<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum LegalPageType: string implements HasLabel
{
    case Terms = 'terms';
    case Privacy = 'privacy';

    public function getLabel(): string
    {
        return match ($this) {
            self::Terms => 'Términos y condiciones', self::Privacy => 'Política de privacidad'
        };
    }
}
