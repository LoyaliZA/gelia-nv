<?php

namespace App\Http\Controllers\Api\V1\Mobile\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PuntoVenta\Resguardos\BandejaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\BuscarProductoRegistroManualResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\ConfirmacionCustodiaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\EntregaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\FormularioConfirmacionCustodiaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\PasarARecepcionResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\RecepcionFisicaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\RegistrarResguardoManualPdvController;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarConfirmacionCustodiaPdvRequest;
use App\Services\PuntoVenta\Resguardos\ConsultaFormularioConfirmacionCustodiaPdvService;
use App\Services\PuntoVenta\Resguardos\RegistrarConfirmacionCustodiaPdvService;
use App\Http\Requests\PuntoVenta\Resguardos\ConsultarBandejasResguardoPdvRequest;
use App\Http\Requests\PuntoVenta\Resguardos\PasarARecepcionResguardoPdvRequest;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvRequest;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarRecepcionFisicaPdvRequest;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarResguardoManualPdvRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\ConsultaBandejasResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\ConsultaDetalleResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\CrearResguardoManualPdvService;
use App\Services\PuntoVenta\Resguardos\PasarARecepcionResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\RegistrarRecepcionFisicaPdvService;
use App\Services\PuntoVenta\Resguardos\RegistroManualResguardoPdvConfig;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResguardoPdvMobileController extends Controller
{
    public function index(
        ConsultarBandejasResguardoPdvRequest $request,
        ConsultaBandejasResguardoPdvService $consulta,
        BandejaResguardoPdvController $bandeja,
        AlcancePdv $alcance,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if ($request->input('antiguedad') === AntiguedadOperativaResguardoPdv::REZAGADO
            && ! $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER_REZAGADOS)) {
            abort(403, 'No tiene permiso para consultar resguardos rezagados.');
        }

        return $bandeja->listado($request, $consulta);
    }

    public function show(
        Request $request,
        ResguardoPdv $resguardo,
        ConsultaDetalleResguardoPdvService $consulta,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json($consulta->obtener($user, $resguardo));
    }

    public function buscarProductos(
        Request $request,
        RegistroManualResguardoPdvConfig $config,
        BuscarProductoRegistroManualResguardoPdvController $buscar,
    ): JsonResponse {
        return $buscar($request, $config);
    }

    public function store(
        RegistrarResguardoManualPdvRequest $request,
        CrearResguardoManualPdvService $crear,
        RegistrarResguardoManualPdvController $registrar,
    ): JsonResponse {
        return $registrar($request, $crear);
    }

    public function recepcion(
        RegistrarRecepcionFisicaPdvRequest $request,
        ResguardoPdv $resguardo,
        RegistrarRecepcionFisicaPdvService $registrar,
        RecepcionFisicaResguardoPdvController $web,
    ): JsonResponse {
        return $web($request, $resguardo, $registrar);
    }

    public function pasarRecepcion(
        PasarARecepcionResguardoPdvRequest $request,
        ResguardoPdv $resguardo,
        PasarARecepcionResguardoPdvService $servicio,
        PasarARecepcionResguardoPdvController $web,
    ): JsonResponse {
        return $web($request, $resguardo, $servicio);
    }

    public function formularioCustodia(
        Request $request,
        ResguardoPdv $resguardo,
        ConsultaFormularioConfirmacionCustodiaPdvService $consulta,
        FormularioConfirmacionCustodiaResguardoPdvController $formulario,
    ): JsonResponse {
        $response = $formulario->show($request, $resguardo, $consulta);

        return $response instanceof JsonResponse ? $response : abort(500);
    }

    public function custodia(
        RegistrarConfirmacionCustodiaPdvRequest $request,
        ResguardoPdv $resguardo,
        RegistrarConfirmacionCustodiaPdvService $registrar,
        ConfirmacionCustodiaResguardoPdvController $confirmacion,
    ): JsonResponse {
        return $confirmacion($request, $resguardo, $registrar);
    }

    public function entrega(
        RegistrarEntregaResguardoPdvRequest $request,
        ResguardoPdv $resguardo,
        RegistrarEntregaResguardoPdvService $registrar,
        EntregaResguardoPdvController $web,
    ): JsonResponse {
        return $web($request, $resguardo, $registrar);
    }
}
