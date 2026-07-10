import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { CheckCircle2, Download, FileText, Loader2, X, XCircle } from 'lucide-react';
import {
    clearCobranzaReporteTracking,
    COBRANZA_REPORTE_DISMISSED_EVENT,
    COBRANZA_REPORTE_STARTED_EVENT,
    dismissCobranzaReporteTracking,
    getStoredCobranzaReporteJobId,
} from '@/utils/cobranzaReporteTracker';

export default function CobranzaReporteFloatingTracker({ canView = false }) {
    const [jobId, setJobId] = useState(null);
    const [progreso, setProgreso] = useState(null);
    const [minimized, setMinimized] = useState(false);
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
        setProgreso({ progress: 0, status: 'processing' });
        setMinimized(false);
    }, []);

    const finalizarSeguimiento = useCallback((estado, motivo) => {
        setProgreso(estado);
        if (motivo === 'completado') {
            detenerPolling();
            clearCobranzaReporteTracking();
            return;
        }
        if (motivo === 'error') {
            detenerPolling();
        }
    }, [detenerPolling]);

    useEffect(() => {
        if (!canView) return undefined;

        const stored = getStoredCobranzaReporteJobId();
        if (stored) {
            conectarJob(stored);
        }

        const onStarted = (e) => conectarJob(e.detail?.jobId);
        const onDismissed = () => {
            detenerPolling();
            setJobId(null);
            setProgreso(null);
        };

        window.addEventListener(COBRANZA_REPORTE_STARTED_EVENT, onStarted);
        window.addEventListener(COBRANZA_REPORTE_DISMISSED_EVENT, onDismissed);

        return () => {
            window.removeEventListener(COBRANZA_REPORTE_STARTED_EVENT, onStarted);
            window.removeEventListener(COBRANZA_REPORTE_DISMISSED_EVENT, onDismissed);
        };
    }, [canView, conectarJob, detenerPolling]);

    useEffect(() => {
        if (!canView || !jobId) return undefined;

        const poll = async () => {
            try {
                const resp = await axios.get(route('auto-cobranza.reportes.estado', jobId));
                const estado = resp.data;
                setProgreso(estado);

                if (estado.status === 'completed') {
                    finalizarSeguimiento(estado, 'completado');
                } else if (estado.status === 'error') {
                    finalizarSeguimiento(estado, 'error');
                }
            } catch (e) {
                console.error('Error polling reporte cobranza', e);
            }
        };

        poll();
        intervalRef.current = setInterval(poll, 2000);

        return detenerPolling;
    }, [canView, jobId, finalizarSeguimiento, detenerPolling]);

    const cerrarWidget = () => {
        detenerPolling();
        dismissCobranzaReporteTracking();
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
        <div className="fixed bottom-24 right-6 z-[99989] w-[min(100vw-2rem,20rem)] theme-surface border theme-border rounded-[1.75rem] shadow-2xl overflow-hidden">
            <div className="relative p-4 space-y-3">
                <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-3 min-w-0">
                        <FileText className="w-9 h-9 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <div className="min-w-0">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Reporte Cobranza</p>
                            <p className="text-xs font-black uppercase theme-text-main truncate m-0">Generando archivo</p>
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
                        <div className="flex items-center gap-2">
                            <Loader2 className="w-4 h-4 animate-spin" style={{ color: 'var(--color-primario)' }} />
                            <span className="text-xs font-medium theme-text-muted">Procesando… {pct}%</span>
                        </div>
                        <div className="w-full h-2.5 rounded-full theme-element overflow-hidden">
                            <div
                                className="h-full transition-all duration-500 rounded-full"
                                style={{ width: `${pct}%`, backgroundColor: 'var(--color-primario)' }}
                            />
                        </div>
                    </>
                ) : progreso.status === 'completed' ? (
                    <div className="space-y-3">
                        <div className="flex items-center gap-2 text-green-600">
                            <CheckCircle2 className="w-4 h-4" />
                            <span className="text-xs font-bold">Reporte listo</span>
                        </div>
                        <a
                            href={route('auto-cobranza.reportes.descargar', jobId)}
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
                            <span className="text-xs font-bold">Error al generar</span>
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
