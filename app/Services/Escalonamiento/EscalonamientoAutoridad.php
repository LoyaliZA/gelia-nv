<?php

namespace App\Services\Escalonamiento;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoPeriodo;

class EscalonamientoAutoridad
{
    public function __construct(
        private ParticipacionClienteEscalonamiento $participacion,
        private EscalonamientoAutoridadConfig $config,
    ) {}

    public function estaActiva(): bool
    {
        return $this->config->estaActiva();
    }

    public function periodoOperativo(): ?EscalonamientoPeriodo
    {
        $id = $this->config->periodoOficialId();
        if ($id) {
            $periodo = EscalonamientoPeriodo::query()->find($id);
            if ($periodo && $periodo->estaAbierto()) {
                return $periodo;
            }
        }

        return EscalonamientoPeriodo::query()
            ->where('estado', EscalonamientoPeriodo::ESTADO_ABIERTO)
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->first();
    }

    public function clienteGobernadoPorModulo(Cliente $cliente, ?EscalonamientoPeriodo $periodo = null): bool
    {
        if (! $this->estaActiva()) {
            return false;
        }

        $periodo ??= $this->periodoOperativo();
        if (! $periodo) {
            return false;
        }

        return $this->participacion->clienteParticipa($periodo, $cliente);
    }
}
