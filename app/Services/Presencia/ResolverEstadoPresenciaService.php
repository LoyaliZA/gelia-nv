<?php

namespace App\Services\Presencia;

use App\Support\PresenciaCatalogo;

class ResolverEstadoPresenciaService
{
    /**
     * La presencia visible ya no usa estados de trabajo. El slug interno se conserva
     * para no reescribir preferencias guardadas.
     */
    public function estadoEfectivo(array $prefs): string
    {
        return PresenciaCatalogo::defaults()['estado'] ?? 'disponible';
    }
}
