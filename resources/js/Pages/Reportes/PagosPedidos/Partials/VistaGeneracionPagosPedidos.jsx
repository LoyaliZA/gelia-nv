import React, { useEffect, useState } from 'react';
import GeliaLogo from '@/Components/GeliaLogo';
import {
    etiquetaRegistros,
    formatearTiempoTranscurrido,
} from '@/utils/pagosPedidosReporteTracker';

export default function VistaGeneracionPagosPedidos({
    progreso,
    onSegundoPlano,
    onCancelar,
    cancelando = false,
}) {
    const [elapsed, setElapsed] = useState('0:00');

    useEffect(() => {
        if (!progreso?.started_at) return undefined;
        const tick = () => setElapsed(formatearTiempoTranscurrido(progreso.started_at));
        tick();
        const iv = setInterval(tick, 1000);
        return () => clearInterval(iv);
    }, [progreso?.started_at]);

    const pct = progreso?.progress ?? 0;
    const activo = progreso?.status === 'processing' || progreso?.status === 'pending';
    const puedeCancelar = activo && progreso?.cancelable && !cancelando;

    return (
        <div className="p-8 md:p-10 flex flex-col items-center gap-6 flex-1 justify-center min-h-[22rem]">
            <GeliaLogo variant="fluid-fill" progress={pct} className="w-24 h-24 drop-shadow-xl" />

            <div className="text-center space-y-1 w-full max-w-md">
                <p className="text-3xl font-black m-0" style={{ color: 'var(--color-primario)' }}>
                    {pct}%
                </p>
                <p className="text-sm font-bold theme-text-main m-0">
                    {progreso?.etapa_label || 'Generando reporte…'}
                </p>
            </div>

            <div className="w-full max-w-md space-y-2">
                <div className="w-full h-3 rounded-full theme-element overflow-hidden">
                    <div
                        className="h-full transition-all duration-500 rounded-full"
                        style={{ width: `${pct}%`, backgroundColor: 'var(--color-primario)' }}
                        role="progressbar"
                        aria-valuenow={pct}
                        aria-valuemin={0}
                        aria-valuemax={100}
                    />
                </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full max-w-md text-center">
                <div className="rounded-xl border theme-border px-4 py-3">
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Registros procesados</p>
                    <p className="text-sm font-bold theme-text-main mt-1 m-0">{etiquetaRegistros(progreso)}</p>
                </div>
                <div className="rounded-xl border theme-border px-4 py-3">
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Tiempo transcurrido</p>
                    <p className="text-sm font-bold theme-text-main mt-1 m-0">{elapsed}</p>
                </div>
            </div>

            <div className="flex flex-wrap gap-3 justify-center w-full max-w-md pt-2">
                {activo && (
                    <button
                        type="button"
                        onClick={onSegundoPlano}
                        className="px-5 py-2.5 rounded-xl border theme-border text-sm font-semibold theme-text-main hover:border-[var(--color-primario)] transition-colors"
                    >
                        Continuar en segundo plano
                    </button>
                )}
                {puedeCancelar && (
                    <button
                        type="button"
                        onClick={onCancelar}
                        disabled={cancelando}
                        className="px-5 py-2.5 rounded-xl border border-red-400/40 text-sm font-semibold text-red-500 hover:bg-red-500/10 transition-colors disabled:opacity-50"
                    >
                        {cancelando ? 'Cancelando…' : 'Cancelar generación'}
                    </button>
                )}
            </div>

            {progreso?.status === 'completed' && (
                <p className="text-xs font-semibold text-green-600 m-0">Reporte listo para descargar.</p>
            )}
            {(progreso?.status === 'failed' || progreso?.status === 'cancelled') && progreso?.error && (
                <p className="text-xs text-red-500 m-0 text-center max-w-md">{progreso.error}</p>
            )}
        </div>
    );
}
