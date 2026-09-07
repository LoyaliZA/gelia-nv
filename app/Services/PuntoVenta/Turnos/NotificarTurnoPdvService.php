<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\User;
use App\Notifications\PuntoVenta\AlertaTurnoPdvNotification;
use Illuminate\Support\Facades\Log;

class NotificarTurnoPdvService
{
    public function desdeEvento(
        TurnoPdv $turno,
        TurnoPdvAtencion $atencion,
        TurnoPdvEvento $evento,
        int $sucursalId,
    ): void {
        $tipoEvento = $evento->tipo_evento;

        if (! in_array($tipoEvento, [
            TurnoPdvEvento::TIPO_ASIGNADO,
            TurnoPdvEvento::TIPO_REATENCION,
            TurnoPdvEvento::TIPO_TRANSFERIDO,
        ], true)) {
            return;
        }

        $userId = (int) $atencion->user_id;
        if ($userId <= 0) {
            return;
        }

        try {
            $destinatario = User::query()
                ->whereKey($userId)
                ->whereHas(
                    'sucursalesOperables',
                    fn ($query) => $query->where('sucursales.id', $sucursalId)
                )
                ->first();

            if (! $destinatario instanceof User) {
                return;
            }

            $claveNotificacion = $this->claveNotificacion($tipoEvento, $evento);
            if ($this->yaNotificado($destinatario, $claveNotificacion)) {
                return;
            }

            [$titulo, $mensaje] = $this->textos($tipoEvento, $turno->folio);

            $destinatario->notify(new AlertaTurnoPdvNotification(
                $this->tipoAlerta($tipoEvento),
                $titulo,
                $mensaje,
                (int) $turno->id,
                (string) $turno->folio,
                $sucursalId,
                $claveNotificacion,
            ));
        } catch (\Throwable $e) {
            Log::error('No se pudo notificar turno PDV', [
                'turno_id' => $turno->id,
                'tipo' => $tipoEvento,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    private function tipoAlerta(string $tipoEvento): string
    {
        return match ($tipoEvento) {
            TurnoPdvEvento::TIPO_REATENCION => AlertaTurnoPdvNotification::TIPO_REATENCION,
            TurnoPdvEvento::TIPO_TRANSFERIDO => AlertaTurnoPdvNotification::TIPO_TRANSFERIDO,
            default => AlertaTurnoPdvNotification::TIPO_ASIGNADO,
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function textos(string $tipoEvento, string $folio): array
    {
        return match ($tipoEvento) {
            TurnoPdvEvento::TIPO_REATENCION => [
                'Reatención de turno',
                "Turno {$folio} listo para reatención.",
            ],
            TurnoPdvEvento::TIPO_TRANSFERIDO => [
                'Turno transferido',
                "Turno {$folio} transferido a este puesto.",
            ],
            default => [
                'Turno asignado',
                "Turno {$folio} asignado para atención.",
            ],
        };
    }

    private function claveNotificacion(string $tipoEvento, TurnoPdvEvento $evento): string
    {
        $eventoId = (int) $evento->id;
        $claveEvento = trim((string) ($evento->idempotency_key ?? ''));

        if ($claveEvento !== '') {
            return "pdv:notify:{$tipoEvento}:{$claveEvento}";
        }

        return "pdv:notify:{$tipoEvento}:{$eventoId}";
    }

    private function yaNotificado(User $destinatario, string $idempotencyKey): bool
    {
        return $destinatario->notifications()
            ->where('type', AlertaTurnoPdvNotification::class)
            ->where('data->idempotency_key', $idempotencyKey)
            ->exists();
    }
}
