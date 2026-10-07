<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\EscalonamientoCierre;
use App\Services\Escalonamiento\Excepciones\CierreEscalonamientoException;

class RegistrarAplicacionExternaErp
{
    public function registrar(EscalonamientoCierre $cierre, string $evidencia): EscalonamientoCierre
    {
        $evidencia = trim($evidencia);
        if ($evidencia === '') {
            throw new CierreEscalonamientoException('La evidencia de aplicación en ERP es obligatoria.');
        }

        if (! $cierre->estaAplicado()) {
            throw new CierreEscalonamientoException('Solo se registra aplicación externa tras el cierre interno.');
        }

        $cierre->aplicacion_externa_declarada = true;
        $cierre->aplicacion_externa_en = now();
        $cierre->aplicacion_externa_evidencia = $evidencia;
        $cierre->save();

        return $cierre;
    }
}
