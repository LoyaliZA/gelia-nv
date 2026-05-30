<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireJsonAccept
{
    public function handle(Request $request, Closure $next): Response
    {
        $accept = (string) $request->header('Accept', '');

        if ($accept !== '' && !str_contains($accept, 'application/json') && !str_contains($accept, '*/*')) {
            return response()->json([
                'message' => 'Se requiere el encabezado Accept: application/json.',
            ], 406);
        }

        return $next($request);
    }
}
