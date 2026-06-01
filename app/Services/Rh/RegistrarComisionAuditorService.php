<?php

namespace App\Services\Rh;

use App\Models\CatalogoReglaIncidencia;
use App\Models\RhComisionAuditor;
use App\Models\RhDeduccion;
use App\Models\User;
use Illuminate\Support\Str;

class RegistrarComisionAuditorService
{
    public function ejecutar(User $auditor, RhDeduccion $deduccion, CatalogoReglaIncidencia $regla): ?RhComisionAuditor
    {
        if (!$regla->recompensa_auditor_activa) {
            return null;
        }

        $monto = (float) $regla->monto_recompensa_auditor;
        if ($monto <= 0) {
            return null;
        }

        return RhComisionAuditor::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $auditor->id,
            'rh_deduccion_id' => $deduccion->id,
            'catalogo_regla_incidencia_id' => $regla->id,
            'monto' => $monto,
            'estado' => 'pendiente',
            'fecha_acreditacion' => now(),
        ]);
    }
}
