<?php

namespace App\Http\Middleware;

use App\Services\SiteIndexing;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::apply($request, $next($request));
    }

    public static function apply(Request $request, Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Cache-Control', 'private, no-store');
        if (! SiteIndexing::environmentAllows() || $request->is('admin*', 'api*', 'livewire*', 'cookies*', 'up')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
