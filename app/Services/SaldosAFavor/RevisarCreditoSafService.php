<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\SafCredito;
use InvalidArgumentException;

class RevisarCreditoSafService
{
    public function handle(
        int $creditoId,
        string $estadoRevision,
        ?int $usuarioId = null,
        ?string $observaciones = null,
    ): SafCredito {
        $permitidos = [
            SafCredito::REVISION_REVISADO,
            SafCredito::REVISION_CON_DIFERENCIA,
            SafCredito::REVISION_REQUIERE_CORRECCION,
            SafCredito::REVISION_RECHAZADO,
            SafCredito::REVISION_AJUSTADO,
            SafCredito::REVISION_PENDIENTE,
        ];
        if (! in_array($estadoRevision, $permitidos, true)) {
            throw new InvalidArgumentException('Estado de revisión inválido.');
        }

        $credito = SafCredito::findOrFail($creditoId);
        $credito->estado_revision = $estadoRevision;
        $credito->observaciones_revision = $observaciones;
        $credito->revisado_por_id = $usuarioId;
        $credito->revisado_at = now();
        $credito->save();

        return $credito->fresh(['revisadoPor', 'motivo']);
    }
}
