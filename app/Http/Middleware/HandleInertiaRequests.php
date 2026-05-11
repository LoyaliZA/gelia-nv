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
        
        // 1. Buscamos la configuración del usuario
        $configuracion = $user ? ConfiguracionUsuario::where('user_id', $user->id)->first() : null;

        // 2. Nos aseguramos de decodificar el JSON a un array de PHP. 
        // Si no hay configuración, mandamos un array vacío.
        $temaVisual = [];
        if ($configuracion && !empty($configuracion->tema_visual)) {
            $temaVisual = is_string($configuracion->tema_visual) 
                ? json_decode($configuracion->tema_visual, true) 
                : $configuracion->tema_visual;
        }

        return [
            ...parent::share($request),
            'auth' => [
                // Inyectamos roles, permisos y NOTIFICACIONES dentro de user
                'user' => $user ? array_merge($user->toArray(), [
                    'roles' => $user->getRoleNames(), 
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'notifications' => $user->notifications, // <-- AQUÍ ESTÁ LA MAGIA
                ]) : null,
                
                // Mantenemos tu tema visual independiente y seguro
                'tema_visual' => $temaVisual,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}