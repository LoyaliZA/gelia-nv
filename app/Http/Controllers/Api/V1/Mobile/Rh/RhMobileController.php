<?php

namespace App\Http\Controllers\Api\V1\Mobile\Rh;

use App\Http\Controllers\Controller;
use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Models\RhPrestamoPagoFijo;
use App\Models\User;
use App\Services\Mobile\Serializar\RhMovilSerializer;
use App\Services\Rh\ListarBancoTiempoService;
use App\Services\Rh\ListarColaboradoresService;
use App\Services\Rh\ListarDeduccionesService;
use App\Services\Rh\ListarHorasExtraService;
use App\Services\Rh\ListarPrestamosPagoFijoService;
use App\Services\Rh\ListarSalidasPersonalesService;
use App\Support\Api\V1\AutorizacionMovil;
use App\Support\Api\V1\RespuestaPaginada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RhMobileController extends Controller
{
    public function colaboradores(Request $request, ListarColaboradoresService $listar, RhMovilSerializer $serializar): JsonResponse
    {
        $this->exigir($request, ['rh.ver']);
        $paginado = $listar->ejecutar($this->filtros($request, [
            'busqueda', 'departamento_id', 'area_id', 'catalogo_puesto_id', 'activo', 'vinculo',
        ]), RespuestaPaginada::porPagina($request));

        return response()->json([
            'data' => $paginado->getCollection()->map(fn (RhColaborador $colaborador) => $serializar->colaborador($colaborador))->values(),
            'meta' => RespuestaPaginada::meta($paginado),
        ]);
    }

    public function colaborador(Request $request, RhColaborador $colaborador, RhMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->exigir($request, ['rh.ver']);
        $colaborador->load(['departamento', 'area', 'puesto', 'turno', 'usuario']);

        $puedeIncidencias = $usuario->can('rh.incidencias.ver') || $usuario->can('rh.deducciones.ver');
        $puedePrestamos = $usuario->can('rh.prestamos.ver');

        return response()->json([
            'data' => $serializar->colaborador($colaborador),
            'incidencias' => $puedeIncidencias
                ? RhDeduccion::query()
                    ->with(['colaborador.departamento', 'colaborador.area', 'reglaIncidencia'])
                    ->where('rh_colaborador_id', $colaborador->id)
                    ->orderByDesc('fecha_ocurrencia')
                    ->limit(8)
                    ->get()
                    ->map(fn (RhDeduccion $deduccion) => $serializar->deduccion($deduccion))
                    ->values()
                : [],
            'prestamos_activos' => $puedePrestamos
                ? RhPrestamoPagoFijo::query()
                    ->with(['colaborador.departamento', 'colaborador.area'])
                    ->where('rh_colaborador_id', $colaborador->id)
                    ->whereIn('estado', [RhPrestamoPagoFijo::ESTADO_ACTIVO, RhPrestamoPagoFijo::ESTADO_PAUSADO])
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn (RhPrestamoPagoFijo $prestamo) => $serializar->prestamo($prestamo))
                    ->values()
                : [],
        ]);
    }

    public function horasExtra(Request $request, ListarHorasExtraService $listar, RhMovilSerializer $serializar): JsonResponse
    {
        $this->exigir($request, ['rh.ver', 'rh.horas_extra.ver']);
        $filtros = $this->filtros($request, [
            'busqueda', 'rh_colaborador_id', 'departamento_id', 'area_id', 'fecha_inicio', 'fecha_fin', 'estado_pago',
        ]);

        $paginado = $listar->ejecutar($filtros, RespuestaPaginada::porPagina($request));

        return response()->json([
            'data' => $paginado->getCollection()->map(fn ($registro) => $serializar->horasExtra($registro))->values(),
            'meta' => RespuestaPaginada::meta($paginado),
            'metricas' => $listar->metricas($filtros),
        ]);
    }

    public function salidas(Request $request, ListarSalidasPersonalesService $listar, RhMovilSerializer $serializar): JsonResponse
    {
        $this->exigir($request, ['rh.ver', 'rh.salidas_personales.ver']);
        $filtros = $this->filtros($request, [
            'busqueda', 'rh_colaborador_id', 'departamento_id', 'area_id', 'fecha_inicio', 'fecha_fin', 'estado_cobro',
        ]);
        $paginado = $listar->ejecutar($filtros, RespuestaPaginada::porPagina($request));

        return response()->json([
            'data' => $paginado->getCollection()->map(fn ($salida) => $serializar->salida($salida))->values(),
            'meta' => RespuestaPaginada::meta($paginado),
        ]);
    }

    public function incidencias(Request $request, ListarDeduccionesService $listar, RhMovilSerializer $serializar): JsonResponse
    {
        $this->exigir($request, ['rh.ver', 'rh.incidencias.ver']);
        $filtros = $this->filtros($request, [
            'busqueda', 'rh_colaborador_id', 'departamento_id', 'area_id', 'catalogo_regla_incidencia_id', 'estado_deduccion',
        ]);
        $paginado = $listar->ejecutar($filtros, RespuestaPaginada::porPagina($request));

        return response()->json([
            'data' => $paginado->getCollection()->map(fn ($deduccion) => $serializar->deduccion($deduccion))->values(),
            'meta' => RespuestaPaginada::meta($paginado),
            'metricas' => $listar->metricas($filtros),
        ]);
    }

    public function prestamos(Request $request, ListarPrestamosPagoFijoService $listar, RhMovilSerializer $serializar): JsonResponse
    {
        $this->exigir($request, ['rh.ver', 'rh.prestamos.ver']);
        $filtros = $this->filtros($request, ['busqueda', 'rh_colaborador_id', 'departamento_id', 'estado']);
        $paginado = $listar->ejecutar($filtros, RespuestaPaginada::porPagina($request));

        return response()->json([
            'data' => $paginado->getCollection()->map(fn ($prestamo) => $serializar->prestamo($prestamo))->values(),
            'meta' => RespuestaPaginada::meta($paginado),
            'metricas' => $listar->metricas($filtros),
        ]);
    }

    public function bancoTiempo(Request $request, ListarBancoTiempoService $listar, RhMovilSerializer $serializar): JsonResponse
    {
        $this->exigir($request, ['rh.ver', 'rh.banco_tiempo.ver']);
        $filtros = $this->filtros($request, ['busqueda', 'rh_colaborador_id', 'departamento_id', 'estado']);
        $paginado = $listar->ejecutar($filtros, RespuestaPaginada::porPagina($request));

        return response()->json([
            'data' => $paginado->getCollection()->map(fn ($banco) => $serializar->bancoTiempo($banco))->values(),
            'meta' => RespuestaPaginada::meta($paginado),
            'metricas' => $listar->metricas($filtros),
        ]);
    }

    /**
     * @param  list<string>  $permisos
     */
    private function exigir(Request $request, array $permisos): User
    {
        /** @var User $usuario */
        $usuario = $request->user();
        foreach ($permisos as $permiso) {
            AutorizacionMovil::alguno($usuario, [$permiso]);
        }

        return $usuario;
    }

    /**
     * @param  list<string>  $claves
     * @return array<string, mixed>
     */
    private function filtros(Request $request, array $claves): array
    {
        $filtros = $request->only($claves);
        if ($request->filled('q') && empty($filtros['busqueda'])) {
            $filtros['busqueda'] = $request->query('q');
        }

        return $filtros;
    }
}
