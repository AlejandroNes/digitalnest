<?php

namespace App\Models\Concerns;

use App\Services\ApplicationCache;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait HasSeo
{
    public static function bootHasSeo(): void
    {
        static::saved(fn () => ApplicationCache::invalidateSitemap());
        static::deleted(fn () => ApplicationCache::invalidateSitemap());
    }

    public function seoImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'seo_image_id');
    }
}
