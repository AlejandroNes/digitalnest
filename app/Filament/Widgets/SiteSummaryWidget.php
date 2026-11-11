<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Models\ContactMessage;
use App\Models\Post;
use App\Models\Service;
use App\PostStatus;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;

class SiteSummaryWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

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

        if (! $user || ! $user->canAccessPanel(Filament::getPanel('admin'))) {
            return false;
        }

        return Gate::allows('viewAny', ContactMessage::class)
            || Gate::allows('viewAny', Post::class)
            || Gate::allows('viewAny', Service::class);
    }

    protected function getStats(): array
    {
        $stats = [];

        if (Gate::allows('viewAny', ContactMessage::class)) {
            $pendingCount = ContactMessage::query()->pending()->count();
            $stats[] = Stat::make('Mensajes pendientes', $pendingCount)
                ->description($pendingCount === 1 ? '1 mensaje por responder' : "{$pendingCount} por responder")
                ->descriptionIcon(Heroicon::OutlinedInbox)
                ->color($pendingCount > 0 ? 'warning' : 'gray')
                ->url(ContactMessageResource::getUrl());
        }

        if (Gate::allows('viewAny', Post::class)) {
            $publishedCount = Post::query()->published()->count();
            $stats[] = Stat::make('Artículos publicados', $publishedCount)
                ->description('Visibles en el sitio web')
                ->descriptionIcon(Heroicon::OutlinedDocumentCheck)
                ->color('success')
                ->url(PostResource::getUrl());

            $draftCount = Post::query()->where('status', PostStatus::Draft)->count();
            $stats[] = Stat::make('Borradores', $draftCount)
                ->description('En preparación')
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->color('gray')
                ->url(PostResource::getUrl('index', ['tableFilters' => ['status' => ['value' => PostStatus::Draft->value]]]));
        }

        if (Gate::allows('viewAny', Service::class)) {
            $activeCount = Service::query()->active()->count();
            $stats[] = Stat::make('Servicios activos', $activeCount)
                ->description('Habilitados en el sitio')
                ->descriptionIcon(Heroicon::OutlinedBriefcase)
                ->color('primary')
                ->url(ServiceResource::getUrl());
        }

        return $stats;
    }
}
