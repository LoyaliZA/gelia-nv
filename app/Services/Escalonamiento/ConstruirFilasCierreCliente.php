<?php

namespace App\Services\Escalonamiento;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoCierreDetalle;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoMovimiento;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use Illuminate\Support\Collection;

class ConstruirFilasCierreCliente
{
    public function __construct(
        private EvaluarListaClienteEscalonamiento $evaluarLista,
        private EvaluarInactividadClienteEscalonamiento $inactividad,
        private ParticipacionClienteEscalonamiento $participacion,
        private ListaPublicoGeneralEscalonamiento $listaPg,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function construir(EscalonamientoPeriodo $periodo): array
    {
        $clienteIds = $this->clienteIdsAlcance($periodo);
        if ($clienteIds->isEmpty()) {
            return [];
        }

        $resumenes = EscalonamientoResumenCliente::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->whereIn('cliente_id', $clienteIds)
            ->get()
            ->keyBy('cliente_id');

        $totales = $this->totalesPorCliente($periodo, $clienteIds);
        $incidenciasAbiertas = EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('estado', 'abierta')
            ->operativas()
            ->whereIn('cliente_id', $clienteIds)
            ->selectRaw('cliente_id, count(*) as total')
            ->groupBy('cliente_id')
            ->pluck('total', 'cliente_id');

        $clientes = Cliente::query()
            ->whereIn('id', $clienteIds)
            ->get()
            ->keyBy('id');

        $filas = [];
        foreach ($clienteIds as $clienteId) {
            $cliente = $clientes->get($clienteId);
            if (! $cliente) {
                continue;
            }

            $resumen = $resumenes->get($clienteId);
            $compras = $totales[$clienteId]['compras'] ?? '0.00';
            $devoluciones = $totales[$clienteId]['devoluciones'] ?? '0.00';
            $neto = $resumen ? (string) $resumen->acumulado : bcsub($compras, $devoluciones, 2);

            $listaBaseId = $resumen?->lista_base_id;
            $listaVigenteId = $resumen?->lista_vigente_id;
            $clasificacionId = $resumen?->clasificacion_mes_id;
            $acumulado = $neto;

            $renovacion = $this->evaluarLista->evaluarRenovacion(
                $periodo,
                $listaVigenteId,
                $clasificacionId,
                $acumulado,
            );

            $listaSiguienteId = $renovacion['lista_siguiente_propuesta_id'];
            $motivo = $this->motivoRenovacion($renovacion, $listaVigenteId, $listaSiguienteId);

            $tuvoActividad = $this->inactividad->tuvoActividadCompra($periodo, $cliente);
            $inact = $this->inactividad->evaluarContador($periodo, $cliente, $tuvoActividad);

            $participa = $this->participacion->clienteParticipa($periodo, $cliente);
            $bloqueado = (bool) $cliente->lista_bloqueada;

            if ($bloqueado) {
                $listaSiguienteId = $listaVigenteId ?? $cliente->lista_actual_id;
                $motivo = 'bloqueado';
            } elseif (! $participa) {
                $listaSiguienteId = $cliente->lista_actual_id;
                $motivo = 'no_participa';
            } elseif ($inact['propone_inactivo']) {
                try {
                    $listaSiguienteId = $this->listaPg->id();
                    $motivo = 'inactividad';
                } catch (\Illuminate\Validation\ValidationException) {
                    $motivo = 'inactividad_sin_pg';
                }
            }

            $listaOperativaId = $cliente->lista_actual_id;
            $aplicaCambio = $participa
                && ! $bloqueado
                && $listaSiguienteId
                && (int) $listaSiguienteId !== (int) $listaOperativaId;

            $filas[] = [
                'cliente_id' => $clienteId,
                'compras' => $compras,
                'devoluciones' => $devoluciones,
                'neto' => $neto,
                'lista_base_id' => $listaBaseId,
                'lista_vigente_cierre_id' => $listaVigenteId,
                'clasificacion_mes_id' => $clasificacionId,
                'lista_siguiente_id' => $listaSiguienteId,
                'lista_operativa_id' => $listaOperativaId,
                'motivo' => $motivo,
                'aplica_cambio_lista' => $aplicaCambio || $inact['propone_inactivo'],
                'propone_inactivo' => $inact['propone_inactivo'],
                'meses_sin_compra' => $inact['meses_sin_compra'],
                'extras' => [
                    'cumple_mantenimiento' => $renovacion['cumple_mantenimiento'],
                    'faltante_mantenimiento' => $renovacion['faltante_mantenimiento'],
                    'incidencias_abiertas' => (int) ($incidenciasAbiertas[$clienteId] ?? 0),
                    'tuvo_actividad_compra' => $tuvoActividad,
                ],
            ];
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function persistirDetalles(int $cierreId, array $filas): void
    {
        foreach ($filas as $fila) {
            EscalonamientoCierreDetalle::create([
                'escalonamiento_cierre_id' => $cierreId,
                ...$fila,
            ]);
        }
    }

    private function clienteIdsAlcance(EscalonamientoPeriodo $periodo): Collection
    {
        $desdeResumen = EscalonamientoResumenCliente::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->pluck('cliente_id');

        $desdeMovimientos = EscalonamientoMovimiento::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->distinct()
            ->pluck('cliente_id');

        $desdeContador = Cliente::query()
            ->where('escalonamiento_meses_sin_compra', '>', 0)
            ->pluck('id');

        return $desdeResumen
            ->merge($desdeMovimientos)
            ->merge($desdeContador)
            ->unique()
            ->values();
    }

    /**
     * @return array<int, array{compras: string, devoluciones: string}>
     */
    private function totalesPorCliente(EscalonamientoPeriodo $periodo, Collection $clienteIds): array
    {
        $movimientos = EscalonamientoMovimiento::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->whereIn('cliente_id', $clienteIds)
            ->get(['cliente_id', 'efecto', 'operacion']);

        $out = [];
        foreach ($movimientos as $mov) {
            $id = (int) $mov->cliente_id;
            if (! isset($out[$id])) {
                $out[$id] = ['compras' => '0.00', 'devoluciones' => '0.00'];
            }
            $efecto = bcadd((string) $mov->efecto, '0', 2);
            if (bccomp($efecto, '0', 2) > 0) {
                $out[$id]['compras'] = bcadd($out[$id]['compras'], $efecto, 2);
            } elseif (bccomp($efecto, '0', 2) < 0) {
                $out[$id]['devoluciones'] = bcadd($out[$id]['devoluciones'], ltrim($efecto, '-'), 2);
            }
        }

        return $out;
    }

    /**
     * @param  array{cumple_mantenimiento: bool, lista_siguiente_propuesta_id: ?int}  $renovacion
     */
    private function motivoRenovacion(array $renovacion, ?int $listaVigenteId, ?int $listaSiguienteId): string
    {
        if (! $listaVigenteId && ! $listaSiguienteId) {
            return 'sin_datos';
        }
        if ($listaSiguienteId && $listaVigenteId && (int) $listaSiguienteId === (int) $listaVigenteId) {
            return $renovacion['cumple_mantenimiento'] ? 'mantenimiento' : 'sin_cambio';
        }

        return 'descenso_actividad';
    }
}
