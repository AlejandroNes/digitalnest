<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum ToolPricingModel: string implements HasLabel
{
    case Free = 'free';
    case Freemium = 'freemium';
    case Paid = 'paid';
    case OpenSource = 'open_source';

    public function getLabel(): string
    {
        return match ($this) {
            self::Free => 'Gratis',
            self::Freemium => 'Freemium',
            self::Paid => 'De pago',
            self::OpenSource => 'Open source',
        };
    }

    /**
     * Whether the tool can be used without paying.
     */
    public function isFree(): bool
    {
        return $this === self::Free || $this === self::OpenSource;
    }
}
