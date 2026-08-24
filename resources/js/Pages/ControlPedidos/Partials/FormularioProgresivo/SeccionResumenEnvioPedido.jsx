import React from 'react';
import { formatearMoneda, BTN_PRIMARY, BTN_SECONDARY } from '../pedidosBmaStyles';

export default function SeccionResumenEnvioPedido({
    progreso = null,
    totalMercancia = 0,
    costoEnvio = 0,
    costoSeguro = 0,
    saldoFavor = 0,
    totalCobrar = 0,
    accionLabel = null,
    onAccion = null,
    accionDisabled = false,
    secundarioLabel = null,
    onSecundario = null,
}) {
    return (
        <aside className="space-y-4 p-4 rounded-xl border theme-border theme-element sticky top-0">
            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Resumen</p>
            {progreso?.accion_recomendada && (
                <p className="text-sm font-bold theme-text-main m-0 leading-snug">{progreso.accion_recomendada}</p>
            )}
            {(progreso?.bloqueos || []).map((b) => (
                <p key={b} className="text-xs font-bold m-0" style={{ color: 'var(--color-peligro)' }}>{b}</p>
            ))}
            <div className="space-y-1.5 text-sm">
                <div className="flex justify-between theme-text-muted font-bold"><span>Mercancía</span><span>{formatearMoneda(totalMercancia)}</span></div>
                <div className="flex justify-between theme-text-muted font-bold"><span>Envío</span><span>{formatearMoneda(costoEnvio)}</span></div>
                <div className="flex justify-between theme-text-muted font-bold"><span>Seguro</span><span>{formatearMoneda(costoSeguro)}</span></div>
                <div className="flex justify-between font-bold" style={{ color: 'var(--color-exito)' }}><span>Saldo a favor</span><span>- {formatearMoneda(saldoFavor)}</span></div>
                <div className="flex justify-between font-black pt-2 border-t theme-border" style={{ color: 'var(--color-primario)' }}>
                    <span>A cobrar</span><span>{formatearMoneda(totalCobrar)}</span>
                </div>
            </div>
            {accionLabel && (
                <button
                    type="button"
                    onClick={onAccion}
                    disabled={accionDisabled}
                    className={`${BTN_PRIMARY} w-full min-h-[44px] outline-none disabled:opacity-50`}
                >
                    {accionLabel}
                </button>
            )}
            {secundarioLabel && (
                <button type="button" onClick={onSecundario} className={`${BTN_SECONDARY} w-full min-h-[44px] outline-none`}>
                    {secundarioLabel}
                </button>
            )}
        </aside>
    );
}
