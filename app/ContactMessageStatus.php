<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum ContactMessageStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Read = 'read';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Read => 'Leído',
        };
    }
}
