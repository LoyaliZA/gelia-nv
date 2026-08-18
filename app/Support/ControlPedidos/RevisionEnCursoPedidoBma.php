<?php

namespace App\Support\ControlPedidos;

use Illuminate\Support\Facades\Cache;

/**
 * Presencia corta: la auxiliar tiene el modal de revisar abierto.
 * TTL cubre un heartbeat perdido; si cierra la pestaña, caduca solo.
 */
final class RevisionEnCursoPedidoBma
{
    public const TTL_SEGUNDOS = 45;

    public static function clave(int $pedidoId): string
    {
        return "pedido_bma.revision_en_curso.{$pedidoId}";
    }

    public static function marcar(int $pedidoId, int $usuarioId): void
    {
        Cache::put(self::clave($pedidoId), [
            'usuario_id' => $usuarioId,
            'at' => now()->toIso8601String(),
        ], self::TTL_SEGUNDOS);
    }

    public static function soltar(int $pedidoId, int $usuarioId): void
    {
        $actual = Cache::get(self::clave($pedidoId));
        if ((int) ($actual['usuario_id'] ?? 0) === $usuarioId) {
            Cache::forget(self::clave($pedidoId));
        }
    }

    public static function activa(?int $pedidoId): bool
    {
        if (! $pedidoId) {
            return false;
        }

        return Cache::has(self::clave($pedidoId));
    }
}
