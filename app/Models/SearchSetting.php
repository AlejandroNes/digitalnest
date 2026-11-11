<?php

namespace App\Models;

use App\Services\ApplicationCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['default_title', 'default_description', 'og_image_id', 'indexable', 'analytics_enabled', 'measurement_id', 'verification_token', 'cookies_enabled', 'cookie_message', 'verification_enabled', 'tag_manager_enabled', 'tag_manager_id', 'sitemap_enabled'])]
class SearchSetting extends Model
{
    use HasFactory;

    protected $attributes = ['indexable' => false, 'analytics_enabled' => false, 'cookies_enabled' => true, 'consent_version' => 1];

    protected function casts(): array
    {
        return ['indexable' => 'boolean', 'analytics_enabled' => 'boolean', 'cookies_enabled' => 'boolean', 'consent_version' => 'integer', 'verification_enabled' => 'boolean', 'tag_manager_enabled' => 'boolean', 'sitemap_enabled' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            ApplicationCache::forget(ApplicationCache::SEARCH);
            ApplicationCache::invalidateSitemap();
        });
        static::deleted(fn () => ApplicationCache::forget(ApplicationCache::SEARCH));
        static::updating(function (self $setting): void {
            if ($setting->isDirty(['measurement_id', 'analytics_enabled', 'cookies_enabled', 'cookie_message', 'tag_manager_enabled', 'tag_manager_id'])) {
                $setting->consent_version = $setting->getOriginal('consent_version') + 1;
            }
        });
    }

    public static function current(): self
    {
        $attributes = Cache::memo()->remember(ApplicationCache::SEARCH, 300, fn (): array => (static::query()->find(1) ?? new static)->getAttributes());
        $settings = new static;
        $settings->setRawAttributes($attributes, true);
        $settings->exists = isset($attributes['id']);

        return $settings;
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_id');
    }

    public function validMeasurementId(): ?string
    {
        return is_string($this->measurement_id) && preg_match('/^G-[A-Z0-9]{4,20}$/D', $this->measurement_id) ? $this->measurement_id : null;
    }

    public function validVerificationToken(): ?string
    {
        return $this->verification_enabled !== false && is_string($this->verification_token) && preg_match('/^[A-Za-z0-9_-]{1,255}$/D', $this->verification_token) ? $this->verification_token : null;
    }

    public function validTagManagerId(): ?string
    {
        return is_string($this->tag_manager_id) && preg_match('/^GTM-[A-Z0-9]{4,20}$/D', $this->tag_manager_id) ? $this->tag_manager_id : null;
    }

    public function usesTagManager(): bool
    {
        return $this->tag_manager_enabled && $this->validTagManagerId() !== null;
    }

    public static function supportsIntegrations(): bool
    {
        return once(fn (): bool => Schema::hasColumn('search_settings', 'sitemap_enabled') && Schema::hasTable('custom_scripts'));
    }

    public static function renewConsent(): void
    {
        static::query()->firstOrCreate(['id' => 1]);
        static::query()->whereKey(1)->increment('consent_version');
        ApplicationCache::forget(ApplicationCache::SEARCH);
    }
}
