<?php

namespace App\Services\Cobranza;

use App\Models\CobranzaBitacora;
use App\Models\CobranzaFactura;

class SincronizarAlertasOperativasService
{
    public function __construct(
        private SincronizarAlertasVencimientoService $vencimiento,
        private SincronizarAlertasLimiteService $limite,
    ) {}

    /**
     * @return array{vencimiento: array{resueltas: int, creadas: int, actualizadas: int}, limite: array{resueltas: int, creadas: int, actualizadas: int}}
     */
    public function ejecutar(): array
    {
        $this->limpiarBanderasAbonoSinBitacora();

        return [
            'vencimiento' => $this->vencimiento->ejecutar(),
            'limite' => $this->limite->ejecutar(),
        ];
    }

    private function limpiarBanderasAbonoSinBitacora(): void
    {
        $clienteIdsConAbonoBitacora = CobranzaBitacora::query()
            ->where('tipo_evento', 'abono')
            ->distinct()
            ->pluck('cliente_id');

        CobranzaFactura::query()
            ->where('tiene_abono', true)
            ->where('pagada', false)
            ->whereNotIn('cliente_id', $clienteIdsConAbonoBitacora)
            ->update(['tiene_abono' => false]);
    }
}
