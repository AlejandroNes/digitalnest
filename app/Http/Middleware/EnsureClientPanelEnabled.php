<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientPanelEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('features.client_panel_enabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}
