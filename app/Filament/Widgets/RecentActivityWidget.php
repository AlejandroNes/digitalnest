<?php

namespace App\Filament\Widgets;

use App\Models\ContactMessage;
use App\Models\Post;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class RecentActivityWidget extends Widget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.recent-activity-widget';

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user || ! $user->canAccessPanel(Filament::getPanel('admin'))) {
            return false;
        }

        return Gate::allows('viewAny', ContactMessage::class)
            || Gate::allows('viewAny', Post::class);
    }

    /**
     * @return array{
     *     canViewMessages: bool,
     *     canViewPosts: bool,
     *     recentMessages: Collection<int, ContactMessage>,
     *     recentPosts: Collection<int, Post>,
     * }
     */
    protected function getViewData(): array
    {
        $canViewMessages = Gate::allows('viewAny', ContactMessage::class);
        $canViewPosts = Gate::allows('viewAny', Post::class);

        /** @var Collection<int, ContactMessage> $recentMessages */
        $recentMessages = $canViewMessages
            ? ContactMessage::query()
                ->latest('created_at')
                ->limit(5)
                ->get(['id', 'name', 'email', 'subject', 'status', 'created_at'])
            : collect();

        /** @var Collection<int, Post> $recentPosts */
        $recentPosts = $canViewPosts
            ? Post::query()
                ->latest('updated_at')
                ->limit(5)
                ->get(['id', 'slug', 'title', 'status', 'published_at', 'updated_at'])
            : collect();

        return [
            'canViewMessages' => $canViewMessages,
            'canViewPosts' => $canViewPosts,
            'recentMessages' => $recentMessages,
            'recentPosts' => $recentPosts,
        ];
    }
}
