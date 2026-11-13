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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'name', 'slug', 'description', 'icon', 'accent_color', 'is_active', 'sort_order', 'seo_title', 'seo_description', 'seo_noindex'])]
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function rankings(): HasMany
    {
        return $this->hasMany(Ranking::class, 'tool_category_id');
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

    /**
     * This sector's id plus the ids of its active subcategories.
     *
     * @return array<int, int>
     */
    public function familyIds(): array
    {
        return [$this->id, ...$this->children()->active()->pluck('id')->all()];
    }

    #[Scope]
    protected function roots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
