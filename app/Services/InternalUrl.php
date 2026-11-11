<?php

namespace App\Services;

class InternalUrl
{
    public static function path(?string $value): ?string
    {
        if (! is_string($value) || strlen($value) > 255 || ! preg_match('~^/(?:[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*)?/?$~D', $value)) {
            return null;
        }

        return $value === '/' ? '/' : rtrim($value, '/');
    }

    public static function protectedPath(string $path): bool
    {
        $first = strtolower(explode('/', trim($path, '/'))[0]);

        return in_array($first, ['admin', 'api', 'livewire', 'up', 'cookies', 'build', 'storage', 'vendor', 'sitemap', 'robots'], true)
            || str_contains($path, '.');
    }

    public static function absolute(string $path): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
