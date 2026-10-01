<?php

namespace App\Http\Controllers\Api\V1\Mobile\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PuntoVenta\Resguardos\BandejaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\BuscarProductoRegistroManualResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\ConfirmacionCustodiaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\ConfirmarDevolucionResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\EntregaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\EtiquetasResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\FormularioConfirmacionCustodiaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\HistorialEntregadosResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\PasarARecepcionResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\RecepcionFisicaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\RegistrarIncidenciaResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\RegistrarResguardoManualPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\ReponerVencidoResguardoPdvController;
use App\Http\Controllers\PuntoVenta\Resguardos\ResolverIncidenciaResguardoPdvController;
use App\Http\Requests\PuntoVenta\Resguardos\ConfirmarDevolucionResguardoPdvRequest;
use App\Http\Requests\PuntoVenta\Resguardos\ConsultarHistorialEntregadosResguardoPdvRequest;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarConfirmacionCustodiaPdvRequest;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarIncidenciaResguardoPdvRequest;
use App\Http\Requests\PuntoVenta\Resguardos\ReponerVencidoResguardoPdvRequest;
use App\Http\Requests\PuntoVenta\Resguardos\ResolverIncidenciaResguardoPdvRequest;
use App\Services\PuntoVenta\Resguardos\ConsultaFormularioConfirmacionCustodiaPdvService;
use App\Services\PuntoVenta\Resguardos\RegistrarConfirmacionCustodiaPdvService;
use App\Http\Requests\PuntoVenta\Resguardos\ConsultarBandejasResguardoPdvRequest;
use App\Http\Requests\PuntoVenta\Resguardos\PasarARecepcionResguardoPdvRequest;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvRequest;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarRecepcionFisicaPdvRequest;
use App\Http\Requests\PuntoVenta\Resguardos\RegistrarResguardoManualPdvRequest;
use App\Models\Almacen;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\ConfirmarDevolucionResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\ConsultaBandejasResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\ConsultaDetalleResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\ConsultaHistorialEntregadosResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\CrearResguardoManualPdvService;
use App\Services\PuntoVenta\Resguardos\PasarARecepcionResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\RegistrarIncidenciaResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\RegistrarRecepcionFisicaPdvService;
use App\Services\PuntoVenta\Resguardos\ReponerVencidoResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\ResolverEtiquetaResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\ResolverIncidenciaResguardoPdvService;
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

        if ($request->input('antiguedad') === AntiguedadOperativaResguardoPdv::VENCIDO
            && ! $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER_VENCIDOS)) {
            abort(403, 'No tiene permiso para consultar resguardos vencidos.');
        }

        return $bandeja->listado($request, $consulta);
    }

    public function show(
        Request $request,
        ResguardoPdv $resguardo,
        ConsultaDetalleResguardoPdvService $consulta,
        AlcancePdv $alcance,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $payload = $consulta->obtener($user, $resguardo);
        $payload['almacenes'] = $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_DANO)
            ? Almacen::query()
                ->where('sucursal_id', $resguardo->sucursal_id)
                ->where('activo', true)
                ->orderBy('codigo')
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre'])
                ->map(fn (Almacen $almacen) => [
                    'id' => $almacen->id,
                    'codigo' => $almacen->codigo,
                    'nombre' => $almacen->nombre,
                ])
                ->values()
                ->all()
            : [];

        return response()->json($payload);
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

    public function historialEntregados(
        ConsultarHistorialEntregadosResguardoPdvRequest $request,
        ConsultaHistorialEntregadosResguardoPdvService $consulta,
        HistorialEntregadosResguardoPdvController $web,
    ): JsonResponse {
        return $web->listado($request, $consulta);
    }

    public function resolverEtiqueta(
        Request $request,
        string $codigo,
        ResolverEtiquetaResguardoPdvService $resolver,
        EtiquetasResguardoPdvController $web,
    ): JsonResponse {
        return $web->resolver($request, $codigo, $resolver);
    }

    public function registrarIncidencia(
        RegistrarIncidenciaResguardoPdvRequest $request,
        ResguardoPdv $resguardo,
        RegistrarIncidenciaResguardoPdvService $registrar,
        RegistrarIncidenciaResguardoPdvController $web,
    ): JsonResponse {
        return $web($request, $resguardo, $registrar);
    }

    public function resolverIncidencia(
        ResolverIncidenciaResguardoPdvRequest $request,
        ResguardoPdv $resguardo,
        ResguardoPdvIncidencia $incidenciaResguardo,
        ResolverIncidenciaResguardoPdvService $resolver,
        ResolverIncidenciaResguardoPdvController $web,
    ): JsonResponse {
        return $web($request, $resguardo, $incidenciaResguardo, $resolver);
    }

    public function devolucion(
        ConfirmarDevolucionResguardoPdvRequest $request,
        ResguardoPdv $resguardo,
        ConfirmarDevolucionResguardoPdvService $confirmar,
        ConfirmarDevolucionResguardoPdvController $web,
    ): JsonResponse {
        return $web($request, $resguardo, $confirmar);
    }

    public function reponerVencido(
        ReponerVencidoResguardoPdvRequest $request,
        ResguardoPdv $resguardo,
        ReponerVencidoResguardoPdvService $reponer,
        ReponerVencidoResguardoPdvController $web,
    ): JsonResponse {
        return $web($request, $resguardo, $reponer);
    }
}
