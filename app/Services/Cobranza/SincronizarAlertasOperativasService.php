<?php

namespace App\Services\Cobranza;

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
        return [
            'vencimiento' => $this->vencimiento->ejecutar(),
            'limite' => $this->limite->ejecutar(),
        ];
    }
}
