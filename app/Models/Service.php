<?php

namespace App\Models;

use App\Models\Concerns\HasContentImage;
use App\Models\Concerns\HasOrderedActivity;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasSlug;
use App\Services\ContentHtml;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'summary', 'description', 'image_id', 'seo_title', 'seo_description', 'seo_image_id', 'seo_canonical', 'seo_noindex', 'sort_order', 'is_active'])]
class Service extends Model
{
    use HasContentImage, HasFactory, HasOrderedActivity, HasSeo, HasSlug;

    protected $attributes = ['sort_order' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return ['seo_noindex' => 'boolean', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    protected function description(): Attribute
    {
        return Attribute::make(set: fn (?string $value): string => ContentHtml::sanitize($value));
    }
}
