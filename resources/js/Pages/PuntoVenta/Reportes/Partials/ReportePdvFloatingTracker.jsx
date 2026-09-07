import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { CheckCircle2, Download, FileText, X, XCircle } from 'lucide-react';
import GeliaLogo from '@/Components/GeliaLogo';
import {
    clearPdvReporteTracking,
    dismissPdvReporteTracking,
    fetchEstadoPdvReporte,
    getStoredPdvReporteJobId,
    PDV_REPORTE_DISMISSED_EVENT,
    PDV_REPORTE_STARTED_EVENT,
    urlDescargaPdvReporte,
} from '@/utils/pdvReporteTracker';

function formatearTiempoTranscurrido(inicioMs) {
    const seg = Math.max(0, Math.floor((Date.now() - inicioMs) / 1000));
    const m = Math.floor(seg / 60);
    const s = seg % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

export default function ReportePdvFloatingTracker({ canView = false }) {
    const [jobId, setJobId] = useState(null);
    const [progreso, setProgreso] = useState(null);
    const [minimized, setMinimized] = useState(false);
    const [elapsed, setElapsed] = useState('0:00');
    const inicioRef = useRef(Date.now());
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
        setProgreso({ progress: 0, status: 'processing', etapa_label: 'Preparando exportación' });
        setMinimized(false);
        inicioRef.current = Date.now();
    }, []);

    useEffect(() => {
        if (!canView) return undefined;
        const stored = getStoredPdvReporteJobId();
        if (stored) conectarJob(stored);

        const onStarted = (e) => conectarJob(e.detail?.jobId);
        const onDismissed = () => {
            detenerPolling();
            setJobId(null);
            setProgreso(null);
        };

        window.addEventListener(PDV_REPORTE_STARTED_EVENT, onStarted);
        window.addEventListener(PDV_REPORTE_DISMISSED_EVENT, onDismissed);
        return () => {
            window.removeEventListener(PDV_REPORTE_STARTED_EVENT, onStarted);
            window.removeEventListener(PDV_REPORTE_DISMISSED_EVENT, onDismissed);
        };
    }, [canView, conectarJob, detenerPolling]);

    useEffect(() => {
        if (!canView || !jobId) return undefined;

        const poll = async () => {
            try {
                const estado = await fetchEstadoPdvReporte(jobId);
                setProgreso(estado);
                if (estado.status === 'completed' || estado.status === 'failed') {
                    detenerPolling();
                    if (estado.status === 'completed') clearPdvReporteTracking();
                }
            } catch (e) {
                console.error('Error polling exportación PDV', e);
            }
        };

        poll();
        intervalRef.current = setInterval(poll, 2000);
        return () => detenerPolling();
    }, [canView, jobId, detenerPolling]);

    useEffect(() => {
        if (!jobId || progreso?.status === 'completed' || progreso?.status === 'failed') return undefined;
        const tick = setInterval(() => setElapsed(formatearTiempoTranscurrido(inicioRef.current)), 1000);
        return () => clearInterval(tick);
    }, [jobId, progreso?.status]);

    if (!canView || !jobId) return null;

    const completado = progreso?.status === 'completed';
    const fallido = progreso?.status === 'failed';
    const exportacion = progreso?.exportacion;

    const contenido = minimized ? (
        <button
            type="button"
            onClick={() => setMinimized(false)}
            className="fixed bottom-4 right-4 z-[80] flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg theme-element border theme-border"
        >
            <GeliaLogo className="w-5 h-5" />
            <span className="text-sm font-medium">{completado ? 'Exportación lista' : 'Generando exportación…'}</span>
        </button>
    ) : (
        <div className="fixed bottom-4 right-4 z-[80] w-[min(100vw-2rem,22rem)] rounded-xl shadow-xl theme-element border theme-border overflow-hidden">
            <div className="flex items-center justify-between px-4 py-3 border-b theme-border">
                <div className="flex items-center gap-2">
                    <GeliaLogo className="w-5 h-5" />
                    <span className="text-sm font-semibold theme-text-main">Exportación PDV</span>
                </div>
                <div className="flex items-center gap-1">
                    <button type="button" className="p-1.5 theme-text-muted" onClick={() => setMinimized(true)} aria-label="Minimizar">
                        <span className="text-lg leading-none">−</span>
                    </button>
                    <button type="button" className="p-1.5 theme-text-muted" onClick={dismissPdvReporteTracking} aria-label="Cerrar">
                        <X className="w-4 h-4" />
                    </button>
                </div>
            </div>
            <div className="p-4 space-y-3">
                {completado ? (
                    <>
                        <div className="flex items-center gap-2 text-emerald-500">
                            <CheckCircle2 className="w-5 h-5" />
                            <span className="text-sm font-medium">Listo para descargar</span>
                        </div>
                        {exportacion?.descarga_url && (
                            <a
                                href={exportacion.descarga_url || urlDescargaPdvReporte(jobId)}
                                className="inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-primario)]"
                            >
                                <Download className="w-4 h-4" />
                                Descargar archivo
                            </a>
                        )}
                    </>
                ) : fallido ? (
                    <div className="flex items-center gap-2 text-red-500">
                        <XCircle className="w-5 h-5" />
                        <span className="text-sm">{exportacion?.error || 'La exportación falló.'}</span>
                    </div>
                ) : (
                    <>
                        <div className="flex items-center gap-2 theme-text-main">
                            <FileText className="w-5 h-5 animate-pulse" />
                            <span className="text-sm">{progreso?.etapa_label || 'Procesando…'}</span>
                        </div>
                        <div className="h-1.5 rounded-full bg-[color-mix(in_srgb,var(--theme-border)_80%,transparent)] overflow-hidden">
                            <div
                                className="h-full bg-[var(--color-primario)] transition-all"
                                style={{ width: `${progreso?.progress || 15}%` }}
                            />
                        </div>
                        <p className="text-xs theme-text-muted m-0">Tiempo: {elapsed}</p>
                    </>
                )}
            </div>
        </div>
    );

    return createPortal(contenido, document.body);
}
