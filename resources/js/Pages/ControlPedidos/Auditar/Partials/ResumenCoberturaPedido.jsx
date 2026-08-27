import React, { useMemo } from 'react';
import {
    badgeCoberturaPago,
    formatearMoneda,
    etiquetaCostoEnvio,
} from '../../Partials/pedidosBmaStyles';
import {
    costoReexpedicionDeZona,
    separarCostoEnvioDeReexpedicion,
} from '../../Partials/resolverReexpedicionForm';

const COLOR_EXITO = { color: 'var(--color-exito)' };
const COLOR_INFO = { color: 'var(--color-info)' };

/**
 * Resumen financiero canónico (backend). React no decide si puede validar.
 */
export default function ResumenCoberturaPedido({
    resumen = null,
    bloqueos = [],
    pedido = null,
    zonas = [],
}) {
    const guiaCliente = Boolean(pedido?.cliente_proporciona_guia);
    const envioPorCobrar = Boolean(pedido?.envio_por_cobrar);
    const omiteEnvio = guiaCliente || envioPorCobrar;

    const costoReexpedicion = useMemo(
        () => costoReexpedicionDeZona(zonas, pedido?.catalogo_zona_id ?? pedido?.zona?.id),
        [zonas, pedido?.catalogo_zona_id, pedido?.zona?.id],
    );

    const costoEnvioBase = useMemo(() => {
        if (omiteEnvio) return 0;
        const { base } = separarCostoEnvioDeReexpedicion(pedido?.costo_envio, costoReexpedicion);
        return Number(base || 0);
    }, [omiteEnvio, pedido?.costo_envio, costoReexpedicion]);

    if (!resumen) {
        return (
            <div className="p-4 rounded-xl border theme-border theme-element">
                <p className="text-xs theme-text-muted font-bold m-0">Cargando cobertura…</p>
            </div>
        );
    }

    const totalACubrir = resumen.total_a_cubrir ?? resumen.total_final;
    const pagosValidos = resumen.pagos_validos ?? resumen.total_pagado ?? resumen.total_recibido;
    const saf = resumen.saldo_favor_aplicado ?? resumen.saldo_a_favor_aplicado ?? resumen.saldos_aplicados ?? 0;
    const diferencia = resumen.diferencia ?? resumen.pendiente;
    const tolerancia = resumen.tolerancia_aplicada ?? resumen.tolerancia;
    const totalACobrar = resumen.total_a_cobrar ?? pedido?.total_a_cobrar;
    const excedente = Number(resumen.excedente_generado ?? resumen.excedente ?? 0);
    const badgeCob = resumen.cobertura ? badgeCoberturaPago(resumen.cobertura) : null;

    let estadoTexto = badgeCob?.label || '—';
    if (Array.isArray(bloqueos) && bloqueos.length > 0) {
        estadoTexto = bloqueos[0];
    } else if (resumen.cubierto === true) {
        estadoTexto = Number(diferencia) > 0.009 && Number(tolerancia) > 0
            ? 'Dentro de tolerancia'
            : (badgeCob?.label || 'Cubierto');
    }

    const labelEnvio = etiquetaCostoEnvio(pedido?.paqueteria);

    return (
        <div className="p-4 rounded-xl border theme-border theme-element space-y-4">
            <div className="space-y-2 text-sm">
                <div className="flex justify-between theme-text-muted font-bold">
                    <span>Total de mercancía</span>
                    <span className="theme-text-main tabular-nums">{formatearMoneda(pedido?.total_mercancia)}</span>
                </div>
                <div className="flex justify-between theme-text-muted font-bold">
                    <span>{labelEnvio}</span>
                    <span className="theme-text-main tabular-nums">
                        {formatearMoneda(omiteEnvio ? 0 : costoEnvioBase)}
                    </span>
                </div>
                <div className="flex justify-between theme-text-muted font-bold">
                    <span>Reexpedición</span>
                    <span className="theme-text-main tabular-nums">
                        {formatearMoneda(omiteEnvio ? 0 : costoReexpedicion)}
                    </span>
                </div>
                <div className="flex justify-between theme-text-muted font-bold">
                    <span>Costo del seguro</span>
                    <span className="theme-text-main tabular-nums">
                        {pedido?.aplica_seguro ? formatearMoneda(pedido.costo_seguro) : formatearMoneda(0)}
                    </span>
                </div>
                <div className="flex justify-between theme-text-muted font-bold">
                    <span>Total a cubrir</span>
                    <span className="theme-text-main tabular-nums">{formatearMoneda(totalACubrir)}</span>
                </div>
                <div className="flex justify-between font-bold" style={COLOR_EXITO}>
                    <span>Saldo a favor aplicado</span>
                    <span className="tabular-nums">- {formatearMoneda(saf)}</span>
                </div>
                {excedente > 0.01 && (
                    <div className="flex justify-between font-bold" style={COLOR_INFO}>
                        <span>Excedente generado (este pedido)</span>
                        <span className="tabular-nums">{formatearMoneda(excedente)}</span>
                    </div>
                )}
            </div>

            <div className="p-4 rounded-2xl border-2" style={{ borderColor: 'var(--color-primario)' }}>
                <p className="text-[10px] font-black uppercase theme-text-muted m-0">Total a cobrar ahora</p>
                <p className="text-[10px] theme-text-muted font-bold m-0">Después del saldo a favor aplicado</p>
                <p className="text-2xl font-black m-0 tabular-nums" style={{ color: 'var(--color-primario)' }}>
                    {formatearMoneda(totalACobrar)}
                </p>
            </div>

            <div className="space-y-2 pt-3 border-t theme-border text-sm">
                <div className="flex justify-between theme-text-muted font-bold">
                    <span>Pagos válidos</span>
                    <span className="theme-text-main tabular-nums">{formatearMoneda(pagosValidos)}</span>
                </div>
                <div className="flex justify-between theme-text-muted font-bold">
                    <span>Diferencia</span>
                    <span className="theme-text-main tabular-nums">{formatearMoneda(diferencia)}</span>
                </div>
                <div className="flex flex-wrap items-center gap-2 pt-1">
                    {badgeCob && (
                        <span className={badgeCob.className} style={badgeCob.style}>{badgeCob.label}</span>
                    )}
                    <p className="text-xs font-bold theme-text-main m-0">{estadoTexto}</p>
                </div>
                {tolerancia != null && Number(tolerancia) > 0 && (
                    <p className="text-[10px] theme-text-muted font-bold m-0">
                        Tolerancia aplicada: {formatearMoneda(tolerancia)}
                    </p>
                )}
                {Array.isArray(bloqueos) && bloqueos.length > 1 && (
                    <ul className="text-xs font-bold text-amber-600 m-0 pl-4 list-disc space-y-1">
                        {bloqueos.slice(1).map((b) => (
                            <li key={b}>{b}</li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
