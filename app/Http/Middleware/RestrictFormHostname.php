<?php

namespace App\Http\Middleware;

use App\Support\FormPublicUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictFormHostname
{
    public function handle(Request $request, Closure $next): Response
    {
        $formHost = FormPublicUrl::host();
        if ($formHost === null) {
            return $next($request);
        }

        if (strcasecmp($request->getHost(), $formHost) !== 0) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if (is_string($routeName) && str_starts_with($routeName, 'direcciones.publicas.')) {
            return $next($request);
        }

        abort(404);
    }
}
