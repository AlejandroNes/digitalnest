<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\GeneralSettings;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Models\ContactMessage;
use App\Models\GeneralSetting;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

class QuickActionsWidget extends Widget
{
    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.quick-actions-widget';

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user || ! $user->canAccessPanel(Filament::getPanel('admin'))) {
            return false;
        }

        return Gate::allows('create', Post::class)
            || Gate::allows('create', MediaAsset::class)
            || Gate::allows('create', Service::class)
            || Gate::allows('create', Project::class)
            || Gate::allows('viewAny', ContactMessage::class)
            || Gate::allows('viewAny', GeneralSetting::class);
    }

    /**
     * @return array{
     *     actions: array<int, array{
     *         label: string,
     *         description: string,
     *         icon: Heroicon,
     *         url: string,
     *     }>
     * }
     */
    protected function getViewData(): array
    {
        $actions = [];

        if (Gate::allows('create', Post::class)) {
            $actions[] = [
                'label' => 'Crear artículo',
                'description' => 'Redactar publicación para el blog',
                'icon' => Heroicon::OutlinedDocumentPlus,
                'url' => PostResource::getUrl('create'),
            ];
        }

        if (Gate::allows('create', MediaAsset::class)) {
            $actions[] = [
                'label' => 'Subir imagen',
                'description' => 'Añadir archivos a multimedia',
                'icon' => Heroicon::OutlinedArrowUpTray,
                'url' => MediaAssetResource::getUrl('create'),
            ];
        }

        if (Gate::allows('create', Service::class)) {
            $actions[] = [
                'label' => 'Crear servicio',
                'description' => 'Añadir un servicio corporativo',
                'icon' => Heroicon::OutlinedBriefcase,
                'url' => ServiceResource::getUrl('create'),
            ];
        }

        if (Gate::allows('create', Project::class)) {
            $actions[] = [
                'label' => 'Crear proyecto',
                'description' => 'Añadir caso de éxito o proyecto',
                'icon' => Heroicon::OutlinedSquares2x2,
                'url' => ProjectResource::getUrl('create'),
            ];
        }

        if (Gate::allows('viewAny', ContactMessage::class)) {
            $actions[] = [
                'label' => 'Ver mensajes',
                'description' => 'Bandeja de mensajes de contacto',
                'icon' => Heroicon::OutlinedInbox,
                'url' => ContactMessageResource::getUrl(),
            ];
        }

        if (Gate::allows('viewAny', GeneralSetting::class)) {
            $actions[] = [
                'label' => 'Configuración general',
                'description' => 'Datos de empresa y mantenimiento',
                'icon' => Heroicon::OutlinedCog6Tooth,
                'url' => GeneralSettings::getUrl(),
            ];
        }

        return [
            'actions' => $actions,
        ];
    }
}
