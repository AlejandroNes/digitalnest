<?php

namespace App\Models\Concerns;

use App\PublicationStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Draft/published state with an automatic publication date, for Workflow and Template.
 */
trait HasPublication
{
    public static function bootHasPublication(): void
    {
        static::saving(function (Model $model): void {
            if ($model->getAttribute('status') === PublicationStatus::Published && $model->getAttribute('published_at') === null) {
                $model->setAttribute('published_at', now());
            }
        });
    }

    public function isPublished(): bool
    {
        return $this->status === PublicationStatus::Published && $this->published_at !== null && $this->published_at->lte(now());
    }

    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), PublicationStatus::Published)
            ->whereNotNull($query->qualifyColumn('published_at'))
            ->where($query->qualifyColumn('published_at'), '<=', now());
    }

    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderByDesc($query->qualifyColumn('is_featured'))
            ->orderBy($query->qualifyColumn('sort_order'))
            ->orderByDesc($query->qualifyColumn('published_at'));
    }
}
