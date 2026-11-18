<?php

namespace App\Support\ControlPedidos\Conversion;

use Illuminate\Support\Facades\Cache;

/**
 * Reserva exclusiva por instancia. No clonar RevisionEnCursoPedidoBma.
 * El caller hace lockForUpdate del pedido ANTES de leer o escribir caché.
 */
final class ConversionModalidadEnCursoPedidoBma
{
    public const TTL_SEGUNDOS = 45;

    public static function clave(int $pedidoId): string
    {
        return "pedido_bma.conversion_en_curso.{$pedidoId}";
    }

    /**
     * Cache::add. Misma instancia renueva. Otra instancia: false (409 conversion_en_curso).
     * No usar Cache::put para adquirir.
     */
    public static function adquirir(int $pedidoId, int $usuarioId, string $instanciaId): bool
    {
        $actual = self::payload($pedidoId);
        if (self::esPropietaria($actual, $usuarioId, $instanciaId)) {
            return self::renovar($pedidoId, $usuarioId, $instanciaId);
        }

        $payload = (new PayloadReservaConversion(
            $usuarioId,
            $instanciaId,
            now()->toIso8601String(),
        ))->toArray();

        return Cache::add(self::clave($pedidoId), $payload, self::TTL_SEGUNDOS);
    }

    /** Cache::put + TTL solo si usuario_id e instancia_id coinciden. Si caducó, reintentar add. */
    public static function renovar(int $pedidoId, int $usuarioId, string $instanciaId): bool
    {
        $actual = self::payload($pedidoId);
        if ($actual === null) {
            return Cache::add(
                self::clave($pedidoId),
                (new PayloadReservaConversion($usuarioId, $instanciaId, now()->toIso8601String()))->toArray(),
                self::TTL_SEGUNDOS
            );
        }
        if (! self::esPropietaria($actual, $usuarioId, $instanciaId)) {
            return false;
        }

        Cache::put(
            self::clave($pedidoId),
            (new PayloadReservaConversion($usuarioId, $instanciaId, now()->toIso8601String()))->toArray(),
            self::TTL_SEGUNDOS
        );

        return true;
    }

    /** forget solo si usuario_id e instancia_id coinciden. Token ajeno no suelta. */
    public static function soltar(int $pedidoId, int $usuarioId, string $instanciaId): bool
    {
        $actual = self::payload($pedidoId);
        if (! self::esPropietaria($actual, $usuarioId, $instanciaId)) {
            return false;
        }
        Cache::forget(self::clave($pedidoId));

        return true;
    }

    public static function payload(?int $pedidoId): ?PayloadReservaConversion
    {
        if (! $pedidoId) {
            return null;
        }

        $datos = Cache::get(self::clave($pedidoId));

        return PayloadReservaConversion::fromArray(is_array($datos) ? $datos : null);
    }

    public static function activa(?int $pedidoId): bool
    {
        return self::payload($pedidoId) !== null;
    }

    public static function esPropietaria(?PayloadReservaConversion $payload, int $usuarioId, string $instanciaId): bool
    {
        return $payload !== null
            && $payload->usuario_id === $usuarioId
            && $payload->instancia_id === $instanciaId;
    }

    public static function instanciaOpaca(?PayloadReservaConversion $payload): ?string
    {
        if ($payload === null || $payload->instancia_id === '') {
            return null;
        }

        return substr(hash('sha256', $payload->instancia_id), 0, 16);
    }
}
