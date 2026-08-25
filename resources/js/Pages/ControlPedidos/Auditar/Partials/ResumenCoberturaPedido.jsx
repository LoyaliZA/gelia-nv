import React from 'react';
import {
    badgeCoberturaPago,
    formatearMoneda,
} from '../../Partials/pedidosBmaStyles';

/**
 * Resumen financiero canónico (backend). React no decide si puede validar.
 */
export default function ResumenCoberturaPedido({ resumen = null, bloqueos = [] }) {
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
    const badgeCob = resumen.cobertura ? badgeCoberturaPago(resumen.cobertura) : null;

    let estadoTexto = badgeCob?.label || '—';
    if (Array.isArray(bloqueos) && bloqueos.length > 0) {
        estadoTexto = bloqueos[0];
    } else if (resumen.cubierto === true) {
        estadoTexto = Number(diferencia) > 0.009 && Number(tolerancia) > 0
            ? 'Dentro de tolerancia'
            : (badgeCob?.label || 'Cubierto');
    }

    const campos = [
        { label: 'Total a cubrir', value: formatearMoneda(totalACubrir) },
        { label: 'Saldo a favor aplicado', value: formatearMoneda(saf) },
        { label: 'Pagos válidos', value: formatearMoneda(pagosValidos) },
        { label: 'Diferencia', value: formatearMoneda(diferencia) },
    ];

    return (
        <div className="p-4 rounded-xl border theme-border theme-element space-y-3">
            <div className="grid grid-cols-2 gap-3">
                {campos.map((c) => (
                    <div key={c.label}>
                        <p className="text-[9px] font-black uppercase theme-text-muted m-0">{c.label}</p>
                        <p className="text-base font-black theme-text-main m-0 mt-0.5 tabular-nums" style={{ color: 'var(--color-primario)' }}>
                            {c.value}
                        </p>
                    </div>
                ))}
            </div>
            <div className="flex flex-wrap items-center gap-2 pt-1 border-t theme-border">
                {badgeCob && (
                    <span className={badgeCob.className} style={badgeCob.style}>{badgeCob.label}</span>
                )}
                <p className="text-xs font-bold theme-text-main m-0">{estadoTexto}</p>
                {tolerancia != null && Number(tolerancia) > 0 && (
                    <p className="text-[10px] theme-text-muted font-bold m-0 w-full">
                        Tolerancia aplicada: {formatearMoneda(tolerancia)}
                    </p>
                )}
            </div>
        </div>
    );
}
