<?php

namespace App\Http\Controllers\PuntoVenta\VisitasProgramadas;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\VisitasProgramadas\ConfirmarLlegadaVisitaProgramadaPdvRequest;
use App\Models\Comercial\VisitaClienteProgramada;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Comercial\VisitasProgramadas\ConfirmarLlegadaVisitaProgramadaService;
use App\Services\Comercial\VisitasProgramadas\ListarVisitasProgramadasService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BandejaVisitasProgramadasPdvController extends Controller
{
    public function index(
        Request $request,
        ResuelveAlcancePdv $alcance,
        ListarVisitasProgramadasService $listar,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $ahora = now();
        $sucursalId = $alcance->sucursalActivaId($user);

        return Inertia::render('PuntoVenta/VisitasProgramadas/Index', [
            'bandeja' => fn () => $sucursalId
                ? $listar->bandejaPdvDia($user, $sucursalId, $ahora)
                : ['servidor_at' => $ahora->toIso8601String(), 'visitas' => []],
            'permisos' => fn () => [
                'ver' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_VER),
                'confirmar_llegada' => $alcance->tienePermisoPdv(
                    $user,
                    PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_CONFIRMAR_LLEGADA
                ),
            ],
            'sucursal_activa' => fn () => $this->serializarSucursalActiva($user, $alcance),
            'sucursales_asignadas' => fn () => $this->serializarSucursalesAsignadas($user),
        ]);
    }

    public function datos(
        Request $request,
        ResuelveAlcancePdv $alcance,
        ListarVisitasProgramadasService $listar,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $sucursalId = $alcance->sucursalActivaId($user);

        if (! $sucursalId) {
            return response()->json([
                'servidor_at' => now()->toIso8601String(),
                'visitas' => [],
            ]);
        }

        return response()->json($listar->bandejaPdvDia($user, $sucursalId, now()));
    }

    public function confirmarLlegada(
        ConfirmarLlegadaVisitaProgramadaPdvRequest $request,
        VisitaClienteProgramada $visitaClienteProgramada,
        ResuelveAlcancePdv $alcance,
        ConfirmarLlegadaVisitaProgramadaService $confirmar,
    ): RedirectResponse|JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $sucursalId = $alcance->sucursalActivaId($user);

        if (! $sucursalId) {
            abort(409, 'Sucursal activa requerida.');
        }

        $confirmar->handle(
            $user,
            $visitaClienteProgramada,
            $sucursalId,
            $request->validated('idempotency_key'),
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Llegada registrada.']);
        }

        return back()->with('success', 'Llegada registrada.');
    }

    /**
     * @return array{id: int, nombre: string}|null
     */
    private function serializarSucursalActiva(User $user, ResuelveAlcancePdv $alcance): ?array
    {
        $id = $alcance->sucursalActivaId($user);
        if (! $id) {
            return null;
        }

        $sucursal = Sucursal::query()->find($id, ['id', 'nombre']);

        return $sucursal ? ['id' => $sucursal->id, 'nombre' => $sucursal->nombre] : null;
    }

    /**
     * @return list<array{id: int, nombre: string}>
     */
    private function serializarSucursalesAsignadas(User $user): array
    {
        $user->loadMissing('sucursales');

        return $user->sucursales
            ->filter(static fn (Sucursal $sucursal): bool => $sucursal->activo && (bool) $sucursal->pivot->activo)
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(static fn (Sucursal $sucursal): array => [
                'id' => $sucursal->id,
                'nombre' => $sucursal->nombre,
            ])
            ->values()
            ->all();
    }
}
