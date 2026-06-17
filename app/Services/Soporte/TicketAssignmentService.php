<?php

namespace App\Services\Soporte;

use App\Models\User;
use App\Models\SoporteTicket;
use App\Models\SoporteCatalogoEstado;

class TicketAssignmentService
{
    public function assignNextAgent(): ?int
    {
        $agentes = User::permission('soporte.gestionar')
            ->where('excluir_asignacion_tickets', false)
            ->get();

        if ($agentes->isEmpty()) {
            return null;
        }

        $estadoResueltoId = SoporteCatalogoEstado::where('nombre', 'Resuelto')->value('id');
        $estadoCerradoId = SoporteCatalogoEstado::where('nombre', 'Cerrado')->value('id');

        $agentesConConteo = $agentes->map(function ($agente) use ($estadoResueltoId, $estadoCerradoId) {
            $conteo = SoporteTicket::where('asignado_a_id', $agente->id)
                ->when($estadoResueltoId && $estadoCerradoId, fn ($q) => $q->whereNotIn('estado_id', [$estadoResueltoId, $estadoCerradoId]))
                ->count();

            return ['agente' => $agente, 'conteo' => $conteo];
        });

        $grupoMenorCarga = $agentesConConteo->sortBy('conteo')->groupBy('conteo')->first();
        $agenteSeleccionado = $grupoMenorCarga->shuffle()->first()['agente'];

        return $agenteSeleccionado->id;
    }
}
