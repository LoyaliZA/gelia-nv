<?php

namespace App\Support\SaldosAFavor;

use App\Models\ConfiguracionSistema;
use App\Models\SaldosAFavor\SafCredito;

/**
 * Una sola política de uso de un saldo a favor (S-06). Todos los servicios que reservan, aplican o sincronizan la consultan.
 *
 * - general: paga mercancía, envío y seguro, como siempre.
 * - solo_transporte: paga únicamente gastos de envío de pedidos posteriores del mismo cliente, con tope.
 * - pendiente_conciliacion: no se usa en aplicaciones nuevas hasta que Administración confirme su origen.
 *
 * Las reservas y aplicaciones ya hechas no se reevalúan: la política rige solo para reservas nuevas.
 */
final class PoliticaUsoCreditoSaf
{
    public const CLAVE_PROTEGER_HISTORICOS = 'saldos_favor.proteger_envio_historico';

    public const CATEGORIA_ENVIO = 'ajustes_envio';

    /**
     * Uso efectivo. Un crédito histórico de motivo de envío sin clasificación confirmada se trata como pendiente de
     * conciliación solo cuando Administración activó la protección tras revisar el reporte de impacto.
     */
    public static function uso(SafCredito $credito): string
    {
        $uso = $credito->uso_permitido ?: SafCredito::USO_GENERAL;
        if ($uso === SafCredito::USO_GENERAL
            && $credito->clasificado_at === null
            && self::protegerHistoricos()
            && self::motivoEsDeEnvio($credito)) {
            return SafCredito::USO_PENDIENTE_CONCILIACION;
        }

        return $uso;
    }

    public static function motivoEsDeEnvio(SafCredito $credito): bool
    {
        $credito->loadMissing('motivo');

        return $credito->motivo?->categoria === self::CATEGORIA_ENVIO;
    }

    public static function usableParaMercancia(SafCredito $credito): bool
    {
        return self::uso($credito) === SafCredito::USO_GENERAL;
    }

    public static function usableParaTransporte(SafCredito $credito): bool
    {
        return self::uso($credito) === SafCredito::USO_SOLO_TRANSPORTE;
    }

    public static function protegerHistoricos(): bool
    {
        $valor = ConfiguracionSistema::query()->where('clave', self::CLAVE_PROTEGER_HISTORICOS)->value('valor');

        return in_array(strtolower((string) $valor), ['1', 'true', 'si', 'sí'], true);
    }

    /** Clasificación con la que nace un crédito nuevo si quien lo genera no declara una. Un motivo de envío no prueba el origen. */
    public static function usoInicial(?string $categoriaMotivo): string
    {
        return $categoriaMotivo === self::CATEGORIA_ENVIO ? SafCredito::USO_PENDIENTE_CONCILIACION : SafCredito::USO_GENERAL;
    }
}
