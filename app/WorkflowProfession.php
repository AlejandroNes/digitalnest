<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum WorkflowProfession: string implements HasLabel
{
    case Freelancer = 'freelancer';
    case Marketing = 'marketing';
    case Creator = 'creator';
    case SmallBusiness = 'small_business';
    case Teacher = 'teacher';
    case Sales = 'sales';

    public function getLabel(): string
    {
        return match ($this) {
            self::Freelancer => 'Freelancers',
            self::Marketing => 'Marketing',
            self::Creator => 'Creadores',
            self::SmallBusiness => 'Pequeño negocio',
            self::Teacher => 'Docentes',
            self::Sales => 'Ventas',
        };
    }
}
