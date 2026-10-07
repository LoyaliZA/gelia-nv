<?php

namespace App\Services\Escalonamiento;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Services\Clientes\ResolucionListaWizerp;

class ParticipacionClienteEscalonamiento
{
    public function __construct(
        private ListasPeriodoEscalonamiento $listasPeriodo,
        private ResolucionListaWizerp $resolucionLista,
    ) {}

    /**
     * Períodos editables: catálogo vigente (misma lógica que importación de clientes).
     * Períodos con cierre aplicado: snapshot congelado del período.
     */
    public function clienteParticipa(EscalonamientoPeriodo $periodo, Cliente $cliente): bool
    {
        $listaId = (int) ($cliente->lista_actual_id ?? 0);
        if ($listaId === 0) {
            return false;
        }

        if ($periodo->permiteEscrituraMovimientos()) {
            $cliente->loadMissing('listaDescuento');

            return $this->resolucionLista->listaParticipaEnEscalonamiento($cliente->listaDescuento);
        }

        return $this->listasPeriodo->listaParticipaEnPeriodo($periodo, $listaId);
    }
}
