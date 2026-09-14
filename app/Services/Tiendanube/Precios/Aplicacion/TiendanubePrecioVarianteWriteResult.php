<?php

namespace App\Services\Tiendanube\Precios\Aplicacion;

final class TiendanubePrecioVarianteWriteResult
{
    /**
     * @param  array<string, mixed>  $payloadEnviado
     * @param  array<string, mixed>|null  $respuestaPut
     * @param  array<string, mixed>|null  $productoCompleto
     */
    public function __construct(
        public readonly array $payloadEnviado,
        public readonly ?array $respuestaPut,
        public readonly ?array $productoCompleto,
        public readonly bool $espejoActualizado,
        public readonly bool $escrituraIncierta,
        public readonly ?string $detalleRefresco = null,
    ) {}
}
