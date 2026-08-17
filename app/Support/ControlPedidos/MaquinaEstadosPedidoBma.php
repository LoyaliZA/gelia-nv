<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;

/**
 * Secuencia y bloqueos de fase_ciclo. Códigos internos se conservan;
 * las etiquetas de negocio viven en CatalogoEstatusPedido::LABELS_POR_FASE.
 */
final class MaquinaEstadosPedidoBma
{
    public const HITO_PAGO_EN_REVISION = 'pago_en_revision';

    public const HITO_PENDIENTE_REMISION = 'pendiente_remision';

    public const HITO_PAGO_VALIDADO = 'pago_validado';

    public const PERMISO_REABRIR = 'control_pedidos.reabrir';

    /** @var array<string, string> */
    public const LABELS_HITO = [
        self::HITO_PAGO_EN_REVISION => 'Pago en revisión',
        self::HITO_PENDIENTE_REMISION => 'Pendiente de remisión',
        self::HITO_PAGO_VALIDADO => 'Pago validado',
    ];

    /** Cola abierta que impide empacar (pago / guía / producto). Sin guía aún no es grave. */
    public const CAMPOS_GRAVES_EMPAQUE = [
        'pago_validado',
        'pagos',
        'remision',
        'folio_remision',
        'numero_rastreo',
        'guia_pdf',
        'producto_faltante',
        'producto_danado',
        'inventario',
    ];

    /**
     * Destinos permitidos por fase. Misma fase siempre es válida (hitos).
     *
     * @var array<string, list<string>>
     */
    private const TRANSICIONES = [
        CatalogoEstatusPedido::FASE_BORRADOR => [
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_CANCELADO,
        ],
        CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE => [
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO,
            CatalogoEstatusPedido::FASE_BORRADOR,
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_CANCELADO,
        ],
        CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO => [
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_BORRADOR,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_CANCELADO,
        ],
        CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR => [
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
            CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_CANCELADO,
        ],
        CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA => [
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_BORRADOR,
            CatalogoEstatusPedido::FASE_CANCELADO,
        ],
        CatalogoEstatusPedido::FASE_EN_CEDIS => [
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_CANCELADO,
        ],
        CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS => [
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_CANCELADO,
        ],
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA => [
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
            CatalogoEstatusPedido::FASE_CANCELADO,
        ],
        CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE => [
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_CANCELADO,
        ],
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO => [
            CatalogoEstatusPedido::FASE_ENVIADO,
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
            CatalogoEstatusPedido::FASE_CANCELADO,
        ],
        CatalogoEstatusPedido::FASE_ENVIADO => [
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
        ],
        CatalogoEstatusPedido::FASE_ENTREGADO => [],
        CatalogoEstatusPedido::FASE_CANCELADO => [],
        CatalogoEstatusPedido::FASE_EN_RUTA => [
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            CatalogoEstatusPedido::FASE_ENVIADO,
        ],
    ];

    public static function puedeTransicionar(?string $desde, string $hacia): bool
    {
        if ($desde === null || $desde === $hacia) {
            return true;
        }

        return in_array($hacia, self::TRANSICIONES[$desde] ?? [], true);
    }

    public static function assertTransicion(?string $desde, string $hacia): void
    {
        if (! self::puedeTransicionar($desde, $hacia)) {
            throw new \RuntimeException(
                'Transición de estado no permitida: '.($desde ?? '—').' → '.$hacia.'.'
            );
        }
    }

    public static function hitoAuditoria(PedidoBma $pedido): ?string
    {
        if ($pedido->estatus?->fase_ciclo !== CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR) {
            return null;
        }

        if (! $pedido->tienePagoValidado()) {
            return self::HITO_PAGO_EN_REVISION;
        }

        if (! $pedido->tieneRemision()) {
            return self::HITO_PENDIENTE_REMISION;
        }

        return self::HITO_PAGO_VALIDADO;
    }

    public static function etiquetaHito(?string $hito): ?string
    {
        if ($hito === null || $hito === '') {
            return null;
        }

        return self::LABELS_HITO[$hito] ?? $hito;
    }

    /**
     * Volvió a PENDIENTE_AUXILIAR tras un rechazo o reporte (no el primer envío desde borrador).
     */
    public static function esPendienteReRevision(PedidoBma $pedido): bool
    {
        if ($pedido->estatus?->fase_ciclo !== CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR) {
            return false;
        }

        $historial = $pedido->relationLoaded('historial') ? $pedido->historial : collect();

        $ultimaEntrada = $historial
            ->filter(fn ($h) => $h->estatusNuevo?->fase_ciclo === CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR)
            ->sortByDesc('id')
            ->first();

        $faseAnterior = $ultimaEntrada?->estatusAnterior?->fase_ciclo;

        return $faseAnterior !== null
            && $faseAnterior !== CatalogoEstatusPedido::FASE_BORRADOR;
    }

    public static function faseDestinoEmpaque(PedidoBma $pedido): string
    {
        $pedido->loadMissing(['paqueteria', 'origen']);
        $tieneGuia = ! empty($pedido->numero_rastreo);

        if ($pedido->cliente_proporciona_guia && ! $tieneGuia) {
            return CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE;
        }

        if (! $pedido->ofreceRastreo() || $tieneGuia) {
            return CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO;
        }

        return CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA;
    }

    public static function faseDestinoTrasAsignarGuia(): string
    {
        return CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO;
    }

    /**
     * Cola abierta de pago / guía / producto. Revisión física grave (malo / dañado / sin_existencia)
     * se cierra por la misma cola (producto_* / inventario); sin esos campos es observación aceptada.
     */
    public static function erroresGravesBloqueanEmpaque(PedidoBma $pedido): bool
    {
        $campos = CamposIncorrectosPedidoBma::filtrar(
            is_array($pedido->campos_incorrectos) ? $pedido->campos_incorrectos : []
        );

        return array_intersect($campos, self::CAMPOS_GRAVES_EMPAQUE) !== [];
    }
}
