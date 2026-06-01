<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\ConfiguracionUsuario;
use App\Services\PersonalizacionCatalogoService;
use App\Services\Mensajeria\ListarConversacionesService;
use App\Services\Presencia\PresenciaUsuarioService;
use App\Support\PresenciaCatalogo;

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

        $presenciaAuth = null;
        if ($user) {
            try {
                $presenciaAuth = array_merge(
                    app(PresenciaUsuarioService::class)->formatear($user),
                    ['user_id' => $user->id]
                );
            } catch (\Throwable) {
                $presenciaAuth = null;
            }
        }

        $temaVisual = [];
        if ($configuracion && !empty($configuracion->tema_visual)) {
            $temaVisual = is_string($configuracion->tema_visual) 
                ? json_decode($configuracion->tema_visual, true) 
                : $configuracion->tema_visual;
        }

        $tonosAlertas = PersonalizacionCatalogoService::tonosActivos();

        return [
            ...parent::share($request),
            'tonos_alertas' => $tonosAlertas,
            'catalogo_fondos' => PersonalizacionCatalogoService::fondosActivos(),
            'catalogo_temas'  => PersonalizacionCatalogoService::temasActivos(),
            'auth' => [
                'user' => $user ? array_merge($user->toArray(), [
                    'roles' => $user->getRoleNames(), 
                    'permissions' => $user->getAllPermissions()->pluck('name'), 
                ]) : null,
                'tema_visual' => $temaVisual,
                
                'notificaciones' => $user
                    ? $user->notifications()->orderByDesc('created_at')->take(50)->get()
                    : [],
                'mensajeria_resumen' => $user
                    ? app(ListarConversacionesService::class)->resumen($user, 8)
                    : null,
                'presencia' => $presenciaAuth,
                'presencia_catalogo' => PresenciaCatalogo::estados(),
                'webpush' => [
                    'enabled' => (bool) config('webpush.enabled', true) && (bool) config('webpush.vapid.public_key'),
                    'public_key' => config('webpush.vapid.public_key'),
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'activo_registrado' => fn () => $request->session()->get('activo_registrado'),
                // Permite el paso del reporte hacia React
                'reporte_importacion' => fn () => $request->session()->get('reporte_importacion'),
            ],
        ];
    }
}