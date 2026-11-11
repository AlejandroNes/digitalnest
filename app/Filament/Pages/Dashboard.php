<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\RecentActivityWidget;
use App\Filament\Widgets\SiteSummaryWidget;
use App\Models\GeneralSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Panel de control';

    protected static ?string $navigationLabel = 'Panel de control';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = -1;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewSite')
                ->label('Ver sitio web')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(url('/'))
                ->openUrlInNewTab(),
        ];
    }

    public function getHeading(): string
    {
        $hour = (int) now()->format('H');
        $greeting = match (true) {
            $hour >= 6 && $hour < 12 => 'Buenos días',
            $hour >= 12 && $hour < 20 => 'Buenas tardes',
            default => 'Buenas noches',
        };

        $user = auth()->user();

        return $user?->name ? "{$greeting}, {$user->name}" : 'Panel de control';
    }

    public function getSubheading(): ?string
    {
        $siteName = GeneralSetting::current(false)->company_name ?: config('app.name', 'Sitio corporativo');

        return "Resumen del estado y accesos directos para {$siteName}.";
    }

    /**
     * @return int | array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return 1;
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            SiteSummaryWidget::class,
            RecentActivityWidget::class,
            QuickActionsWidget::class,
        ];
    }
}
