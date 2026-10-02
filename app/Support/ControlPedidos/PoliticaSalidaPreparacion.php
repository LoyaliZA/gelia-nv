<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;

/**
 * Reglas de liberación física. La separación no consulta esta política.
 */
final class PoliticaSalidaPreparacion
{
    public const COBRO_PAGADO = 'PAGADO';

    public const COBRO_POR_COBRAR = 'POR_COBRAR';

    public function __construct(
        private readonly ?string $codigoModalidad,
        private readonly ?CatalogoPaqueteriaPedido $paqueteria = null,
    ) {}

    public static function desdeTarea(PedidoBmaTareaPreparacion $tarea): self
    {
        $tarea->loadMissing(['modalidad', 'paqueteria']);

        return new self($tarea->modalidad?->codigo, $tarea->paqueteria);
    }

    public function esRecogeHoy(): bool
    {
        return $this->codigoModalidad === CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA;
    }

    public function esTransferenciaDiferida(): bool
    {
        return $this->codigoModalidad === CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_TRANSFERENCIA;
    }

    public function esEnvioMunicipio(): bool
    {
        return $this->codigoModalidad === CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO;
    }

    public function cierraEnPdv(): bool
    {
        return $this->esRecogeHoy() || $this->esTransferenciaDiferida();
    }

    public function usaSalidaInterna(): bool
    {
        return $this->cierraEnPdv() || $this->esEnvioMunicipio();
    }

    public function exigeRemisionParaCierre(): bool
    {
        return ! $this->cierraEnPdv() && ! $this->esEnvioMunicipio();
    }

    public function permitePorCobrar(): bool
    {
        if ($this->esRecogeHoy()) {
            return true;
        }
        if ($this->esEnvioMunicipio()) {
            return (bool) ($this->paqueteria?->permite_por_cobrar);
        }

        return false;
    }

    /** La transferencia espera en sucursal; recoge hoy se entrega en el momento. */
    public function abreResguardo(): bool
    {
        return $this->esTransferenciaDiferida();
    }

    public function entregaDirectaSinResguardo(): bool
    {
        return $this->esRecogeHoy();
    }
}
