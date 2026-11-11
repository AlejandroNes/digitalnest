<?php

namespace App\Filament\Pages;

use App\Models\GeneralSetting;
use App\Services\ApplicationCache;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class SystemTools extends Page
{
    protected static ?string $title = 'Caché de aplicación';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración y Sistema';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.system-tools';

    public static function canAccess(): bool
    {
        return Gate::allows('update', GeneralSetting::current(false));
    }

    public function mount(): void
    {
        Gate::authorize('update', GeneralSetting::current(false));
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('clearApplicationCache')->label('Limpiar caché de aplicación')
            ->requiresConfirmation()
            ->modalDescription('Se renuevan los ajustes públicos y la revisión de privacidad. Se conservan las sesiones y los límites de envío.')
            ->action(fn () => $this->clearApplicationCache())];
    }

    public function clearApplicationCache(): void
    {
        Gate::authorize('update', GeneralSetting::current(false));
        ApplicationCache::clear();
        Notification::make()->title('Caché de aplicación limpiada')->success()->send();
    }
}
