<?php

namespace App\Models;

use App\Services\ApplicationCache;
use Database\Factories\GeneralSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['company_name', 'description', 'logo', 'logo_dark', 'favicon', 'logo_media_id', 'logo_dark_media_id', 'favicon_media_id', 'email', 'phone', 'whatsapp', 'address', 'social_links', 'locale', 'timezone', 'maintenance_enabled'])]
class GeneralSetting extends Model
{
    /** @use HasFactory<GeneralSettingFactory> */
    use HasFactory;

    protected $attributes = [
        'company_name' => 'Mi empresa',
        'locale' => 'es',
        'timezone' => 'UTC',
        'maintenance_enabled' => false,
    ];

    private const PUBLIC_ATTRIBUTES = ['id', 'company_name', 'description', 'logo', 'logo_dark', 'favicon', 'locale', 'timezone', 'maintenance_enabled', 'created_at', 'updated_at'];

    private const CONTACT_ATTRIBUTES = ['email', 'phone', 'whatsapp', 'address', 'social_links'];

    protected static function booted(): void
    {
        static::saved(fn () => ApplicationCache::forget(ApplicationCache::GENERAL));
        static::deleted(fn () => ApplicationCache::forget(ApplicationCache::GENERAL));
        static::saved(fn () => ApplicationCache::invalidateSitemap());
        static::deleted(fn () => ApplicationCache::invalidateSitemap());
    }

    public static function current(bool $includeContactDetails = true): self
    {
        try {
            $publicAttributes = [...self::PUBLIC_ATTRIBUTES, ...(self::hasMediaReferences() ? ['logo_media_id', 'logo_dark_media_id', 'favicon_media_id'] : [])];
            $loadAttributes = fn (): array => (static::query()->select($publicAttributes)->find(1) ?? new static)->getAttributes();
            $cache = Cache::memo();
            $attributes = $cache->remember(ApplicationCache::GENERAL, 300, $loadAttributes);
            if (! is_array($attributes) || array_diff(array_keys($attributes), $publicAttributes)) {
                $cache->forget(ApplicationCache::GENERAL);
                $attributes = $cache->remember(ApplicationCache::GENERAL, 300, $loadAttributes);
            }
            if ($includeContactDetails && isset($attributes['id'])) {
                $attributes = array_merge($attributes, static::query()->select(self::CONTACT_ATTRIBUTES)->find(1)?->getAttributes() ?? []);
            }
            $settings = new static;
            $settings->setRawAttributes($attributes, true);
            $settings->exists = isset($attributes['id']);

            return $settings;
        } catch (QueryException $e) {
            return new static;
        }
    }

    public function imageUrl(string $attribute): ?string
    {
        if (! in_array($attribute, ['logo', 'logo_dark', 'favicon'], true)) {
            return null;
        }

        if ($this->getAttribute($attribute.'_media_id')) {
            return MediaAsset::mediaUrl($this->{match ($attribute) {
                'logo' => 'logoMedia', 'logo_dark' => 'logoDarkMedia', 'favicon' => 'faviconMedia'
            }}, '');
        }
        $path = $this->getAttribute($attribute);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function hasMediaReferences(): bool
    {
        return once(fn (): bool => Schema::hasColumn('general_settings', 'logo_media_id'));
    }

    /** @return BelongsTo<Media, $this> */
    public function logoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function logoDarkMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_dark_media_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function faviconMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'favicon_media_id');
    }

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'maintenance_enabled' => 'boolean',
        ];
    }
}
