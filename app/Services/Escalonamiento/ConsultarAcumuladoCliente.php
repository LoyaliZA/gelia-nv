<?php

namespace App\Services\Escalonamiento;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;

class ConsultarAcumuladoCliente
{
    public function __construct(
        private ParticipacionClienteEscalonamiento $participacion,
        private EvaluarListaClienteEscalonamiento $evaluarLista,
    ) {}

    /**
     * Lectura del ledger para las pantallas del módulo. No escribe el cliente.
     *
     * @return array<string, mixed>
     */
    public function consultar(Cliente $cliente, ?EscalonamientoPeriodo $periodo = null): array
    {
        $periodo ??= EscalonamientoPeriodo::query()
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->first();

        $cliente->loadMissing('listaDescuento');

        $resumen = null;
        if ($periodo) {
            $resumen = EscalonamientoResumenCliente::query()
                ->with(['clasificacionMes', 'listaBase', 'listaVigente'])
                ->where('escalonamiento_periodo_id', $periodo->id)
                ->where('cliente_id', $cliente->id)
                ->first();
        }

        $proyeccion = $periodo
            ? $this->evaluarLista->proyeccionLectura($periodo, $cliente, $resumen)
            : [];

        $listaBaseId = $resumen?->lista_base_id ?? ($proyeccion['lista_base_id'] ?? null);
        $listaVigenteId = $resumen?->lista_vigente_id ?? ($proyeccion['lista_vigente_id'] ?? null);

        return [
            'cliente_id' => $cliente->id,
            'numero_cliente' => $cliente->numero_cliente,
            'nombre' => $cliente->nombre,
            'periodo_id' => $periodo?->id,
            'anio' => $periodo?->anio,
            'mes' => $periodo?->mes,
            'estado_periodo' => $periodo?->estado,
            'corte' => $periodo?->fecha_corte?->toDateTimeString(),
            'acumulado' => $resumen ? (string) $resumen->acumulado : '0.00',
            'lista_base_id' => $listaBaseId,
            'lista_base' => $resumen?->listaBase?->nombre
                ?? $this->evaluarLista->nombreLista($listaBaseId),
            'clasificacion_mes_id' => $resumen?->clasificacion_mes_id ?? ($proyeccion['clasificacion_mes_id'] ?? null),
            'clasificacion_mes' => $resumen?->clasificacionMes?->nombre
                ?? $this->evaluarLista->nombreLista($proyeccion['clasificacion_mes_id'] ?? null),
            'lista_vigente_id' => $listaVigenteId,
            'lista_vigente' => $resumen?->listaVigente?->nombre
                ?? $this->evaluarLista->nombreLista($listaVigenteId),
            'lista_operativa_id' => $cliente->lista_actual_id,
            'lista_operativa' => $cliente->listaDescuento?->nombre,
            'participa' => $periodo
                ? $this->participacion->clienteParticipa($periodo, $cliente)
                : (bool) $cliente->listaDescuento?->participa_escalonamiento,
            'bloqueo' => (bool) $cliente->lista_bloqueada,
        ];
    }
}
