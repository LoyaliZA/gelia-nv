<?php

namespace App\Services\Escalonamiento;

use App\Models\Cliente;
use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoAplicacionDevolucion;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoMovimiento;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use Illuminate\Support\Carbon;

class MetricasPeriodoEscalonamiento
{
    public function __construct() {}

    /**
     * @return array<string, mixed>
     */
    public function paraPeriodo(EscalonamientoPeriodo $periodo): array
    {
        $compras = $this->sumaMovimientos($periodo, ['remision', 'revision']);
        $devoluciones = $this->sumaMovimientos($periodo, ['devolucion', 'reversion_devolucion']);
        $devolucionesAplicadas = (string) EscalonamientoAplicacionDevolucion::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('estado', EscalonamientoAplicacionDevolucion::ESTADO_ACTIVA)
            ->sum('importe');

        $pendientesVinculo = DocumentoVenta::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('tipo', 'devolucion')
            ->where('estado', 'pendiente_de_vinculacion')
            ->count();

        $incidenciasAbiertas = EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('estado', 'abierta')
            ->operativas()
            ->count();

        $incidenciasPorCodigo = EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('estado', 'abierta')
            ->operativas()
            ->selectRaw('codigo, count(*) as total')
            ->groupBy('codigo')
            ->pluck('total', 'codigo')
            ->all();

        $exclusionesInformativas = EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->whereIn('codigo', EscalonamientoIncidencia::codigosInformativos())
            ->count();

        $resumenes = EscalonamientoResumenCliente::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->get(['acumulado', 'lista_base_id', 'lista_vigente_id', 'clasificacion_mes_id']);

        $ascensosIntrames = $resumenes->filter(function (EscalonamientoResumenCliente $resumen) {
            $base = (int) ($resumen->lista_base_id ?? 0);
            $vigente = (int) ($resumen->lista_vigente_id ?? 0);

            return $vigente > 0 && $vigente !== $base;
        })->count();

        $clientesConMovimiento = EscalonamientoMovimiento::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->distinct('cliente_id')
            ->count('cliente_id');

        $clientesActivos = Cliente::query()->where('es_inactivo', false)->count();
        $coberturaHistorial = $periodo->estado === EscalonamientoPeriodo::ESTADO_HISTORIAL && $clientesActivos > 0
            ? round(($clientesConMovimiento / $clientesActivos) * 100, 1)
            : null;

        $cierre = $periodo->cierreVigente;
        $erpPendiente = $cierre
            && $cierre->estaAplicado()
            && ! $cierre->aplicacion_externa_declarada;

        $antiguedadCorteDias = null;
        if ($periodo->estaAbierto() && $periodo->fecha_corte) {
            $antiguedadCorteDias = (int) Carbon::parse($periodo->fecha_corte)->diffInDays(now());
        }

        return [
            'periodo' => [
                'id' => $periodo->id,
                'etiqueta' => $periodo->etiquetaMes(),
                'estado' => $periodo->estado,
            ],
            'compras' => $compras,
            'devoluciones_aplicadas' => bcadd($devolucionesAplicadas, '0', 2),
            'devoluciones_movimiento' => $devoluciones,
            'venta_neta' => bcsub($compras, $devolucionesAplicadas, 2),
            'ascensos_intrames' => $ascensosIntrames,
            'clientes_con_movimiento' => $clientesConMovimiento,
            'cobertura_historial_pct' => $coberturaHistorial,
            'pendientes_vinculo' => $pendientesVinculo,
            'incidencias_abiertas' => $incidenciasAbiertas,
            'incidencias_por_codigo' => $incidenciasPorCodigo,
            'exclusiones_informativas' => $exclusionesInformativas,
            'erp_aplicacion_pendiente' => $erpPendiente,
            'antiguedad_corte_dias' => $antiguedadCorteDias,
            'documentos_total' => $periodo->documentos()->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tareasOperativas(?EscalonamientoPeriodo $periodo): array
    {
        $tareas = [];

        if ($periodo) {
            $metricas = $this->paraPeriodo($periodo);
            if ($metricas['pendientes_vinculo'] > 0) {
                $tareas[] = [
                    'codigo' => 'devoluciones_pendientes',
                    'descripcion' => $metricas['pendientes_vinculo'].' devolución(es) pendientes de vínculo en '.$periodo->etiquetaMes().'.',
                ];
            }
            if ($metricas['incidencias_abiertas'] > 0) {
                $tareas[] = [
                    'codigo' => 'incidencias_abiertas',
                    'descripcion' => $metricas['incidencias_abiertas'].' incidencia(s) abiertas en '.$periodo->etiquetaMes().'.',
                ];
            }
            if ($metricas['erp_aplicacion_pendiente']) {
                $tareas[] = [
                    'codigo' => 'erp_pendiente',
                    'descripcion' => 'Cierre de '.$periodo->etiquetaMes().' sin declarar aplicación manual en ERP.',
                ];
            }
            if ($metricas['antiguedad_corte_dias'] !== null && $metricas['antiguedad_corte_dias'] > 7) {
                $tareas[] = [
                    'codigo' => 'corte_antiguo',
                    'descripcion' => 'El corte del período abierto tiene '.$metricas['antiguedad_corte_dias'].' días sin actualizarse.',
                ];
            }
        }

        $historialSinCobertura = EscalonamientoPeriodo::query()
            ->where('estado', EscalonamientoPeriodo::ESTADO_HISTORIAL)
            ->withCount('documentos')
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->limit(6)
            ->get()
            ->filter(fn (EscalonamientoPeriodo $p) => $p->documentos_count === 0);

        foreach ($historialSinCobertura as $periodoHistorial) {
            $tareas[] = [
                'codigo' => 'historial_vacio',
                'descripcion' => 'Período histórico '.$periodoHistorial->etiquetaMes().' sin documentos cargados.',
            ];
        }

        return $tareas;
    }

    /**
     * @param  list<string>  $operaciones
     */
    private function sumaMovimientos(EscalonamientoPeriodo $periodo, array $operaciones): string
    {
        $suma = EscalonamientoMovimiento::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->whereIn('operacion', $operaciones)
            ->sum('efecto');

        return bcadd((string) $suma, '0', 2);
    }
}
