<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\EscalonamientoPeriodo;

class ResolverResultadoClienteEscalonamiento
{
    public function __construct(
        private ListasPeriodoEscalonamiento $listasPeriodo,
    ) {}

    /**
     * @param  array{
     *     bloqueo: bool,
     *     participa: bool,
     *     incidencias_abiertas: int,
     *     lista_operativa_id: ?int,
     *     lista_vigente_id: ?int,
     *     lista_siguiente_propuesta_id: ?int,
     *     cumple_mantenimiento: bool,
     *     propone_inactivo: bool,
     * }  $contexto
     */
    public function resolver(EscalonamientoPeriodo $periodo, array $contexto): string
    {
        $bloqueo = (bool) ($contexto['bloqueo'] ?? false);
        $participa = (bool) ($contexto['participa'] ?? false);
        $incidencias = (int) ($contexto['incidencias_abiertas'] ?? 0);
        $listaOperativaId = $contexto['lista_operativa_id'] ?? null;
        $listaVigenteId = $contexto['lista_vigente_id'] ?? null;
        $listaSiguienteId = $contexto['lista_siguiente_propuesta_id'] ?? null;
        $cumpleMantenimiento = (bool) ($contexto['cumple_mantenimiento'] ?? false);
        $proponeInactivo = (bool) ($contexto['propone_inactivo'] ?? false);

        if ($bloqueo) {
            return 'bloqueado';
        }
        if (! $participa) {
            return 'no_participa';
        }
        if ($incidencias > 0) {
            return 'revision_pendiente';
        }
        if ($proponeInactivo) {
            return 'inactividad';
        }

        if (! $listaSiguienteId) {
            return 'sin_cambio';
        }

        if ($listaOperativaId) {
            $vsOperativa = $this->listasPeriodo->compararJerarquia($periodo, $listaSiguienteId, $listaOperativaId);
            if ($vsOperativa > 0) {
                return 'ascenso';
            }
            if ($vsOperativa < 0) {
                return 'descenso';
            }
        }

        if ($listaVigenteId) {
            $vsVigente = $this->listasPeriodo->compararJerarquia($periodo, $listaSiguienteId, $listaVigenteId);
            if ($vsVigente < 0) {
                return 'descenso';
            }
            if ($vsVigente === 0 && ! $cumpleMantenimiento) {
                return 'descenso';
            }
        }

        return 'sin_cambio';
    }

    /**
     * @return list<string>
     */
    public function codigosValidos(): array
    {
        return [
            'sin_cambio',
            'ascenso',
            'descenso',
            'no_participa',
            'bloqueado',
            'revision_pendiente',
            'inactividad',
        ];
    }
}
