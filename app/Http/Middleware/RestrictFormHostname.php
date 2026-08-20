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

        // En local (Sail) FORM_PUBLIC_URL suele coincidir con APP_URL: no aplicar lockdown.
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '' && strcasecmp($formHost, $appHost) === 0) {
            return $next($request);
        }

        if (strcasecmp($request->getHost(), $formHost) !== 0) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if (is_string($routeName) && (
            str_starts_with($routeName, 'direcciones.publicas.')
            || str_starts_with($routeName, 'datos_fiscales.publicas.')
            || str_starts_with($routeName, 'cedis_evidencia.publicas.')
        )) {
            return $next($request);
        }

        abort(404);
    }
}
