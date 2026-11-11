<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function (Model $model): void {
            if (blank($model->slug)) {
                $model->slug = Str::slug($model->getAttribute('title') ?? $model->getAttribute('name'));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
