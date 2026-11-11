<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplicationCache
{
    public const GENERAL = 'general_settings';

    public const SEARCH = 'corporate.search_settings';

    public const PRIVACY_REVISION = 'corporate.privacy_revision';

    public const FEATURED_PROJECTS = 'corporate.featured_projects';

    public const SCRIPTS = 'corporate.custom_scripts';

    public const SITEMAP_REVISION = 'corporate.sitemap_revision';

    public static function invalidateSitemap(): void
    {
        Cache::memo()->forever(self::SITEMAP_REVISION, (string) Str::uuid());
        if (DB::connection()->transactionLevel() > 0) {
            DB::afterCommit(fn () => Cache::memo()->forever(self::SITEMAP_REVISION, (string) Str::uuid()));
            DB::afterRollBack(fn () => Cache::memo()->forever(self::SITEMAP_REVISION, (string) Str::uuid()));
        }
    }

    public static function forget(string $key): void
    {
        Cache::memo()->forget($key);
        if (DB::connection()->transactionLevel() > 0) {
            DB::afterCommit(fn () => Cache::memo()->forget($key));
            DB::afterRollBack(fn () => Cache::memo()->forget($key));
        }
    }

    public static function clear(): void
    {
        foreach ([self::GENERAL, self::SEARCH, self::PRIVACY_REVISION, self::FEATURED_PROJECTS, self::SCRIPTS] as $key) {
            self::forget($key);
        }
        self::invalidateSitemap();
    }
}
