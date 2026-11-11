<?php

namespace App\Http\Middleware;

use App\Models\GeneralSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySiteSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = GeneralSetting::current(false);

        if ($settings->maintenance_enabled) {
            $body = $request->expectsJson() || $request->hasHeader('X-Livewire')
                ? 'El sitio está en mantenimiento. Inténtalo de nuevo más tarde.'
                : view('errors.503');
            abort(response($body, 503)->header('Retry-After', '3600'));
        }

        $originalLocale = app()->getLocale();
        $originalTimezone = config('app.timezone');
        $originalPhpTimezone = date_default_timezone_get();

        try {
            app()->setLocale($settings->locale);
            config(['app.timezone' => $settings->timezone]);
            date_default_timezone_set($settings->timezone);

            return $next($request);
        } finally {
            app()->setLocale($originalLocale);
            config(['app.timezone' => $originalTimezone]);
            date_default_timezone_set($originalPhpTimezone);
        }
    }
}
