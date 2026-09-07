<?php

namespace App\Http\Controllers\PuntoVenta\Pantallas;

use App\Http\Controllers\Controller;
use App\Services\PuntoVenta\Pantallas\ConsultaEstadoSalaPdvService;
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

        $estado = $consulta->payload($sucursal, now());

        return Inertia::render('PuntoVenta/Pantallas/Sala', [
            'estado_inicial' => fn () => $estado,
            'sucursal_id' => $sucursal,
            'url_estado' => route('sala_turnos.publica.estado', ['sucursal' => $sucursal]),
        ]);
    }

    public function estado(
        int $sucursal,
        ConsultaEstadoSalaPdvService $consulta,
        PuntoVentaModulo $modulo,
    ): JsonResponse {
        $this->asegurarModuloHabilitado($modulo);

        return response()->json($consulta->payload($sucursal, now()));
    }

    private function asegurarModuloHabilitado(PuntoVentaModulo $modulo): void
    {
        if (! $modulo->habilitado()) {
            throw new NotFoundHttpException();
        }
    }
}
