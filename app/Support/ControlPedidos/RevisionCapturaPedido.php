<?php
namespace App\Support\ControlPedidos;

use App\Exceptions\ControlPedidos\ConflictoCapturaPedido;
use App\Models\ControlPedidos\PedidoBma;

/** La revisión cuenta guardados comerciales; la huella detecta también escritores operativos. */
final class RevisionCapturaPedido
{
    public static function base(PedidoBma $pedido): string
    {
        $attrs = $pedido->getRawOriginal();
        foreach (['created_at', 'updated_at', 'captura_creacion_clave', 'captura_creacion_hash', 'captura_creacion_base'] as $clave) unset($attrs[$clave]);
        ksort($attrs);
        return hash('sha256', json_encode($attrs, JSON_THROW_ON_ERROR));
    }

    public static function comprobar(PedidoBma $pedido, array $datos): void
    {
        if (!isset($datos['revision_esperada'], $datos['base_captura'])
            || !is_string($datos['base_captura']) || !is_numeric($datos['revision_esperada'])
            || (int) $datos['revision_esperada'] !== (int) $pedido->revision_registro
            || !hash_equals(self::base($pedido), (string) $datos['base_captura'])) {
            throw new ConflictoCapturaPedido;
        }
    }
}
