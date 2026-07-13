<?php

namespace App\Services\ControlPedidos;

class CalcularSeguroPedidoService
{
    public const COMERCIALES_CON_COBERTURA = ['FEDEX', 'ESTAFETA', 'DHL'];

    public function calcularCosto(?string $nombrePaqueteria, float $envio, float $totalMercancia): float
    {
        $nombre = mb_strtoupper(trim((string) $nombrePaqueteria));

        if (!in_array($nombre, self::COMERCIALES_CON_COBERTURA, true)) {
            return 0.0;
        }

        $base = $envio + $totalMercancia;

        $costo = match ($nombre) {
            'DHL' => ($base * 0.02) + 51,
            default => $base * 0.025,
        };

        return round($costo, 2);
    }

    public function ejecutar(?string $nombrePaqueteria, float $envio, float $totalMercancia): array
    {
        $costo = $this->calcularCosto($nombrePaqueteria, $envio, $totalMercancia);

        return [
            'aplica_seguro' => $this->tieneCobertura($nombrePaqueteria),
            'costo_seguro' => $costo,
        ];
    }

    public function tieneCobertura(?string $nombrePaqueteria): bool
    {
        $nombre = mb_strtoupper(trim((string) $nombrePaqueteria));

        return in_array($nombre, self::COMERCIALES_CON_COBERTURA, true);
    }
}
