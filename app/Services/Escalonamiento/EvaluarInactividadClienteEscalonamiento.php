<?php

namespace App\Services\Escalonamiento;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoMovimiento;
use App\Models\Escalonamiento\EscalonamientoPeriodo;

class EvaluarInactividadClienteEscalonamiento
{
    public function __construct(
        private ParticipacionClienteEscalonamiento $participacion,
    ) {}

    public function tuvoActividadCompra(EscalonamientoPeriodo $periodo, Cliente $cliente): bool
    {
        return EscalonamientoMovimiento::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('cliente_id', $cliente->id)
            ->whereHas('documento', function ($q) {
                $q->where('tipo', 'remision')
                    ->where('estado', 'activo');
            })
            ->where('efecto', '>', 0)
            ->exists();
    }

    /**
     * @return array{meses_sin_compra: int, propone_inactivo: bool}
     */
    public function evaluarContador(
        EscalonamientoPeriodo $periodo,
        Cliente $cliente,
        bool $tuvoActividad,
    ): array {
        $anterior = (int) ($cliente->escalonamiento_meses_sin_compra ?? 0);
        $meses = $tuvoActividad ? 0 : $anterior + 1;
        $umbral = (int) config('escalonamiento.meses_inactividad', 3);

        $participa = $this->participacion->clienteParticipa($periodo, $cliente);
        $bloqueado = (bool) $cliente->lista_bloqueada;
        $proponeInactivo = $participa
            && ! $bloqueado
            && ! $tuvoActividad
            && $meses >= $umbral;

        return [
            'meses_sin_compra' => $meses,
            'propone_inactivo' => $proponeInactivo,
        ];
    }
}
