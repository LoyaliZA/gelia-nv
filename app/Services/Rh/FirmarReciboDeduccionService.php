<?php

namespace App\Services\Rh;

use App\Models\RhDeduccion;
use App\Models\User;

class FirmarReciboDeduccionService
{
    public function __construct(
        private GuardarFirmaDeduccionService $guardarFirma,
    ) {}

    public function ejecutar(User $usuario, RhDeduccion $deduccion, array $datos): RhDeduccion
    {
        $actualizacion = [];

        if (! empty($datos['firma_gerente_data'])) {
            $actualizacion['firma_gerente_path'] = $this->guardarFirma->ejecutar(
                $deduccion,
                'gerente',
                $datos['firma_gerente_data'],
            );
        }

        if (! empty($datos['firma_colaborador_data'])) {
            $actualizacion['firma_colaborador_path'] = $this->guardarFirma->ejecutar(
                $deduccion,
                'colaborador',
                $datos['firma_colaborador_data'],
            );
        }

        if ($actualizacion === []) {
            throw new \InvalidArgumentException('Debe capturar al menos una firma.');
        }

        $deduccion->update($actualizacion);

        return $deduccion->fresh();
    }
}
