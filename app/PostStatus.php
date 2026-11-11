<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum PostStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Borrador', self::Published => 'Publicado'
        };
    }
}
