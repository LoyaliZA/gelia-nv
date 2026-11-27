<?php

namespace App\Services\ControlPedidos\Nacional\Multienvio;

use App\Models\ControlPedidos\PedidoBma;

/**
 * Regla única de folio Wizerp por envío: no se repite dentro del borrador
 * ni lo usa otro pedido (el del origen sí puede reasignarse a un envío).
 */
final class FolioWizerpMultienvio
{
    /** @param  array<string, true>  $vistos  folios ya aceptados en la misma lista (clave en minúsculas) */
    public static function asegurarDisponible(string $folio, int $origenId, array &$vistos): void
    {
        $clave = mb_strtolower($folio);
        if (isset($vistos[$clave])
            || PedidoBma::query()->where('id', '!=', $origenId)->where('folio_remision', $folio)->exists()) {
            throw MultienvioNacionalException::folioRepetido($folio);
        }
        $vistos[$clave] = true;
    }
}
