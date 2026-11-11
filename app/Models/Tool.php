<?php

namespace App\Models;

use App\Models\Concerns\HasContentImage;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasSlug;
use App\Services\ContentHtml;
use App\Services\InternalUrl;
use App\ToolPlatform;
use App\ToolPricingModel;
use App\ToolSpanishSupport;
use App\ToolStatus;
use Database\Factories\ToolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'name',
    'slug',
    'tagline',
    'logo_id',
    'image_id',
    'tool_category_id',
    'website_url',
    'affiliate_url',
    'best_for',
    'not_for',
    'review',
    'pros',
    'cons',
    'pricing_model',
    'has_free_plan',
    'free_plan_notes',
    'starting_price_usd',
    'requires_card',
    'spanish_support',
    'latam_payments',
    'platforms',
    'privacy_notes',
    'deal_notes',
    'deal_expires_at',
    'tested_at',
    'reviewed_at',
    'rating',
    'status',
    'published_at',
    'is_featured',
    'sort_order',
    'seo_title',
    'seo_description',
    'seo_image_id',
    'seo_canonical',
    'seo_noindex',
])]
class Tool extends Model
{
    /** @use HasFactory<ToolFactory> */
    use HasContentImage, HasFactory, HasSeo, HasSlug;

    protected $attributes = [
        'status' => 'draft',
        'has_free_plan' => false,
        'requires_card' => false,
        'is_featured' => false,
        'sort_order' => 0,
        'seo_noindex' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => ToolStatus::class,
            'pricing_model' => ToolPricingModel::class,
            'spanish_support' => ToolSpanishSupport::class,
            'pros' => 'array',
            'cons' => 'array',
            'platforms' => 'array',
            'has_free_plan' => 'boolean',
            'requires_card' => 'boolean',
            'latam_payments' => 'boolean',
            'starting_price_usd' => 'decimal:2',
            'deal_expires_at' => 'date',
            'tested_at' => 'date',
            'reviewed_at' => 'date',
            'rating' => 'integer',
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'seo_noindex' => 'boolean',
        ];
    }

    protected function review(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value !== null ? ContentHtml::sanitize($value) : null
        );
    }

    protected static function booted(): void
    {
        static::saving(function (self $tool): void {
            if ($tool->status === ToolStatus::Published && $tool->published_at === null) {
                $tool->published_at = now();
            }
        });

        static::updated(function (self $tool): void {
            $oldSlug = (string) $tool->getOriginal('slug');
            $newSlug = (string) $tool->slug;
            if (! $tool->wasChanged('slug') || $oldSlug === '' || $newSlug === '') {
                return;
            }
            foreach (['', '/alternativas'] as $suffix) {
                try {
                    Redirect::query()->updateOrCreate(
                        ['source' => '/herramientas/'.$oldSlug.$suffix],
                        ['destination' => '/herramientas/'.$newSlug.$suffix, 'status_code' => 301, 'is_active' => true]
                    );
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class, 'tool_category_id');
    }

    public function secondaryCategories(): BelongsToMany
    {
        return $this->belongsToMany(ToolCategory::class, 'tool_tool_category');
    }

    public function alternatives(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'tool_alternatives', 'tool_id', 'alternative_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_id');
    }

    public function logoUrl(): ?string
    {
        return MediaAsset::mediaUrl($this->logo);
    }

    /**
     * The outbound link for the "Visitar" button: the affiliate URL when it is a valid http(s) URL, otherwise the website.
     */
    public function outboundUrl(): ?string
    {
        return self::safeUrl($this->affiliate_url) ?? self::safeUrl($this->website_url);
    }

    public function isAffiliateLink(): bool
    {
        return self::safeUrl($this->affiliate_url) !== null;
    }

    public function hasActiveDeal(): bool
    {
        return filled($this->deal_notes) && ($this->deal_expires_at === null || $this->deal_expires_at->gte(today()));
    }

    /**
     * @return list<ToolPlatform>
     */
    public function platformList(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $value): ?ToolPlatform => is_string($value) ? ToolPlatform::tryFrom($value) : null,
            $this->platforms ?? []
        )));
    }

    /**
     * @return list<string>
     */
    public function prosList(): array
    {
        return self::textList($this->pros);
    }

    /**
     * @return list<string>
     */
    public function consList(): array
    {
        return self::textList($this->cons);
    }

    /**
     * Schema.org SoftwareApplication data with the editorial review.
     *
     * @return array<string, mixed>
     */
    public function structuredData(string $url, string $publisher): array
    {
        $platforms = array_map(fn (ToolPlatform $platform): string => $platform->getLabel(), $this->platformList());
        $logo = $this->logoUrl();
        if ($logo && ! preg_match('~^https?://~i', $logo)) {
            $logo = InternalUrl::absolute($logo);
        }
        $price = $this->pricing_model?->isFree() ? '0' : ($this->starting_price_usd !== null ? (string) $this->starting_price_usd : null);

        $data = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $this->name,
            'description' => $this->tagline,
            'url' => $url,
            'image' => $logo,
            'applicationCategory' => $this->category?->name,
            'operatingSystem' => $platforms !== [] ? implode(', ', $platforms) : null,
            'offers' => $price !== null ? ['@type' => 'Offer', 'price' => $price, 'priceCurrency' => 'USD'] : null,
        ], fn (mixed $value): bool => $value !== null);

        if ($this->rating !== null) {
            $data['review'] = array_filter([
                '@type' => 'Review',
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $this->rating, 'bestRating' => 10, 'worstRating' => 1],
                'author' => ['@type' => 'Organization', 'name' => $publisher],
                'datePublished' => $this->tested_at?->toDateString(),
            ], fn (mixed $value): bool => $value !== null);
        }

        return $data;
    }

    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('status', ToolStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('name');
    }

    #[Scope]
    protected function inCategory(Builder $query, ToolCategory $category): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->where('tool_category_id', $category->id)
            ->orWhereHas('secondaryCategories', fn (Builder $query): Builder => $query->whereKey($category->id)));
    }

    private static function safeUrl(?string $url): ?string
    {
        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL)
            && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)
            ? $url : null;
    }

    /**
     * @return list<string>
     */
    private static function textList(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): string => trim(is_array($item) ? (string) ($item['text'] ?? '') : (string) $item),
            $items
        ), fn (string $text): bool => $text !== ''));
    }
}
