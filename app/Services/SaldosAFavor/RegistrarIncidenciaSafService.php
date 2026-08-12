<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\SafIncidencia;
use App\Models\User;
use App\Notifications\SaldoFavorIncidenciaAbiertaNotification;
use Illuminate\Support\Facades\Notification;

class RegistrarIncidenciaSafService
{
    public function handle(
        string $tipo,
        string $descripcion,
        ?int $clienteId = null,
        ?int $pedidoBmaId = null,
        ?int $creditoId = null,
        ?int $usuarioId = null,
    ): SafIncidencia {
        $incidencia = SafIncidencia::create([
            'cliente_id' => $clienteId,
            'saf_credito_id' => $creditoId,
            'pedido_bma_id' => $pedidoBmaId,
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'estado' => SafIncidencia::ESTADO_ABIERTA,
            'creado_por_id' => $usuarioId,
        ]);

        $revisores = User::permission('saldos_favor.revisar')->get();
        if ($revisores->isNotEmpty()) {
            Notification::send($revisores, new SaldoFavorIncidenciaAbiertaNotification($incidencia));
        }

        return $incidencia;
    }

    public function resolver(SafIncidencia $incidencia, ?int $usuarioId = null, ?string $nota = null): SafIncidencia
    {
        $descripcion = $incidencia->descripcion;
        if (filled($nota)) {
            $descripcion = trim($descripcion."\n\n[Resolución] ".$nota);
        }

        $updated = $incidencia->update([
            'descripcion' => $descripcion,
            'estado' => SafIncidencia::ESTADO_RESUELTA,
            'resuelto_por_id' => $usuarioId,
            'resuelto_at' => now(),
        ]);

        if (! $updated) {
            throw new \RuntimeException('No se pudo resolver la incidencia.');
        }

        // Evitar fresh(): puede devolver null y romper el return type (500).
        return $incidencia;
    }
}
