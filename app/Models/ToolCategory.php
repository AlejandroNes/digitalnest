<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Services\ApplicationCache;
use Database\Factories\ToolCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'is_active', 'sort_order', 'seo_title', 'seo_description', 'seo_noindex'])]
class ToolCategory extends Model
{
    /** @use HasFactory<ToolCategoryFactory> */
    use HasFactory, HasSlug;

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
        'seo_noindex' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'seo_noindex' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => ApplicationCache::invalidateSitemap());
        static::deleted(fn () => ApplicationCache::invalidateSitemap());
    }

    /**
     * Tools whose primary category is this one.
     */
    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class, 'tool_category_id');
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class, 'tool_category_id');
    }

    /**
     * Tools listed here as a secondary category.
     */
    public function secondaryTools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class, 'tool_tool_category');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
