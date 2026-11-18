<?php

namespace App\Services\ControlPedidos\Conversion;

use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\Conversion\CodigosConversionModalidad;

class ResolverIdempotenciaConversion
{
    /** @return array<string, mixed>|null */
    public function confirmada(PedidoBma $pedido, string $clave): ?array
    {
        if ($clave === '') {
            return null;
        }
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $vigente = is_array($json['conversion_modalidad'] ?? null) ? $json['conversion_modalidad'] : [];
        if ($this->esConfirmadaConClave($vigente, $clave)) {
            return $vigente;
        }
        foreach ($vigente['historial'] ?? [] as $snapshot) {
            if (is_array($snapshot) && $this->esConfirmadaConClave($snapshot, $clave)) {
                return $snapshot;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $fila */
    private function esConfirmadaConClave(array $fila, string $clave): bool
    {
        return ($fila['estado'] ?? null) === CodigosConversionModalidad::ESTADO_CONFIRMADA
            && (string) ($fila['clave_idempotencia'] ?? '') === $clave;
    }
}
