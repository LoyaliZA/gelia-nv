<?php

namespace App\Support\ControlPedidos;

final class EstatusSucursalPedidoBma
{
    public const EN_TRANSITO = 'en_transito';

    public const RECEPCION_GERENTE_PARCIAL = 'recepcion_gerente_parcial';

    public const RECIBIDO = 'recibido';

    public const EN_RECEPCION = 'en_recepcion';

    /** @deprecated Usar EN_RECEPCION */
    public const PENDIENTE_CUSTODIA = 'en_recepcion';

    public const CUSTODIA_PARCIAL = 'custodia_parcial';

    public const EN_CUSTODIA = 'en_custodia';

    /** @var array<string, string> */
    public const LABELS = [
        self::EN_TRANSITO => 'En tránsito a sucursal',
        self::RECEPCION_GERENTE_PARCIAL => 'Recepción gerente parcial',
        self::RECIBIDO => 'Recibido en sucursal',
        self::EN_RECEPCION => 'En recepción',
        self::PENDIENTE_CUSTODIA => 'En recepción',
        self::CUSTODIA_PARCIAL => 'Custodia parcial en recepción',
        self::EN_CUSTODIA => 'En custodia en sucursal',
    ];

    public static function etiqueta(?string $codigo): ?string
    {
        if ($codigo === null || $codigo === '') {
            return null;
        }

        return self::LABELS[$codigo] ?? $codigo;
    }
}
