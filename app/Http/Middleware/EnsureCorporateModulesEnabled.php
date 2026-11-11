<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCorporateModulesEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('features.corporate_modules_enabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}
