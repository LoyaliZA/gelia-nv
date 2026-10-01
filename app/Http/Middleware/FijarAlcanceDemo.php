<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Demo\AlcanceDemo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FijarAlcanceDemo
{
    public function __construct(
        private readonly AlcanceDemo $alcance,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $this->alcance->fijarDesdeUsuario($user instanceof User ? $user : null);

        return $next($request);
    }
}
