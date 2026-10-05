<?php

namespace App\Services\Comercial\VisitasProgramadas;

use App\Models\Comercial\VisitaClienteProgramada;
use App\Models\User;
use App\Notifications\Comercial\AlertaVisitaProgramadaNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConfirmarLlegadaVisitaProgramadaService
{
    public function handle(
        User $operador,
        VisitaClienteProgramada $visita,
        int $sucursalActivaId,
        ?string $idempotencyKey = null,
    ): VisitaClienteProgramada {
        if ((int) $visita->sucursal_id !== $sucursalActivaId) {
            throw new AuthorizationException('La visita no corresponde a la sucursal activa.');
        }

        if ($visita->estado !== VisitaClienteProgramada::ESTADO_PROGRAMADA) {
            throw ValidationException::withMessages([
                'visita' => 'Esta visita ya no está pendiente de llegada.',
            ]);
        }

        if (! $visita->fecha->isToday()) {
            throw ValidationException::withMessages([
                'visita' => 'Solo se puede confirmar la llegada de visitas programadas para hoy.',
            ]);
        }

        if ($idempotencyKey && $visita->llegada_confirmada_at) {
            return $visita;
        }

        $visita = DB::transaction(function () use ($operador, $visita) {
            $visita->forceFill([
                'estado' => VisitaClienteProgramada::ESTADO_ASISTIO,
                'llegada_confirmada_at' => now(),
                'llegada_confirmada_por_user_id' => $operador->id,
            ])->save();

            return $visita->fresh();
        });

        $registrador = $visita->registradoPor;
        if ($registrador instanceof User && (int) $registrador->id !== (int) $operador->id) {
            $visita->loadMissing('cliente');
            $cliente = $visita->cliente;
            $nombreCliente = $cliente?->nombre ?: $cliente?->nombre_razon_social ?: 'Cliente';
            $numero = $cliente?->numero_cliente ?? '';

            $clave = sprintf('visita_programada:llegada:%d', $visita->id);

            $registrador->notify(new AlertaVisitaProgramadaNotification(
                AlertaVisitaProgramadaNotification::TIPO_LLEGADA,
                'Tu cliente llegó a la tienda',
                trim("{$nombreCliente} ({$numero}) fue registrado en recepción."),
                (int) $visita->id,
                (int) $visita->sucursal_id,
                $clave,
            ));
        }

        return $visita;
    }
}
