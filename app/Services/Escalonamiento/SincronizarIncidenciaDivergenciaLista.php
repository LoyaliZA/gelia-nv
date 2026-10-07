<?php

namespace App\Services\Escalonamiento;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;

class SincronizarIncidenciaDivergenciaLista
{
    public function __construct(
        private ParticipacionClienteEscalonamiento $participacion,
    ) {}

    public function sincronizar(
        EscalonamientoPeriodo $periodo,
        Cliente $cliente,
        ?EscalonamientoResumenCliente $resumen,
    ): void {
        if (! $resumen || ! $this->participacion->clienteParticipa($periodo, $cliente)) {
            $this->cerrarAbiertas($periodo, $cliente);

            return;
        }

        $operativa = (int) ($cliente->lista_actual_id ?? 0);
        $vigente = (int) ($resumen->lista_vigente_id ?? 0);
        if ($operativa === 0 || $vigente === 0 || $operativa === $vigente) {
            $this->cerrarAbiertas($periodo, $cliente);

            return;
        }

        $existe = EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('cliente_id', $cliente->id)
            ->where('codigo', 'divergencia_lista_operativa')
            ->where('estado', 'abierta')
            ->exists();
        if ($existe) {
            return;
        }

        EscalonamientoIncidencia::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'gravedad' => 'aviso',
            'codigo' => 'divergencia_lista_operativa',
            'motivo' => 'Lista operativa del cliente (ID '.$operativa.') difiere de la lista vigente del módulo (ID '.$vigente.').',
            'estado' => 'abierta',
        ]);
    }

    private function cerrarAbiertas(EscalonamientoPeriodo $periodo, Cliente $cliente): void
    {
        EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('cliente_id', $cliente->id)
            ->where('codigo', 'divergencia_lista_operativa')
            ->where('estado', 'abierta')
            ->update([
                'estado' => 'resuelta',
                'resolucion' => 'Las listas volvieron a coincidir o el cliente no participa.',
            ]);
    }
}
