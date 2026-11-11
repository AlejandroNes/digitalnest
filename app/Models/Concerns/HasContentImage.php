<?php

namespace App\Models\Concerns;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait HasContentImage
{
    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_id');
    }

    public function imageUrl(): ?string
    {
        return MediaAsset::mediaUrl($this->image);
    }

    public function imageAlt(): ?string
    {
        return $this->image?->model?->alt_text;
    }
}
