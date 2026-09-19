<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebAuthnEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('webauthn.enabled')) {
            return response()->json([
                'message' => 'El acceso con huella no está disponible.',
            ], 404);
        }

        return $next($request);
    }
}
