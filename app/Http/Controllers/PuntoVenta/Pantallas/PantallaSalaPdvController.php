<?php

namespace App\Http\Controllers\PuntoVenta\Pantallas;

use App\Exceptions\PuntoVenta\PantallaSalaInactivaException;
use App\Http\Controllers\Controller;
use App\Services\PuntoVenta\Pantallas\ConsultaEstadoSalaPdvService;
use App\Services\PuntoVenta\Pantallas\ResolverTokenPantallaSalaPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PantallaSalaPdvController extends Controller
{
    public function show(
        int $sucursal,
        ConsultaEstadoSalaPdvService $consulta,
        PuntoVentaModulo $modulo,
    ): Response {
        $this->asegurarModuloHabilitado($modulo);

        return $this->renderSala(
            $sucursal,
            $consulta,
            route('sala_turnos.publica.estado', ['sucursal' => $sucursal], false),
        );
    }

    public function showPorToken(
        string $token,
        ResolverTokenPantallaSalaPdvService $resolver,
        ConsultaEstadoSalaPdvService $consulta,
        PuntoVentaModulo $modulo,
    ): Response {
        $this->asegurarModuloHabilitado($modulo);

        try {
            $registro = $resolver->resolver($token, now(), true);
        } catch (PantallaSalaInactivaException $e) {
            return $this->renderSalaInactiva($e);
        }

        return $this->renderSala(
            (int) $registro->sucursal_id,
            $consulta,
            route('sala_turnos.publica.token.estado', ['token' => $token], false),
        );
    }

    public function estado(
        int $sucursal,
        ConsultaEstadoSalaPdvService $consulta,
        PuntoVentaModulo $modulo,
    ): JsonResponse {
        $this->asegurarModuloHabilitado($modulo);

        return response()->json($consulta->payload($sucursal, now()));
    }

    public function estadoPorToken(
        string $token,
        ResolverTokenPantallaSalaPdvService $resolver,
        ConsultaEstadoSalaPdvService $consulta,
        PuntoVentaModulo $modulo,
    ): JsonResponse {
        $this->asegurarModuloHabilitado($modulo);

        try {
            $registro = $resolver->resolver($token, now());
        } catch (PantallaSalaInactivaException) {
            return response()->json([
                'activa' => false,
                'mensaje' => 'La pantalla de sala está desactivada.',
            ], 403);
        }

        return response()->json($consulta->payload((int) $registro->sucursal_id, now()));
    }

    private function renderSala(int $sucursalId, ConsultaEstadoSalaPdvService $consulta, string $urlEstado): Response
    {
        $estado = $consulta->payload($sucursalId, now());

        return Inertia::render('PuntoVenta/Pantallas/Sala', [
            'estado_inicial' => fn () => $estado,
            'sucursal_id' => $sucursalId,
            'url_estado' => $urlEstado,
        ]);
    }

    private function renderSalaInactiva(PantallaSalaInactivaException $e): Response
    {
        return Inertia::render('PuntoVenta/Pantallas/SalaInactiva', [
            'sucursal_id' => (int) $e->registro->sucursal_id,
        ]);
    }

    private function asegurarModuloHabilitado(PuntoVentaModulo $modulo): void
    {
        if (! $modulo->habilitado()) {
            throw new NotFoundHttpException();
        }
    }
}
