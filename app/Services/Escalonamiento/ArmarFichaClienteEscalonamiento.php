<?php

namespace App\Services\Escalonamiento;

use App\Models\Cliente;
use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoAplicacionDevolucion;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoMovimiento;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;

class ArmarFichaClienteEscalonamiento
{
    public function __construct(
        private ConsultarAcumuladoCliente $consultarAcumulado,
        private EvaluarListaClienteEscalonamiento $evaluarLista,
        private ConciliacionSolicitudEscalonamiento $conciliacion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function armar(Cliente $cliente, ?EscalonamientoPeriodo $periodo = null): array
    {
        $periodo ??= EscalonamientoPeriodo::query()
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->first();

        $resumen = null;
        if ($periodo) {
            $resumen = EscalonamientoResumenCliente::query()
                ->with(['listaBase', 'listaVigente', 'clasificacionMes', 'clasificacionMesMax'])
                ->where('escalonamiento_periodo_id', $periodo->id)
                ->where('cliente_id', $cliente->id)
                ->first();
        }

        $cabecera = $this->consultarAcumulado->consultar($cliente, $periodo);
        $proyeccion = $periodo
            ? $this->evaluarLista->proyeccionLectura($periodo, $cliente, $resumen)
            : [];

        $listaSiguienteId = $proyeccion['lista_siguiente_propuesta_id'] ?? null;

        return [
            'cabecera' => array_merge($cabecera, [
                'lista_siguiente_propuesta_id' => $listaSiguienteId,
                'lista_siguiente_propuesta' => $this->evaluarLista->nombreLista($listaSiguienteId),
                'cumple_mantenimiento' => $proyeccion['cumple_mantenimiento'] ?? null,
                'faltante_mantenimiento' => $proyeccion['faltante_mantenimiento'] ?? null,
                'clasificacion_mes_max' => $resumen?->clasificacionMesMax?->nombre,
                'diverge_lista_operativa' => $this->divergeListaOperativa($cabecera),
            ]),
            'movimientos' => $periodo ? $this->movimientos($periodo, $cliente) : [],
            'documentos' => $periodo ? $this->documentos($periodo, $cliente) : [],
            'aplicaciones' => $periodo ? $this->aplicaciones($periodo, $cliente) : [],
            'incidencias' => $periodo ? $this->incidencias($periodo, $cliente) : [],
            'solicitudes_conciliacion' => $periodo
                ? array_values(array_filter(
                    $this->conciliacion->listarSolicitudesPeriodo($periodo),
                    fn (array $fila) => (int) ($fila['cliente_id'] ?? 0) === (int) $cliente->id,
                ))
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $cabecera
     */
    private function divergeListaOperativa(array $cabecera): bool
    {
        if (empty($cabecera['participa'])) {
            return false;
        }

        $operativa = $cabecera['lista_operativa_id'] ?? null;
        $vigente = $cabecera['lista_vigente_id'] ?? null;
        if (! $operativa || ! $vigente) {
            return false;
        }

        return (int) $operativa !== (int) $vigente;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function movimientos(EscalonamientoPeriodo $periodo, Cliente $cliente): array
    {
        return EscalonamientoMovimiento::query()
            ->with('documento:id,tipo,folio,serie,sucursal,total,estado,fecha_emision')
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('cliente_id', $cliente->id)
            ->orderBy('id')
            ->get()
            ->map(fn (EscalonamientoMovimiento $mov) => [
                'id' => $mov->id,
                'efecto' => (string) $mov->efecto,
                'operacion' => $mov->operacion,
                'documento' => $mov->documento ? [
                    'tipo' => $mov->documento->tipo,
                    'folio' => $mov->documento->folio,
                    'serie' => $mov->documento->serie,
                    'sucursal' => $mov->documento->sucursal,
                    'total' => (string) $mov->documento->total,
                    'estado' => $mov->documento->estado,
                    'fecha_emision' => $mov->documento->fecha_emision?->toDateString(),
                ] : null,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function documentos(EscalonamientoPeriodo $periodo, Cliente $cliente): array
    {
        return DocumentoVenta::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('cliente_id', $cliente->id)
            ->orderBy('fecha_emision')
            ->orderBy('id')
            ->get()
            ->map(fn (DocumentoVenta $doc) => [
                'id' => $doc->id,
                'tipo' => $doc->tipo,
                'folio' => $doc->folio,
                'total' => (string) $doc->total,
                'estado' => $doc->estado,
                'fecha_emision' => $doc->fecha_emision?->toDateString(),
                'remision_original' => $doc->remision_original,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function aplicaciones(EscalonamientoPeriodo $periodo, Cliente $cliente): array
    {
        return EscalonamientoAplicacionDevolucion::query()
            ->with([
                'devolucion:id,folio',
                'remisionVinculada:id,folio',
            ])
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->whereHas('devolucion', fn ($q) => $q->where('cliente_id', $cliente->id))
            ->orderByDesc('id')
            ->get()
            ->map(fn (EscalonamientoAplicacionDevolucion $app) => [
                'id' => $app->id,
                'importe' => (string) $app->importe,
                'estado' => $app->estado,
                'evidencia' => $app->evidencia,
                'folio_devolucion' => $app->devolucion?->folio,
                'folio_remision' => $app->remisionVinculada?->folio,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function incidencias(EscalonamientoPeriodo $periodo, Cliente $cliente): array
    {
        return EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('cliente_id', $cliente->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (EscalonamientoIncidencia $inc) => [
                'id' => $inc->id,
                'codigo' => $inc->codigo,
                'gravedad' => $inc->gravedad,
                'motivo' => $inc->motivo,
                'estado' => $inc->estado,
            ])
            ->all();
    }
}
