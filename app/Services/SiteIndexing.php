<?php

namespace App\Services;

use App\Models\GeneralSetting;
use App\Models\SearchSetting;

class SiteIndexing
{
    public static function environmentAllows(): bool
    {
        $url = config('app.url');
        $host = is_string($url) ? strtolower(parse_url($url, PHP_URL_HOST) ?? '') : '';
        if (! app()->isProduction() || ! is_string($url) || strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            || parse_url($url, PHP_URL_QUERY) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null
            || preg_match('/(?:^|\.)(?:localhost|local|test|internal|invalid|example)$/D', $host)
            || preg_match('/^(?:dev|staging|stage|preview|test)\./', $host)) {
            return false;
        }
        $address = trim($host, '[]');
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return str_contains($host, '.') && (bool) filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
    }

    public static function allows(?SearchSetting $settings = null, ?GeneralSetting $general = null): bool
    {
        return self::environmentAllows() && ($settings ?? SearchSetting::current())->indexable
            && ! ($general ?? GeneralSetting::current(false))->maintenance_enabled;
    }
}
