<?php

namespace App\Support\Reportes;

use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\SaldosAFavor\PedidoBmaPago;

/** Clasifica exhibiciones para el total de ingreso bancario (reporte Vouchers validados). */
final class ClasificacionIngresoBancario
{
    public const INGRESO_BANCARIO = 'ingreso_bancario';

    public const PAGO_NO_BANCARIO = 'pago_no_bancario';

    public const APLICACION_SAF = 'aplicacion_saf';

    public const PAGO_PENDIENTE = 'pago_pendiente';

    public const PAGO_RECHAZADO = 'pago_rechazado';

    public const SUSTITUIDO = 'sustituido';

    /** @var array<string, string> */
    private const DEFAULT_CLASIFICACION_FORMA = [
        'transferencia' => self::INGRESO_BANCARIO,
        'deposito' => self::INGRESO_BANCARIO,
        'tarjeta' => self::INGRESO_BANCARIO,
        'efectivo' => self::PAGO_NO_BANCARIO,
        'otro' => self::PAGO_NO_BANCARIO,
    ];

    /** @var array<string, bool> */
    private const DEFAULT_REQUIERE_BANCO = [
        'transferencia' => true,
        'deposito' => true,
        'tarjeta' => false,
        'efectivo' => false,
        'otro' => false,
    ];

    /** @return list<string> */
    public static function categorias(): array
    {
        return [
            self::INGRESO_BANCARIO,
            self::PAGO_NO_BANCARIO,
            self::APLICACION_SAF,
            self::PAGO_PENDIENTE,
            self::PAGO_RECHAZADO,
            self::SUSTITUIDO,
        ];
    }

    public static function clasificacionForma(?string $formaPago): string
    {
        if ($formaPago === null || $formaPago === '') {
            return self::PAGO_NO_BANCARIO;
        }

        $mapa = array_merge(
            self::DEFAULT_CLASIFICACION_FORMA,
            (array) config('reportes_pagos.clasificacion_forma_pago', [])
        );

        return (string) ($mapa[$formaPago] ?? self::PAGO_NO_BANCARIO);
    }

    public static function formaRequiereBanco(?string $formaPago): bool
    {
        if ($formaPago === null || $formaPago === '') {
            return false;
        }

        $mapa = array_merge(
            self::DEFAULT_REQUIERE_BANCO,
            (array) config('reportes_pagos.requiere_banco', [])
        );

        if (array_key_exists($formaPago, $mapa)) {
            return (bool) $mapa[$formaPago];
        }

        return (bool) (PedidoBmaPago::REQUIERE_BANCO[$formaPago] ?? false);
    }

    /** @return list<string> */
    public static function estadosRevisionValidados(): array
    {
        $estados = config('reportes_pagos.estados_revision_validados');

        if (! is_array($estados) || $estados === []) {
            return [
                PedidoBmaPago::REVISION_VERIFICADO,
                PedidoBmaPago::REVISION_CON_OBSERVACIONES,
            ];
        }

        return array_values(array_map('strval', $estados));
    }

    public static function clasificarItem(PedidoBmaCierrePagoItem $item): string
    {
        $estado = (string) ($item->estado_revision_snapshot ?? '');

        if ($estado === PedidoBmaPago::REVISION_RECHAZADO || $item->motivo_rechazo_snapshot) {
            return self::PAGO_RECHAZADO;
        }

        if (! $item->activo_para_cobertura_snapshot) {
            if (self::itemFueSustituido($item)) {
                return self::SUSTITUIDO;
            }

            return $estado === PedidoBmaPago::REVISION_RECHAZADO
                ? self::PAGO_RECHAZADO
                : self::SUSTITUIDO;
        }

        if (in_array($estado, [PedidoBmaPago::REVISION_PENDIENTE, PedidoBmaPago::REVISION_EN_REVISION], true)) {
            return self::PAGO_PENDIENTE;
        }

        if (! in_array($estado, self::estadosRevisionValidados(), true)) {
            return self::PAGO_PENDIENTE;
        }

        return self::clasificacionForma($item->forma_pago_snapshot);
    }

    public static function cuentaIngresoBancario(PedidoBmaCierrePagoItem $item): bool
    {
        return self::clasificarItem($item) === self::INGRESO_BANCARIO;
    }

    public static function montoIngresoBancario(PedidoBmaCierrePagoItem $item): float
    {
        return self::cuentaIngresoBancario($item) ? (float) $item->monto_snapshot : 0.0;
    }

    public static function labelClasificacion(string $clasificacion): string
    {
        return match ($clasificacion) {
            self::INGRESO_BANCARIO => 'Ingreso bancario',
            self::PAGO_NO_BANCARIO => 'Pago no bancario',
            self::APLICACION_SAF => 'Aplicación de saldo a favor',
            self::PAGO_PENDIENTE => 'Pago pendiente',
            self::PAGO_RECHAZADO => 'Pago rechazado',
            self::SUSTITUIDO => 'Sustituido',
            default => $clasificacion,
        };
    }

    /** @return list<array{codigo: string, label: string, clasificacion: string, requiere_banco: bool, cuenta_ingreso_bancario: bool}> */
    public static function catalogoFormasPago(): array
    {
        return array_map(function (string $codigo) {
            $clasificacion = self::clasificacionForma($codigo);

            return [
                'codigo' => $codigo,
                'label' => PedidoBmaPago::labelForma($codigo) ?? $codigo,
                'clasificacion' => $clasificacion,
                'clasificacion_label' => self::labelClasificacion($clasificacion),
                'requiere_banco' => self::formaRequiereBanco($codigo),
                'cuenta_ingreso_bancario' => $clasificacion === self::INGRESO_BANCARIO,
            ];
        }, PedidoBmaPago::FORMAS_PAGO);
    }

    private static function itemFueSustituido(PedidoBmaCierrePagoItem $item): bool
    {
        return PedidoBmaCierrePagoItem::query()
            ->where('pedido_bma_cierre_pago_id', $item->pedido_bma_cierre_pago_id)
            ->where('reemplaza_pago_id', $item->pedido_bma_pago_id)
            ->exists();
    }
}
