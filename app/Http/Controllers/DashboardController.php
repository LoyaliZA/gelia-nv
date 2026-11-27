<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SolicitudTag;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoProceso;
use App\Models\CobranzaAlerta;
use App\Services\Activos\AlertasActivosService;
use App\Services\CancelacionesCotizaciones\ListarSolicitudesOperativasService;
use App\Services\Cobranza\ResumenCarteraVencidaService;
use App\Services\Contabilidad\ObtenerDashboardContabilidadService;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use App\Services\Dashboard\ObtenerMetricasOperativasDashboardService;
use App\Services\Dashboard\ResolverPeriodoDashboard;
use App\Services\Facturas\ListarSolicitudesFacturaService;
use App\Services\Rh\ResumenDashboardRhService;
use App\Services\Solicitudes\ListarSolicitudesService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Calcula y envía las estadísticas del dashboard basadas estrictamente en los permisos del usuario.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $estadisticas = [];
        $ultimasSolicitudes = [];
        $ultimasOperativas = [];
        $metricasSolicitudes = [];
        $metricasOperativas = [];
        $metricasCredibox = [];
        $metricasPedidos = [];
        $metricasFacturas = [];
        $metricasContabilidad = [];
        $alertasActivosResumen = [];
        $alertasActivosDestacadas = [];
        $rhWidget = [];

        $resolverPeriodo = app(ResolverPeriodoDashboard::class);
        $periodo = $resolverPeriodo->resolver($request);

        $verWidgetSolicitudes = $user->can('configuracion.ver_auditoria')
            || $user->can('solicitudes.ver_listado')
            || $user->can('solicitudes.gestionar');

        if ($user->can('solicitudes.crear') || $user->can('solicitudes.gestionar')) {
            $idsCerrados = array_values(array_filter([
                CatalogoEstadoSolicitud::idDe('Respondida'),
                CatalogoEstadoSolicitud::idDe('Cancelada'),
            ]));

            $queryMisActivas = SolicitudTag::where('vendedor_id', $user->id);
            if ($idsCerrados !== []) {
                $queryMisActivas->whereNotIn('catalogo_estado_solicitud_id', $idsCerrados);
            }
            $estadisticas['mis_activas'] = $queryMisActivas->count();
        }

        if ($verWidgetSolicitudes) {
            $metricasOperativasDashboard = app(ObtenerMetricasOperativasDashboardService::class)
                ->ejecutar($user, $periodo);
            $estadisticas['cotizado'] = $metricasOperativasDashboard['cotizado'];
            $estadisticas['actividad'] = $metricasOperativasDashboard['actividad'];
            $estadisticas['solicitudes_periodo'] = $metricasOperativasDashboard['actividad']['creadas'];

            $listarSolicitudes = app(ListarSolicitudesService::class);
            $queryUltimas = SolicitudTag::with(['cliente', 'estado', 'proceso'])
                ->whereHas('proceso', function ($q) {
                    $q->where('categoria_flujo', '!=', CatalogoProceso::CATEGORIA_OPERATIVO);
                });
            $listarSolicitudes->aislarConsulta($queryUltimas, $user);
            $ultimasSolicitudes = $queryUltimas->latest()->take(4)->get();
            $metricasSolicitudes = $listarSolicitudes->metricas($user);
        }

        if ($user->can('cancelaciones_cotizaciones.ver_listado')) {
            $ultimasOperativas = SolicitudTag::with(['cliente', 'estado', 'proceso'])
                ->whereHas('proceso', function ($q) {
                    $q->where('categoria_flujo', CatalogoProceso::CATEGORIA_OPERATIVO);
                })
                ->latest()
                ->take(4)
                ->get();
            $metricasOperativas = app(ListarSolicitudesOperativasService::class)->metricas($user);
        }

        if ($user->can('activos.ver')) {
            $alertas = app(AlertasActivosService::class)->ejecutar($user);
            $alertasActivosResumen = [
                'vencidos' => count($alertas['vencidos']),
                'proximos_7' => count($alertas['proximos_7']),
                'proximos_30' => count($alertas['proximos_30']),
                'mantenimiento' => count($alertas['mantenimiento']),
            ];
            $alertasActivosDestacadas = array_values(array_merge(
                array_slice($alertas['vencidos'], 0, 2),
                array_slice($alertas['proximos_7'], 0, 2),
                array_slice($alertas['mantenimiento'], 0, 2),
            ));
        }

        if ($user->can('rh.ver')) {
            $rhWidget = app(ResumenDashboardRhService::class)->widget();
        }

        if ($user->can('cobranza.ver')) {
            $cartera = app(ResumenCarteraVencidaService::class)->ejecutar();
            $metricasCredibox = [
                'alertas_pendientes' => CobranzaAlerta::query()->where('estado', 'pendiente')->count(),
                'saldo_vencido' => $cartera['saldo_vencido'],
                'cartera' => $cartera['cartera'],
                'top_vencidos' => $cartera['top_vencidos'],
            ];
        }

        if ($user->can('control_pedidos.ver_listado')) {
            $metricasPedidos = app(ListarPedidosBmaService::class)->metricas($user);
        }

        if ($user->can('facturas.ver_listado')) {
            $metricasFacturas = app(ListarSolicitudesFacturaService::class)->metricas($user);
        }

        if ($user->can('contabilidad.ver')) {
            $dashRequest = Request::create('/', 'GET', $resolverPeriodo->parametrosContabilidad($periodo));
            $kpis = app(ObtenerDashboardContabilidadService::class)->ejecutar($dashRequest)['kpis'] ?? [];
            $metricasContabilidad = [
                'ventas' => $kpis['ventas'] ?? 0,
                'margen' => $kpis['margen'] ?? 0,
                'ganancias' => $kpis['ganancias'] ?? 0,
                'perdidas' => $kpis['perdidas'] ?? 0,
                'utilidad' => round((float) (($kpis['ganancias'] ?? 0) + ($kpis['perdidas'] ?? 0)), 2),
                'etiqueta_periodo' => $periodo['etiqueta'],
            ];
        }

        return Inertia::render('Dashboards/Index', [
            'estadisticas' => $estadisticas,
            'filtro_periodo' => $resolverPeriodo->paraInertia($periodo),
            'ultimas_solicitudes' => $ultimasSolicitudes,
            'ultimas_operativas' => $ultimasOperativas,
            'metricas_solicitudes' => $metricasSolicitudes,
            'metricas_operativas' => $metricasOperativas,
            'metricas_credibox' => $metricasCredibox,
            'metricas_pedidos' => $metricasPedidos,
            'metricas_facturas' => $metricasFacturas,
            'metricas_contabilidad' => $metricasContabilidad,
            'alertas_activos_resumen' => $alertasActivosResumen,
            'alertas_activos_destacadas' => $alertasActivosDestacadas,
            'rh_widget' => $rhWidget,
        ]);

    }

    /**
     * Actualiza las preferencias visuales del dashboard del usuario (Tarjetas Ocultas).
     */
    public function actualizarPreferencias(Request $request)
    {
        $request->validate([
            'dashboard_ocultos' => 'sometimes|array',
            'dashboard_layout' => 'nullable|array',
            'dashboard_layout.*.i' => 'required_with:dashboard_layout|string|max:64',
            'dashboard_layout.*.x' => 'required_with:dashboard_layout|integer|min:0|max:23',
            'dashboard_layout.*.y' => 'required_with:dashboard_layout|integer|min:0|max:200',
            'dashboard_layout.*.w' => 'required_with:dashboard_layout|integer|min:4|max:24',
            'dashboard_layout.*.h' => 'required_with:dashboard_layout|integer|min:4|max:100',
            'dashboard_layout.*.rw' => 'nullable|integer|min:320|max:10000',
            'dashboard_preset' => 'sometimes|string|in:operativo,comercial,launcher',
        ]);

        $user = $request->user();

        $configActual = DB::table('configuraciones_usuarios')
            ->where('user_id', $user->id)
            ->first();

        $temaVisual = $configActual ? (json_decode($configActual->tema_visual ?? '[]', true) ?: []) : [];

        if ($request->has('dashboard_ocultos')) {
            $temaVisual['dashboard_ocultos'] = $request->input('dashboard_ocultos', []);
        }
        if ($request->has('dashboard_layout') && is_array($request->input('dashboard_layout'))) {
            $temaVisual['dashboard_layout'] = $request->input('dashboard_layout');
        }
        if ($request->has('dashboard_preset')) {
            $temaVisual['dashboard_preset'] = $request->input('dashboard_preset');
        }

        DB::table('configuraciones_usuarios')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'tema_visual' => json_encode($temaVisual),
                'updated_at'  => now(),
            ]
        );

        return back()->with('success', 'Preferencias del panel guardadas.');
    }
}
