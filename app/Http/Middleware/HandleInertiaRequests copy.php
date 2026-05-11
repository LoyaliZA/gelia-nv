<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\ConfiguracionUsuario;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $configuracion = $user ? \App\Models\ConfiguracionUsuario::where('user_id', $user->id)->first() : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? array_merge($user->toArray(), [
                    'roles' => $user->getRoleNames(), 
                    'permissions' => $user->getAllPermissions()->pluck('name'), // Permisos atómicos inyectados
                ]) : null,
                'tema_visual' => $configuracion ? $configuracion->tema_visual : null,
                'dashboard_prefs' => $configuracion ? $configuracion->dashboard_prefs : ['ocultos' => []],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
