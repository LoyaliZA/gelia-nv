<?php

namespace App\Support\ControlPedidos;

use Illuminate\Support\Str;

final class ActualizacionConsulta
{
    public static function pendiente(array $json): bool
    {
        return self::requiereRespuesta($json)
            || (bool) ($json['revision_disponibilidad']['actualizacion_pendiente'] ?? false);
    }

    /** Un retiro se informa; solo un anexo sin responder o una reapertura exige respuesta. */
    public static function requiereRespuesta(array $json): bool
    {
        return collect($json['anexo_sin_remision'] ?? [])->contains(function ($anexo) {
            if (! is_array($anexo) || filled($anexo['respondida_at'] ?? null)) {
                return false;
            }
            if (($anexo['pendiente_revision'] ?? null) === false) {
                return false;
            }

            return ! in_array((string) ($anexo['estado'] ?? ''), ['disponible', 'con_detalle', 'rechazado'], true);
        });
    }

    public static function solicitar(array &$json, bool $forzar = false): void
    {
        if (empty($json['revision_disponibilidad']['estado']) || (! $forzar && ! self::requiereRespuesta($json))) {
            return;
        }
        $json['revision_disponibilidad']['actualizacion_pendiente'] = true;
        $json['revision_disponibilidad']['actualizacion_id'] = (string) Str::uuid();
        $json['revision_disponibilidad']['aceptacion_vigente'] = false;
    }
}
