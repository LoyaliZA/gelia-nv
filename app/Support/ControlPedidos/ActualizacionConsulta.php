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
        return collect($json['anexo_sin_remision'] ?? [])->contains(self::anexoPendiente(...));
    }

    private static function anexoPendiente(mixed $anexo): bool
    {
        return is_array($anexo)
            && ! filled($anexo['respondida_at'] ?? null)
            && ($anexo['pendiente_revision'] ?? null) !== false
            && ! in_array((string) ($anexo['estado'] ?? ''), ['disponible', 'con_detalle', 'rechazado'], true);
    }

    /** @return list<int> */
    public static function revisionesEnCurso(array $json): array
    {
        $ids = [];
        foreach ($json['anexo_sin_remision'] ?? [] as $anexo) {
            if (! self::anexoPendiente($anexo)) {
                continue;
            }
            foreach ($anexo['unidades'] ?? [] as $unidad) {
                foreach ($unidad['revision_ids'] ?? [] as $id) {
                    $ids[] = (int) $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public static function solicitar(array &$json, bool $forzar = false): void
    {
        if (empty($json['revision_disponibilidad']['estado']) || (! $forzar && ! self::requiereRespuesta($json))) {
            return;
        }
        $revision = $json['revision_disponibilidad'];
        // Un cambio nuevo se suma a la consulta abierta: conserva su identidad para no invalidar
        // ni descartar lo que quien responde ya tiene en curso. Solo un ciclo nuevo (o reapertura) la renueva.
        $abierta = ($revision['actualizacion_pendiente'] ?? false) === true
            && is_string($revision['actualizacion_id'] ?? null) && $revision['actualizacion_id'] !== '';
        $json['revision_disponibilidad']['actualizacion_pendiente'] = true;
        if ($forzar || ! $abierta) {
            $json['revision_disponibilidad']['actualizacion_id'] = (string) Str::uuid();
        }
        $json['revision_disponibilidad']['aceptacion_vigente'] = false;
    }
}
