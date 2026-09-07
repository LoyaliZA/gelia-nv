<?php

namespace App\Http\Controllers\PuntoVenta\Reportes;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Reportes\ConsultarMetricasReportePdvRequest;
use App\Models\PuntoVenta\ReportePdvExportacion;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Reportes\ObtenerPayloadMetricasReportePdvService;
use App\Services\PuntoVenta\Reportes\ReportePdvExportacionTipo;
use App\Support\PuntoVenta\Reportes\DefinicionesMetricaReportePdv;
use App\Support\PuntoVenta\Reportes\EtiquetasMetricaReportePdv;
use App\Support\PuntoVenta\Reportes\MetricaResguardoPdvIds;
use App\Support\PuntoVenta\Reportes\MetricaTurnoOperacionPdvIds;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MetricasReportePdvController extends Controller
{
    public function index(Request $request, ResuelveAlcancePdv $alcance): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($this->puedeVerConjunto($user, $alcance)) {
            return redirect()->route('punto_venta.reportes.conjunto');
        }

        if ($this->puedeVerResguardos($user, $alcance)) {
            return redirect()->route('punto_venta.reportes.resguardos');
        }

        if ($this->puedeVerTurnosOperacion($user, $alcance)) {
            return redirect()->route('punto_venta.reportes.turnos_operacion');
        }

        abort(403);
    }

    public function resguardos(ConsultarMetricasReportePdvRequest $request, ObtenerPayloadMetricasReportePdvService $metricas): Response|JsonResponse
    {
        return $this->renderReporte(
            $request,
            $metricas,
            ReportePdvExportacionTipo::RESGUARDOS,
            'PuntoVenta/Reportes/Resguardos',
        );
    }

    public function turnosOperacion(ConsultarMetricasReportePdvRequest $request, ObtenerPayloadMetricasReportePdvService $metricas): Response|JsonResponse
    {
        return $this->renderReporte(
            $request,
            $metricas,
            ReportePdvExportacionTipo::TURNOS_OPERACION,
            'PuntoVenta/Reportes/TurnosOperacion',
        );
    }

    public function conjunto(ConsultarMetricasReportePdvRequest $request, ObtenerPayloadMetricasReportePdvService $metricas): Response|JsonResponse
    {
        return $this->renderReporte(
            $request,
            $metricas,
            ReportePdvExportacionTipo::CONJUNTO,
            'PuntoVenta/Reportes/Conjunto',
        );
    }

    private function renderReporte(
        ConsultarMetricasReportePdvRequest $request,
        ObtenerPayloadMetricasReportePdvService $metricas,
        string $tipoReporte,
        string $componente,
    ): Response|JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $filtros = array_merge($request->filtros(), ['tipo_reporte' => $tipoReporte]);

        $payload = $metricas->ejecutar($user, $filtros);
        $catalogos = $this->catalogos($user, $tipoReporte, $request);
        $permisos = $this->permisosUi($user, $request);
        $definiciones = DefinicionesMetricaReportePdv::todas();
        $etiquetas = $this->etiquetasMetricas($tipoReporte);
        $exportaciones = $this->exportacionesRecientes($user);

        $respuesta = [
            'tipo_reporte' => $tipoReporte,
            'payload' => $payload,
            'filtros' => $this->filtrosParaUi($payload, $tipoReporte),
            'catalogos' => $catalogos,
            'permisos' => $permisos,
            'definiciones_metricas' => $definiciones,
            'etiquetas_metricas' => $etiquetas,
            'secciones' => $this->secciones($tipoReporte),
            'exportaciones_recientes' => $exportaciones,
            'vistas_disponibles' => $this->vistasDisponibles($user, app(ResuelveAlcancePdv::class)),
        ];

        if ($request->expectsJson()) {
            return response()->json($respuesta);
        }

        return Inertia::render($componente, $respuesta);
    }

    /**
     * @param  array{resguardos?: array<string, mixed>, turnos_operacion?: array<string, mixed>}  $payload
     * @return array<string, mixed>
     */
    private function filtrosParaUi(array $payload, string $tipoReporte): array
    {
        if ($tipoReporte === ReportePdvExportacionTipo::RESGUARDOS) {
            return $payload['resguardos']['filtros'] ?? [];
        }

        if ($tipoReporte === ReportePdvExportacionTipo::TURNOS_OPERACION) {
            return $payload['turnos_operacion']['filtros'] ?? [];
        }

        return [
            'resguardos' => $payload['resguardos']['filtros'] ?? null,
            'turnos_operacion' => $payload['turnos_operacion']['filtros'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogos(User $user, string $tipoReporte, ConsultarMetricasReportePdvRequest $request): array
    {
        $alcance = app(ResuelveAlcancePdv::class);
        $sucursales = Sucursal::query()
            ->whereIn('id', $alcance->idsSucursalesElegibles())
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(static fn (Sucursal $s): array => ['id' => $s->id, 'nombre' => $s->nombre])
            ->values()
            ->all();

        $catalogos = [
            'sucursales' => $sucursales,
        ];

        if (in_array($tipoReporte, [ReportePdvExportacionTipo::RESGUARDOS, ReportePdvExportacionTipo::CONJUNTO], true)) {
            $catalogos['resguardos'] = [
                'estados' => EtiquetasResguardoPdv::estados(),
                'antiguedades' => EtiquetasResguardoPdv::antiguedades(),
                'tipos_incidencia' => [
                    ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO => 'Folio no encontrado',
                    ResguardoPdvIncidencia::TIPO_DANO => 'Daño',
                    ResguardoPdvIncidencia::TIPO_FALTANTE => 'Faltante',
                ],
            ];
        }

        if (in_array($tipoReporte, [ReportePdvExportacionTipo::TURNOS_OPERACION, ReportePdvExportacionTipo::CONJUNTO], true)) {
            $opcionesAlcance = [
                ['value' => MetricaTurnoOperacionPdvIds::ALCANCE_EQUIPO, 'label' => 'Equipo de sucursal'],
                ['value' => MetricaTurnoOperacionPdvIds::ALCANCE_PROPIO, 'label' => 'Propio'],
            ];

            if ($request->tieneAlcanceGlobal()) {
                $opcionesAlcance[] = [
                    'value' => MetricaTurnoOperacionPdvIds::ALCANCE_GLOBAL,
                    'label' => 'Global (todas las sucursales)',
                ];
            }

            $catalogos['turnos_operacion'] = [
                'servicios' => [['value' => 'ventas', 'label' => 'Ventas']],
                'alcances' => $opcionesAlcance,
            ];
        }

        return $catalogos;
    }

    /**
     * @return array<string, bool>
     */
    private function permisosUi(User $user, ConsultarMetricasReportePdvRequest $request): array
    {
        $alcance = app(ResuelveAlcancePdv::class);

        return [
            'exportar' => $request->puedeExportar(),
            'resguardos' => $this->puedeVerResguardos($user, $alcance),
            'turnos_operacion' => $this->puedeVerTurnosOperacion($user, $alcance),
            'alcance_global' => $alcance->tieneAlcanceGlobal($user),
        ];
    }

    /**
     * @return list<array{id: string, label: string, metricas: list<string>}>
     */
    private function secciones(string $tipoReporte): array
    {
        $secciones = [];

        if (in_array($tipoReporte, [ReportePdvExportacionTipo::RESGUARDOS, ReportePdvExportacionTipo::CONJUNTO], true)) {
            $secciones[] = [
                'id' => 'resguardos',
                'label' => 'Resguardos',
                'metricas' => MetricaResguardoPdvIds::todas(),
            ];
        }

        if (in_array($tipoReporte, [ReportePdvExportacionTipo::TURNOS_OPERACION, ReportePdvExportacionTipo::CONJUNTO], true)) {
            $secciones[] = [
                'id' => 'turnos',
                'label' => 'Turnos',
                'metricas' => MetricaTurnoOperacionPdvIds::metricasTurnos(),
            ];
            $secciones[] = [
                'id' => 'operacion',
                'label' => 'Operación',
                'metricas' => MetricaTurnoOperacionPdvIds::metricasOperacion(),
            ];
        }

        return $secciones;
    }

    /**
     * @return array<string, string>
     */
    private function etiquetasMetricas(string $tipoReporte): array
    {
        $ids = [];

        if (in_array($tipoReporte, [ReportePdvExportacionTipo::RESGUARDOS, ReportePdvExportacionTipo::CONJUNTO], true)) {
            $ids = array_merge($ids, MetricaResguardoPdvIds::todas());
        }

        if (in_array($tipoReporte, [ReportePdvExportacionTipo::TURNOS_OPERACION, ReportePdvExportacionTipo::CONJUNTO], true)) {
            $ids = array_merge($ids, MetricaTurnoOperacionPdvIds::metricasTurnos(), MetricaTurnoOperacionPdvIds::metricasOperacion());
        }

        $etiquetas = [];
        foreach ($ids as $id) {
            $etiquetas[$id] = EtiquetasMetricaReportePdv::etiqueta($id);
        }

        return $etiquetas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportacionesRecientes(User $user): array
    {
        return ReportePdvExportacion::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(static fn (ReportePdvExportacion $e): array => $e->paraApi())
            ->all();
    }

    /**
     * @return array{resguardos: bool, turnos_operacion: bool, conjunto: bool}
     */
    private function vistasDisponibles(User $user, ResuelveAlcancePdv $alcance): array
    {
        $resguardos = $this->puedeVerResguardos($user, $alcance);
        $turnos = $this->puedeVerTurnosOperacion($user, $alcance);

        return [
            'resguardos' => $resguardos,
            'turnos_operacion' => $turnos,
            'conjunto' => $resguardos && $turnos,
        ];
    }

    private function puedeVerResguardos(User $user, ResuelveAlcancePdv $alcance): bool
    {
        return $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER)
            && $alcance->tieneAlcanceGlobal($user);
    }

    private function puedeVerTurnosOperacion(User $user, ResuelveAlcancePdv $alcance): bool
    {
        return $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_VER);
    }

    private function puedeVerConjunto(User $user, ResuelveAlcancePdv $alcance): bool
    {
        return $this->puedeVerResguardos($user, $alcance)
            && $this->puedeVerTurnosOperacion($user, $alcance);
    }
}
