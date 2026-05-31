<?php

namespace App\Services\Presencia;

use App\Support\PresenciaCatalogo;
use Carbon\Carbon;

class ResolverEstadoPresenciaService
{
    public function estadoEfectivo(array $prefs): string
    {
        $catalogo = PresenciaCatalogo::slugs();
        $defecto = PresenciaCatalogo::defaults()['estado'] ?? 'disponible';

        $modo = $prefs['modo'] ?? 'automatico';
        $manual = $prefs['estado'] ?? $defecto;
        if (!in_array($manual, $catalogo, true)) {
            $manual = $defecto;
        }

        if ($modo === 'manual') {
            $expira = $prefs['expira_at'] ?? null;
            if ($expira && Carbon::parse($expira)->isPast()) {
                return $defecto;
            }

            return $manual;
        }

        if (!($prefs['automatizar'] ?? true)) {
            return $defecto;
        }

        $ahora = now();

        foreach ($prefs['horarios'] ?? [] as $regla) {
            if ($this->coincideHorario($regla, $ahora)) {
                $estado = $regla['estado'] ?? null;
                if ($estado && in_array($estado, $catalogo, true)) {
                    return $estado;
                }
            }
        }

        $inactividad = (int) ($prefs['inactividad_minutos'] ?? 0);
        $ultima = $prefs['ultima_actividad_at'] ?? null;
        if ($inactividad > 0 && $ultima) {
            $minutos = Carbon::parse($ultima)->diffInMinutes($ahora);
            if ($minutos >= $inactividad) {
                $estadoInactivo = $prefs['inactividad_estado'] ?? 'ausente';
                if (in_array($estadoInactivo, $catalogo, true)) {
                    return $estadoInactivo;
                }
            }
        }

        return $defecto;
    }

    private function coincideHorario(array $regla, Carbon $ahora): bool
    {
        $dias = $regla['dias'] ?? [];
        if ($dias !== [] && !in_array((int) $ahora->dayOfWeekIso, array_map('intval', $dias), true)) {
            return false;
        }

        $inicio = $regla['inicio'] ?? null;
        $fin = $regla['fin'] ?? null;
        if (!$inicio || !$fin) {
            return false;
        }

        $hora = $ahora->format('H:i');

        if ($inicio <= $fin) {
            return $hora >= $inicio && $hora < $fin;
        }

        return $hora >= $inicio || $hora < $fin;
    }
}
