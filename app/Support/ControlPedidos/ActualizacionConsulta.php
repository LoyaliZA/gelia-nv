<?php

namespace App\Support\ControlPedidos;

use Illuminate\Support\Str;

final class ActualizacionConsulta
{
    public static function pendiente(array $json): bool
    {
        return ! empty($json['revision_disponibilidad']['actualizacion_pendiente'])
            || collect($json['anexo_sin_remision'] ?? [])->contains(fn ($a) => empty($a['respondida_at']));
    }

    public static function solicitar(array &$json): void
    {
        if (empty($json['revision_disponibilidad']['estado'])) {
            return;
        }
        $json['revision_disponibilidad']['actualizacion_pendiente'] = true;
        $json['revision_disponibilidad']['actualizacion_id'] = (string) Str::uuid();
        $json['revision_disponibilidad']['aceptacion_vigente'] = false;
    }
}
