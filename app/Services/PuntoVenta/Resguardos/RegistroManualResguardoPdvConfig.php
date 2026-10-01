<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Models\ConfiguracionSistema;
use App\Models\User;

class RegistroManualResguardoPdvConfig
{
    public const CLAVE = 'pdv.resguardos.registro_manual';

    public function estaActivo(): bool
    {
        $row = ConfiguracionSistema::query()->where('clave', self::CLAVE)->first();
        if (! $row) {
            return false;
        }

        $valor = $row->valor;
        if (is_bool($valor)) {
            return $valor;
        }

        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    public function estaActivoPara(?User $user): bool
    {
        if ($user instanceof User && $user->es_demo) {
            return true;
        }

        return $this->estaActivo();
    }
}
