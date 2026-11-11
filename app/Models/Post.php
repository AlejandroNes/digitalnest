<?php

namespace App\Models;

use App\Models\Concerns\HasContentImage;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasSlug;
use App\PostStatus;
use App\Services\ContentHtml;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['title', 'slug', 'excerpt', 'content', 'image_id', 'seo_title', 'seo_description', 'seo_image_id', 'seo_canonical', 'seo_noindex', 'status', 'published_at'])]
class Post extends Model
{
    use HasContentImage, HasFactory, HasSeo, HasSlug;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['seo_noindex' => 'boolean', 'status' => PostStatus::class, 'published_at' => 'datetime'];
    }

    protected function content(): Attribute
    {
        return Attribute::make(set: fn (?string $value): string => ContentHtml::sanitize($value));
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(PostCategory::class, 'post_category_post');
    }

    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    #[Scope]
    protected function newest(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }
}
