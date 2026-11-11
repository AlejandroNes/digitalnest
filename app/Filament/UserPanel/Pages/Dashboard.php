<?php

namespace App\Filament\UserPanel\Pages;

use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Mi Cuenta';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected string $view = 'filament.user-panel.pages.dashboard';

    public function getHeading(): string
    {
        $name = auth()->user()?->name ?? 'Cliente';

        return "Bienvenido, {$name}";
    }

    public function getSubheading(): ?string
    {
        return 'Espacio personal y seguro. Gestiona tu perfil y credenciales de acceso.';
    }
}
