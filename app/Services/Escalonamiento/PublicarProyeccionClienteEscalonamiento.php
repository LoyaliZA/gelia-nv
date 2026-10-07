<?php

namespace App\Services\Escalonamiento;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;

class PublicarProyeccionClienteEscalonamiento
{
    public function __construct(
        private EscalonamientoAutoridad $autoridad,
        private SincronizarIncidenciaDivergenciaLista $divergenciaLista,
    ) {}

    public function desdeResumen(
        EscalonamientoPeriodo $periodo,
        Cliente $cliente,
        EscalonamientoResumenCliente $resumen,
    ): void {
        if (! $this->autoridad->estaActiva()) {
            return;
        }

        $operativo = $this->autoridad->periodoOperativo();
        if (! $operativo || (int) $operativo->id !== (int) $periodo->id) {
            return;
        }

        if (! $this->autoridad->clienteGobernadoPorModulo($cliente, $periodo)) {
            return;
        }

        $montoNuevo = $this->dinero($resumen->acumulado);
        $listaVigenteId = $resumen->lista_vigente_id ? (int) $resumen->lista_vigente_id : null;

        $cambio = false;

        if (bccomp($this->dinero($cliente->monto_venta_actual), $montoNuevo, 2) !== 0) {
            $cliente->monto_venta_actual = $montoNuevo;
            $cambio = true;
        }

        if (
            $listaVigenteId
            && ! $cliente->lista_bloqueada
            && (int) ($cliente->lista_actual_id ?? 0) !== $listaVigenteId
        ) {
            $cliente->lista_actual_id = $listaVigenteId;
            $cambio = true;
        }

        if ($cambio) {
            $cliente->save();
        }

        $this->divergenciaLista->sincronizar($periodo, $cliente->fresh(), $resumen->fresh());
    }

    private function dinero(float|int|string|null $valor): string
    {
        return bcadd((string) ($valor ?? '0'), '0', 2);
    }
}
