<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Deals\DealResource;
use App\Models\Deal;
use App\Models\Tool;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class DealsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|array|null $columns = ['default' => 1, 'sm' => 2, 'lg' => 4];

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->canAccessPanel(Filament::getPanel('admin'))
            && Gate::allows('viewAny', Deal::class);
    }

    protected function getStats(): array
    {
        $activeCount = Deal::query()->active()->count();
        $expiringCount = Deal::query()->active()->expiringWithin(7)->count();
        $activeClicks = (int) Deal::query()->active()->sum('clicks');
        $toolsWithoutDeals = Tool::query()->published()
            ->whereDoesntHave('deals', fn (Builder $query): Builder => $query->active())->count();

        return [
            Stat::make('Ofertas activas', $activeCount)
                ->descriptionIcon(Heroicon::OutlinedTicket)->description('Visibles en el sitio')
                ->color('success')->url(DealResource::getUrl('index', ['tableFilters' => ['active' => ['isActive' => true]]])),
            Stat::make('Caducan en 7 días', $expiringCount)
                ->descriptionIcon(Heroicon::OutlinedClock)->description('Renueva o reemplaza')
                ->color($expiringCount > 0 ? 'warning' : 'gray')
                ->url(DealResource::getUrl('index', ['tableFilters' => ['expiring' => ['isActive' => true]]])),
            Stat::make('Clics en ofertas activas', $activeClicks)
                ->descriptionIcon(Heroicon::OutlinedCursorArrowRays)->description('Total acumulado')->color('primary'),
            Stat::make('Apps sin oferta', $toolsWithoutDeals)
                ->descriptionIcon(Heroicon::OutlinedExclamationCircle)->description('Publicadas sin oferta activa')->color('gray'),
        ];
    }
}
