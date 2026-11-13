<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Models\Concerns\RedirectsChangedSlug;
use App\PublicationStatus;
use App\Services\ApplicationCache;
use Database\Factories\RankingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'title',
    'slug',
    'tool_category_id',
    'intro',
    'status',
    'published_at',
    'is_featured',
    'sort_order',
    'seo_title',
    'seo_description',
    'seo_noindex',
])]
class Ranking extends Model
{
    /** @use HasFactory<RankingFactory> */
    use HasFactory, HasSlug, RedirectsChangedSlug;

    protected $attributes = [
        'status' => 'draft',
        'is_featured' => false,
        'sort_order' => 0,
        'seo_noindex' => false,
    ];

    /**
     * @return list<string>
     */
    public static function publicPathsForSlug(string $slug): array
    {
        return ['/top/'.$slug];
    }

    protected function casts(): array
    {
        return [
            'status' => PublicationStatus::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'seo_noindex' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => ApplicationCache::invalidateSitemap());
        static::deleted(fn () => ApplicationCache::invalidateSitemap());

        static::saving(function (self $ranking): void {
            if ($ranking->status === PublicationStatus::Published && $ranking->published_at === null) {
                $ranking->published_at = now();
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class, 'tool_category_id');
    }

    /**
     * Ranked tools ordered by their position in the list.
     */
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class)
            ->withPivot(['position', 'verdict', 'best_for'])
            ->orderByPivot('position');
    }

    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('status', PublicationStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('title');
    }
}
