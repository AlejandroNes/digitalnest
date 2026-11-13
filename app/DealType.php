<?php

namespace App;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DealType: string implements HasColor, HasLabel
{
    case Discount = 'discount';
    case Coupon = 'coupon';
    case Lifetime = 'lifetime';
    case FreeTrial = 'free_trial';

    public function getLabel(): string
    {
        return match ($this) {
            self::Discount => 'Oferta',
            self::Coupon => 'Cupón',
            self::Lifetime => 'Pago único',
            self::FreeTrial => 'Prueba gratis',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Discount => 'primary',
            self::Coupon => 'success',
            self::Lifetime => 'warning',
            self::FreeTrial => 'info',
        };
    }
}
