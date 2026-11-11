<?php

namespace App\Models\Concerns;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates 301 redirects from the old public paths when a published model's slug changes.
 */
trait RedirectsChangedSlug
{
    /**
     * @return list<string>
     */
    abstract public static function publicPathsForSlug(string $slug): array;

    public static function bootRedirectsChangedSlug(): void
    {
        static::updated(function (Model $model): void {
            $oldSlug = (string) $model->getOriginal('slug');
            $newSlug = (string) $model->getAttribute('slug');
            if (! $model->wasChanged('slug') || $oldSlug === '' || $newSlug === '') {
                return;
            }
            foreach (array_combine(static::publicPathsForSlug($oldSlug), static::publicPathsForSlug($newSlug)) as $source => $destination) {
                try {
                    Redirect::query()->updateOrCreate(
                        ['source' => $source],
                        ['destination' => $destination, 'status_code' => 301, 'is_active' => true]
                    );
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        });
    }
}
