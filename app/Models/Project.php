<?php

namespace App\Models;

use App\Models\Concerns\HasContentImage;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasSlug;
use App\ProjectStatus;
use App\Services\ApplicationCache;
use App\Services\ContentHtml;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Fillable([
    'title',
    'slug',
    'short_description',
    'content',
    'description',
    'image_id',
    'project_category_id',
    'client',
    'location',
    'completed_at',
    'external_url',
    'status',
    'is_featured',
    'sort_order',
    'seo_title',
    'seo_description',
    'seo_image_id',
    'seo_canonical',
    'seo_noindex',
])]
class Project extends Model
{
    use HasContentImage, HasFactory, HasSeo, HasSlug;

    protected $attributes = [
        'status' => 'draft',
        'is_featured' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'completed_at' => 'date',
            'seo_noindex' => 'boolean',
        ];
    }

    protected function content(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value !== null ? ContentHtml::sanitize($value) : null
        );
    }

    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->portfolioDescription(),
            set: fn (?string $value): array => [
                'content' => filled($value) ? e($value) : null,
                'short_description' => null,
            ],
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class, 'project_category_id');
    }

    public function galleryImages(): HasMany
    {
        return $this->hasMany(ProjectImage::class)->orderBy('sort_order');
    }

    public function portfolioDescription(): string
    {
        $content = preg_replace('~<(?:br\s*/?|/p|/div|/h[1-6]|/li|/blockquote)>~i', "\n", $this->content ?? '');
        $detail = trim(html_entity_decode(strip_tags($content ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $summary = trim($this->short_description ?? '');

        return $summary !== '' && $summary !== $detail ? trim($summary."\n\n".$detail) : $detail;
    }

    public function safeExternalUrl(): ?string
    {
        $url = $this->external_url;

        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL)
            && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)
            ? $url : null;
    }

    protected static function booted(): void
    {
        static::saved(function (self $project): void {
            static::clearFeaturedCache();
        });

        static::deleted(function (self $project): void {
            static::clearFeaturedCache();
        });

        static::updated(function (self $project): void {
            if ($project->wasChanged('slug')) {
                $oldSlug = (string) $project->getOriginal('slug');
                $newSlug = (string) $project->slug;
                if ($oldSlug !== '' && $newSlug !== '' && $oldSlug !== $newSlug) {
                    $source = '/proyectos/'.$oldSlug;
                    $destination = '/proyectos/'.$newSlug;
                    try {
                        Redirect::query()->updateOrCreate(
                            ['source' => $source],
                            ['destination' => $destination, 'status_code' => 301, 'is_active' => true]
                        );
                    } catch (\Throwable) {
                        // Ignore conflicts safely
                    }
                }
            }
        });
    }

    public static function clearFeaturedCache(): void
    {
        ApplicationCache::forget(ApplicationCache::FEATURED_PROJECTS);
    }

    /**
     * @return Collection<int, Project>
     */
    public static function featuredPublished(int $limit = 3): Collection
    {
        $revision = Cache::memo()->rememberForever(ApplicationCache::FEATURED_PROJECTS, fn (): string => (string) Str::uuid());
        $cacheKey = ApplicationCache::FEATURED_PROJECTS.'.ids.'.$revision.'.'.$limit;

        $ids = Cache::memo()->remember(
            $cacheKey,
            now()->addHour(),
            fn (): array => static::query()
                ->published()
                ->featured()
                ->ordered()
                ->limit($limit)
                ->pluck('id')->all()
        );

        return static::query()->published()->featured()->whereKey($ids)
            ->with(['category', 'image.model'])->ordered()->get();
    }

    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::Published);
    }

    #[Scope]
    protected function featured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('id');
    }

    #[Scope]
    protected function byCategory(Builder $query, int|string|ProjectCategory $category): Builder
    {
        if ($category instanceof ProjectCategory) {
            return $query->where('project_category_id', $category->id);
        }

        if (is_numeric($category)) {
            return $query->where('project_category_id', (int) $category);
        }

        return $query->whereHas('category', fn (Builder $q) => $q->where('slug', $category));
    }

    /**
     * @return Collection<int, Project>
     */
    public function relatedProjects(int $limit = 3): Collection
    {
        $baseQuery = static::query()
            ->published()
            ->whereKeyNot($this->getKey())
            ->with(['category', 'image.model'])
            ->ordered();

        if ($this->project_category_id) {
            $sameCategory = (clone $baseQuery)
                ->where('project_category_id', $this->project_category_id)
                ->limit($limit)
                ->get();

            if ($sameCategory->count() >= $limit) {
                return $sameCategory;
            }

            $needed = $limit - $sameCategory->count();
            $fallback = (clone $baseQuery)
                ->whereNotIn('id', $sameCategory->pluck('id'))
                ->limit($needed)
                ->get();

            return $sameCategory->concat($fallback);
        }

        return $baseQuery->limit($limit)->get();
    }
}
