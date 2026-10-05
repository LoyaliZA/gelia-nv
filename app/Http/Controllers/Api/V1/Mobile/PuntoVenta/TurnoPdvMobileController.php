<?php

namespace App\Http\Controllers\Api\V1\Mobile\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PuntoVenta\Turnos\AltaTurnoPdvController;
use App\Http\Controllers\PuntoVenta\Turnos\FormularioRecepcionTurnoPdvController;
use App\Http\Requests\PuntoVenta\Turnos\AltaTurnoPdvRequest;
use App\Http\Requests\PuntoVenta\Turnos\ConsultarBandejaRecepcionTurnoPdvRequest;
use App\Services\PuntoVenta\Turnos\AltaTurnoPdvService;
use App\Services\PuntoVenta\Turnos\ConsultaBandejaRecepcionTurnoPdvService;
use Illuminate\Http\JsonResponse;

class TurnoPdvMobileController extends Controller
{
    public function recepcion(
        ConsultarBandejaRecepcionTurnoPdvRequest $request,
        ConsultaBandejaRecepcionTurnoPdvService $consulta,
        FormularioRecepcionTurnoPdvController $web,
    ): JsonResponse {
        return $web->datosMovil($request, $consulta);
    }

    public function store(
        AltaTurnoPdvRequest $request,
        AltaTurnoPdvService $alta,
        AltaTurnoPdvController $web,
    ): JsonResponse {
        return $web($request, $alta);
    }
}
