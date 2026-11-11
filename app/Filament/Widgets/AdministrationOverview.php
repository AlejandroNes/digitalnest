<?php

namespace App\Filament\Widgets;

use App\ContactMessageStatus;
use App\Filament\Pages\GeneralSettings;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\ContactMessage;
use App\Models\GeneralSetting;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\Service;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;

class AdministrationOverview extends StatsOverviewWidget
{
    protected int|array|null $columns = ['default' => 1, 'sm' => 2, 'xl' => 3];

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->canAccessPanel(Filament::getPanel('admin')) ?? false;
    }

    protected function getStats(): array
    {
        $stats = [];

        if (Gate::allows('viewAny', Post::class)) {
            $stats[] = Stat::make('Artículos', Post::query()->count())
                ->description(Post::query()->published()->count().' publicados')->url(PostResource::getUrl());
        }
        if (Gate::allows('viewAny', Service::class)) {
            $stats[] = Stat::make('Servicios', Service::query()->count())
                ->description(Service::query()->active()->count().' activos')->url(ServiceResource::getUrl());
        }
        if (Gate::allows('viewAny', MediaAsset::class)) {
            $stats[] = Stat::make('Archivos multimedia', MediaAsset::query()->count())->url(MediaAssetResource::getUrl());
        }
        if (Gate::allows('viewAny', ContactMessage::class)) {
            $stats[] = Stat::make('Mensajes pendientes', ContactMessage::query()->where('status', ContactMessageStatus::Pending)->count())
                ->url(ContactMessageResource::getUrl());
        }
        if (Gate::allows('viewAny', User::class)) {
            $stats[] = Stat::make('Usuarios', User::query()->count())->url(UserResource::getUrl());
            $stats[] = Stat::make('Administradores activos', User::query()->whereIn('role', [UserRole::Admin, UserRole::SuperAdmin])->where('is_active', true)->count());
        }
        if (Gate::allows('viewAny', GeneralSetting::class)) {
            $stats[] = Stat::make('Sitio', GeneralSetting::current(false)->maintenance_enabled ? 'En mantenimiento' : 'Disponible')
                ->url(GeneralSettings::getUrl());
        }

        return $stats;
    }
}
