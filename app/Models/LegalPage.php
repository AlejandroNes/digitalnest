<?php

namespace App\Models;

use App\LegalPageType;
use App\Services\ApplicationCache;
use App\Services\ContentHtml;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'content', 'is_published'])]
class LegalPage extends Model
{
    use HasFactory;

    protected $attributes = ['is_published' => false, 'revision' => 1];

    protected static function booted(): void
    {
        static::saved(fn () => ApplicationCache::forget(ApplicationCache::PRIVACY_REVISION));
        static::deleted(fn () => ApplicationCache::forget(ApplicationCache::PRIVACY_REVISION));
        static::saved(fn () => ApplicationCache::invalidateSitemap());
        static::deleted(fn () => ApplicationCache::invalidateSitemap());
        static::saving(function (self $page): void {
            if (! $page->exists || $page->isDirty('content')) {
                $page->content_updated_at = now();
                $page->revision = $page->exists ? $page->revision + 1 : 1;
            }
        });
    }

    protected function casts(): array
    {
        return ['type' => LegalPageType::class, 'is_published' => 'boolean', 'revision' => 'integer', 'content_updated_at' => 'datetime'];
    }

    protected function content(): Attribute
    {
        return Attribute::make(set: fn (?string $value): string => ContentHtml::sanitize($value));
    }

    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    protected function title(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->type->getLabel());
    }

    public function getRouteKey(): string
    {
        return $this->type->value;
    }

    public function getRouteKeyName(): string
    {
        return 'type';
    }
}
