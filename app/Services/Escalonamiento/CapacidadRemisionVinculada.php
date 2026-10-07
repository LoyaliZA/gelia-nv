<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\EscalonamientoAplicacionDevolucion;

class CapacidadRemisionVinculada
{
    public function sumaActiva(int $remisionId, bool $bloquear = false): string
    {
        $consulta = EscalonamientoAplicacionDevolucion::query()
            ->where('documento_remision_vinculada_id', $remisionId)
            ->where('estado', EscalonamientoAplicacionDevolucion::ESTADO_ACTIVA);

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        $suma = '0.00';
        foreach ($consulta->get(['importe']) as $aplicacion) {
            $suma = bcadd($suma, $this->dinero($aplicacion->importe), 2);
        }

        return $suma;
    }

    public function acepta(string $totalRemision, string $sumaActiva, string $importeNuevo): bool
    {
        $ocupada = bcadd($this->dinero($sumaActiva), $this->dinero($importeNuevo), 2);

        return bccomp($ocupada, $this->dinero($totalRemision), 2) < 0;
    }

    private function dinero(float|int|string $valor): string
    {
        return bcadd((string) $valor, '0', 2);
    }
}
