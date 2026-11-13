<?php

namespace App\Models;

use App\DealType;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\RedirectsChangedSlug;
use App\PublicationStatus;
use App\Services\ApplicationCache;
use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tool_id',
    'type',
    'title',
    'slug',
    'description',
    'code',
    'discount_percent',
    'original_price',
    'deal_price',
    'currency',
    'url',
    'starts_at',
    'expires_at',
    'verified_at',
    'is_exclusive',
    'is_featured',
    'status',
    'published_at',
    'seo_title',
    'seo_description',
])]
class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use HasFactory, HasSlug, RedirectsChangedSlug;

    protected $attributes = [
        'status' => 'draft',
        'currency' => 'USD',
        'is_exclusive' => false,
        'is_featured' => false,
        'clicks' => 0,
    ];

    /**
     * @return list<string>
     */
    public static function publicPathsForSlug(string $slug): array
    {
        return ['/oferta/'.$slug];
    }

    protected function casts(): array
    {
        return [
            'type' => DealType::class,
            'status' => PublicationStatus::class,
            'discount_percent' => 'integer',
            'original_price' => 'decimal:2',
            'deal_price' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'published_at' => 'datetime',
            'is_exclusive' => 'boolean',
            'is_featured' => 'boolean',
            'clicks' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => ApplicationCache::invalidateSitemap());
        static::deleted(fn () => ApplicationCache::invalidateSitemap());

        static::saving(function (self $deal): void {
            if ($deal->status === PublicationStatus::Published && $deal->published_at === null) {
                $deal->published_at = now();
            }
            if ($deal->discount_percent === null && (float) $deal->original_price > 0 && $deal->deal_price !== null) {
                $deal->discount_percent = (int) round((1 - (float) $deal->deal_price / (float) $deal->original_price) * 100);
            }
        });
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Price label such as "$20", "$2.50" or "19,99 €", or null when no amount is given.
     */
    public function priceLabel(mixed $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }
        $value = (float) $amount;
        $decimals = fmod($value, 1.0) === 0.0 ? 0 : 2;

        return $this->currency === 'EUR'
            ? number_format($value, $decimals, ',', '.').' €'
            : '$'.number_format($value, $decimals);
    }

    /**
     * Schema.org Offer for the deal page.
     *
     * @return array<string, mixed>
     */
    public function structuredData(string $url): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Offer',
            'name' => $this->title,
            'description' => $this->description,
            'url' => $url,
            'price' => $this->deal_price !== null ? (string) $this->deal_price : null,
            'priceCurrency' => $this->deal_price !== null ? $this->currency : null,
            'availability' => $this->isExpired() ? 'https://schema.org/Discontinued' : 'https://schema.org/InStock',
            'validFrom' => $this->starts_at?->toAtomString(),
            'priceValidUntil' => $this->expires_at?->toDateString(),
            'itemOffered' => $this->tool ? ['@type' => 'SoftwareApplication', 'name' => $this->tool->name] : null,
        ], fn (mixed $value): bool => $value !== null);
    }

    public function hasCode(): bool
    {
        return filled($this->code);
    }

    /**
     * The link used by the outbound redirect: the deal URL when valid, otherwise the tool's outbound link.
     */
    public function outboundUrl(): ?string
    {
        $isSafe = is_string($this->url) && filter_var($this->url, FILTER_VALIDATE_URL)
            && in_array(strtolower(parse_url($this->url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true);

        return $isSafe ? $this->url : $this->tool?->outboundUrl();
    }

    /**
     * Published, started and not expired deals of published tools.
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', PublicationStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $query): Builder => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('tool', fn (Builder $query): Builder => $query->published());
    }

    /**
     * Published deals of published tools, including expired ones (their pages stay reachable).
     */
    #[Scope]
    protected function visible(Builder $query): Builder
    {
        return $query->where('status', PublicationStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('tool', fn (Builder $query): Builder => $query->published());
    }

    /**
     * Deals whose tool belongs, as primary or secondary category, to any of the given sectors.
     *
     * @param  array<int, int>  $sectorIds
     */
    #[Scope]
    protected function inSectors(Builder $query, array $sectorIds): Builder
    {
        return $query->whereHas('tool', fn (Builder $query): Builder => $query
            ->whereIn('tool_category_id', $sectorIds)
            ->orWhereHas('secondaryCategories', fn (Builder $query): Builder => $query->whereIn('tool_categories.id', $sectorIds)));
    }

    #[Scope]
    protected function expiringWithin(Builder $query, int $days): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays($days));
    }

    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')->orderByDesc('published_at')->orderByDesc('id');
    }
}
