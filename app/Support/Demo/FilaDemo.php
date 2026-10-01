<?php

namespace App\Support\Demo;

use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\Scopes\EsDemoScope;

final class FilaDemo
{
    public static function es(object $modelo): bool
    {
        return (bool) ($modelo->es_demo ?? false);
    }

    public static function atencionEsDemo(TurnoPdvAtencion $atencion): bool
    {
        $turno = TurnoPdv::withoutGlobalScope(EsDemoScope::class)
            ->whereKey($atencion->turno_id)
            ->first(['id', 'es_demo']);

        return (bool) ($turno?->es_demo);
    }
}
