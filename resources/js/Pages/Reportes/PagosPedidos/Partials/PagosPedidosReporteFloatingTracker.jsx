import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { CheckCircle2, Download, FileText, X, XCircle } from 'lucide-react';
import GeliaLogo from '@/Components/GeliaLogo';
import {
    clearPagosPedidosReporteTracking,
    dismissPagosPedidosReporteTracking,
    etiquetaRegistros,
    fetchEstadoPagosPedidosPdf,
    formatearTiempoTranscurrido,
    getStoredPagosPedidosReporteJobId,
    PAGOS_PEDIDOS_REPORTE_DISMISSED_EVENT,
    PAGOS_PEDIDOS_REPORTE_STARTED_EVENT,
    urlDescargaPagosPedidos,
} from '@/utils/pagosPedidosReporteTracker';

export default function PagosPedidosReporteFloatingTracker({ canView = false }) {
    const [jobId, setJobId] = useState(null);
    const [progreso, setProgreso] = useState(null);
    const [minimized, setMinimized] = useState(false);
    const [elapsed, setElapsed] = useState('0:00');
    const intervalRef = useRef(null);

    const detenerPolling = useCallback(() => {
        if (intervalRef.current) {
            clearInterval(intervalRef.current);
            intervalRef.current = null;
        }
    }, []);

    const conectarJob = useCallback((id) => {
        if (!id) return;
        setJobId(String(id));
        setProgreso({ progress: 0, status: 'processing', etapa_label: 'Preparando datos' });
        setMinimized(false);
    }, []);

    useEffect(() => {
        if (!canView) return undefined;

        const stored = getStoredPagosPedidosReporteJobId();
        if (stored) conectarJob(stored);

        const onStarted = (e) => conectarJob(e.detail?.jobId);
        const onDismissed = () => {
            detenerPolling();
            setJobId(null);
            setProgreso(null);
        };

        window.addEventListener(PAGOS_PEDIDOS_REPORTE_STARTED_EVENT, onStarted);
        window.addEventListener(PAGOS_PEDIDOS_REPORTE_DISMISSED_EVENT, onDismissed);

        return () => {
            window.removeEventListener(PAGOS_PEDIDOS_REPORTE_STARTED_EVENT, onStarted);
            window.removeEventListener(PAGOS_PEDIDOS_REPORTE_DISMISSED_EVENT, onDismissed);
        };
    }, [canView, conectarJob, detenerPolling]);

    useEffect(() => {
        if (!canView || !jobId) return undefined;

        const poll = async () => {
            try {
                const estado = await fetchEstadoPagosPedidosPdf(jobId);
                setProgreso(estado);
                if (estado.status === 'completed' || estado.status === 'failed' || estado.status === 'cancelled') {
                    detenerPolling();
                    if (estado.status === 'completed') {
                        clearPagosPedidosReporteTracking();
                    }
                }
            } catch (e) {
                console.error('Error polling reporte pagos pedidos', e);
            }
        };

        poll();
        intervalRef.current = setInterval(poll, 1500);

        return detenerPolling;
    }, [canView, jobId, detenerPolling]);

    useEffect(() => {
        if (!progreso?.started_at) return undefined;
        const tick = () => setElapsed(formatearTiempoTranscurrido(progreso.started_at));
        tick();
        const iv = setInterval(tick, 1000);
        return () => clearInterval(iv);
    }, [progreso?.started_at]);

    const cerrarWidget = () => {
        detenerPolling();
        dismissPagosPedidosReporteTracking();
        setJobId(null);
        setProgreso(null);
    };

    if (!canView || !jobId || !progreso || typeof document === 'undefined') {
        return null;
    }

    const pct = progreso.progress ?? 0;
    const activo = progreso.status === 'processing' || progreso.status === 'pending';

    if (minimized) {
        return createPortal(
            <button
                type="button"
                onClick={() => setMinimized(false)}
                className="fixed bottom-6 right-6 z-[99989] flex items-center gap-2 theme-surface border theme-border rounded-2xl px-4 py-3 shadow-2xl hover:scale-105 transition-transform"
                title="Ver progreso del reporte"
            >
                <FileText className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                <span className="text-[10px] font-black uppercase theme-text-main">{pct}%</span>
            </button>,
            document.body
        );
    }

    return createPortal(
        <div className="fixed bottom-24 right-6 z-[99989] w-[min(100vw-2rem,22rem)] theme-surface border theme-border rounded-[1.75rem] shadow-2xl overflow-hidden">
            <div className="relative p-4 space-y-3">
                <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-3 min-w-0">
                        <GeliaLogo variant="fluid-fill" progress={pct} className="w-10 h-10 shrink-0" />
                        <div className="min-w-0">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Pagos de pedidos</p>
                            <p className="text-xs font-black uppercase theme-text-main truncate m-0">{progreso.etapa_label || 'Generando reporte'}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-1 shrink-0">
                        <button
                            type="button"
                            onClick={() => setMinimized(true)}
                            className="p-1.5 rounded-lg theme-text-muted hover:theme-text-main text-[10px] font-black uppercase"
                            title="Minimizar"
                        >
                            —
                        </button>
                        {!activo && (
                            <button type="button" onClick={cerrarWidget} className="p-1.5 rounded-lg theme-text-muted hover:theme-text-main">
                                <X className="w-4 h-4" />
                            </button>
                        )}
                    </div>
                </div>

                {activo ? (
                    <>
                        <p className="text-2xl font-black m-0 text-center" style={{ color: 'var(--color-primario)' }}>{pct}%</p>
                        <div className="w-full h-2.5 rounded-full theme-element overflow-hidden">
                            <div
                                className="h-full transition-all duration-500 rounded-full"
                                style={{ width: `${pct}%`, backgroundColor: 'var(--color-primario)' }}
                            />
                        </div>
                        <p className="text-[11px] theme-text-muted m-0 text-center">{etiquetaRegistros(progreso)} · {elapsed}</p>
                    </>
                ) : progreso.status === 'completed' ? (
                    <div className="space-y-3">
                        <div className="flex items-center gap-2 text-green-600">
                            <CheckCircle2 className="w-4 h-4" />
                            <span className="text-xs font-bold">Reporte listo</span>
                        </div>
                        <a
                            href={urlDescargaPagosPedidos(jobId)}
                            className="flex items-center justify-center gap-2 w-full px-4 py-2 rounded-xl text-white text-xs font-bold"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                            onClick={() => setTimeout(cerrarWidget, 1500)}
                        >
                            <Download className="w-4 h-4" />
                            Descargar
                        </a>
                    </div>
                ) : (
                    <div className="space-y-2">
                        <div className="flex items-center gap-2 text-red-500">
                            <XCircle className="w-4 h-4" />
                            <span className="text-xs font-bold">
                                {progreso.status === 'cancelled' ? 'Generación cancelada' : 'Error al generar'}
                            </span>
                        </div>
                        {progreso.error && (
                            <p className="text-[10px] text-red-500 m-0 leading-snug">{progreso.error}</p>
                        )}
                    </div>
                )}
            </div>
        </div>,
        document.body
    );
}
