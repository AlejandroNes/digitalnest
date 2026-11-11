<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use App\Services\InternalUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = '/'.ltrim($request->path(), '/');
        if (in_array($request->method(), ['GET', 'HEAD'], true) && InternalUrl::path($path) && ! InternalUrl::protectedPath($path) && ! Redirect::hasRouteConflict($path)) {
            $redirect = Redirect::query()->where('source', InternalUrl::path($path))->where('is_active', true)->first();
            if ($redirect && in_array($redirect->status_code, [301, 302], true) && ($destination = $redirect->terminalDestination())) {
                return redirect(InternalUrl::absolute($destination), $redirect->status_code);
            }
        }

        return $next($request);
    }
}
