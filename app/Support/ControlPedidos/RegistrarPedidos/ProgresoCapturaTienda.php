<?php

namespace App\Support\ControlPedidos\RegistrarPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;

/** La modalidad Tienda no hereda pesaje, cotización ni pago. */
final class ProgresoCapturaTienda
{
    /**
     * @param  array<string, mixed>|null  $solicitud
     */
    public static function aplica(?array $solicitud): bool
    {
        if (! is_array($solicitud)) {
            return false;
        }

        $modalidad = $solicitud['modalidad_entrega'] ?? null;
        if ($modalidad === 'tienda') {
            return true;
        }
        if (in_array($modalidad, ['municipal', 'nacional'], true)) {
            return false;
        }

        $codigo = $solicitud['codigo_modalidad'] ?? null;
        if (! in_array($codigo, CatalogoModalidadPreparacionPedido::CODIGOS_FASE4, true)) {
            return false;
        }

        return array_key_exists('atencion_tienda', $solicitud) || array_key_exists('piezas_tienda', $solicitud);
    }

    /**
     * @return array{etapa_actual: string, etapas: list<array<string, mixed>>, accion_recomendada: string, bloqueos: list<string>, perfil: string}
     */
    public static function respuesta(): array
    {
        return [
            'etapa_actual' => 'tienda',
            'etapas' => [[
                'codigo' => 'tienda',
                'estado' => 'activa',
                'editable' => true,
                'motivo_bloqueo' => null,
            ]],
            'accion_recomendada' => 'La modalidad Tienda no pide pesaje, cotización ni pago para guardar.',
            'bloqueos' => [],
            'perfil' => 'tienda',
        ];
    }
}
