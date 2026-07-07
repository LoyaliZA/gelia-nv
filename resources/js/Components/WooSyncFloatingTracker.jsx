import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { X, StopCircle, Play, ExternalLink, Trash2 } from 'lucide-react';
import GeliaLogo from './GeliaLogo';
import {
    clearWooSyncTracking,
    dismissWooSyncTracking,
    ESTADOS_ACTIVOS,
    ESTADOS_REANUDABLES,
    etiquetaTipoSync,
    getStoredWooSyncLogId,
    WOO_SYNC_DISMISSED_EVENT,
    WOO_SYNC_STARTED_EVENT,
    WOO_SYNC_STORAGE_KEY,
} from '../utils/woocommerceSyncTracker';

function emitToast(mensaje, tipo = 'error') {
    window.dispatchEvent(new CustomEvent('gelia-toast', { detail: { mensaje, tipo } }));
}

function logEsVisible(log) {
    if (!log?.id) return false;
    if (ESTADOS_ACTIVOS.includes(log.estado)) return true;
    if (ESTADOS_REANUDABLES.includes(log.estado) && log.procesados < log.total_productos) return true;
    if (log.estado === 'en_proceso' && log.procesados < log.total_productos) return true;
    return false;
}

function logEsFantasma(log) {
    if (!log) return false;
    return ['pendiente', 'en_proceso', 'interrumpido', 'error'].includes(log.estado);
}

function logPuedeContinuar(log) {
    if (!log || log.procesados >= log.total_productos) return false;
    if (ESTADOS_REANUDABLES.includes(log.estado)) return true;
    if (log.estado === 'en_proceso' && log.updated_at) {
        const haceMs = Date.now() - new Date(log.updated_at).getTime();
        return haceMs > 2 * 60 * 1000;
    }
    return false;
}

export default function WooSyncFloatingTracker({ canView = false, canSync = false }) {
    const { props } = usePage();
    const syncActivoInertia = props.woocommerce_sync_activo;

    const [logId, setLogId] = useState(null);
    const [log, setLog] = useState(null);
    const [minimized, setMinimized] = useState(false);
    const [pollTick, setPollTick] = useState(0);
    const [accionando, setAccionando] = useState(false);
    const intervalRef = useRef(null);
    const toastNotificadoRef = useRef(null);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const detenerPolling = useCallback(() => {
        if (intervalRef.current) {
            clearInterval(intervalRef.current);
            intervalRef.current = null;
        }
    }, []);

    const fetchLog = useCallback(async (id) => {
        const res = await fetch(route('woocommerce.progreso', id), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (res.status === 404) return null;
        if (!res.ok) throw new Error('No se pudo consultar el progreso.');
        return res.json();
    }, []);

    const conectarLog = useCallback((id, dataInicial = null) => {
        if (!id) return;
        setLogId(id);
        if (typeof window !== 'undefined') {
            localStorage.setItem(WOO_SYNC_STORAGE_KEY, String(id));
        }
        if (dataInicial) setLog(dataInicial);
    }, []);

    const finalizarSeguimiento = useCallback((data, motivo) => {
        setLog(data);

        if (motivo === 'error' || motivo === 'interrumpido') {
            detenerPolling();
            if (toastNotificadoRef.current !== data?.id) {
                toastNotificadoRef.current = data?.id;
                emitToast(data?.mensaje_error || 'El proceso de WooCommerce falló.', 'error');
            }
            return;
        }

        detenerPolling();
        clearWooSyncTracking();

        if (motivo === 'completado') {
            emitToast('Sincronización WooCommerce completada.', 'success');
            setTimeout(() => {
                setLogId(null);
                setLog(null);
            }, 4000);
        } else if (motivo === 'cancelado') {
            emitToast('Proceso WooCommerce cancelado.', 'success');
            setLogId(null);
            setLog(null);
        }
    }, [detenerPolling]);

    const descubrirActivo = useCallback(async () => {
        try {
            const res = await fetch(route('woocommerce.sync.activo'), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data.log?.id && logEsVisible(data.log)) {
                conectarLog(data.log.id, data.log);
            }
        } catch {
            // silencioso
        }
    }, [conectarLog]);

    useEffect(() => {
        if (!canView) return;

        const stored = getStoredWooSyncLogId();
        if (stored) {
            conectarLog(stored);
            return;
        }

        if (syncActivoInertia?.id && logEsVisible(syncActivoInertia)) {
            conectarLog(syncActivoInertia.id, syncActivoInertia);
            return;
        }

        descubrirActivo();
    }, [canView, syncActivoInertia, conectarLog, descubrirActivo]);

    useEffect(() => {
        if (!canView) return undefined;

        const onStarted = (e) => conectarLog(e.detail?.logId);
        const onDismissed = () => {
            detenerPolling();
            setLogId(null);
            setLog(null);
        };

        window.addEventListener(WOO_SYNC_STARTED_EVENT, onStarted);
        window.addEventListener(WOO_SYNC_DISMISSED_EVENT, onDismissed);

        return () => {
            window.removeEventListener(WOO_SYNC_STARTED_EVENT, onStarted);
            window.removeEventListener(WOO_SYNC_DISMISSED_EVENT, onDismissed);
        };
    }, [canView, conectarLog, detenerPolling]);

    useEffect(() => {
        if (!canView) return undefined;

        const discovery = setInterval(() => {
            if (!logId) descubrirActivo();
        }, 4000);

        return () => clearInterval(discovery);
    }, [canView, logId, descubrirActivo]);

    useEffect(() => {
        if (!logId || !canView) return undefined;

        const poll = async () => {
            try {
                const data = await fetchLog(logId);
                if (!data) {
                    dismissWooSyncTracking();
                    setLogId(null);
                    setLog(null);
                    detenerPolling();
                    return;
                }
                setLog(data);

                if (data.estado === 'completado' || data.estado === 'cancelado') {
                    finalizarSeguimiento(data, data.estado);
                } else if (data.estado === 'error' || data.estado === 'interrumpido') {
                    detenerPolling();
                    if (toastNotificadoRef.current !== logId) {
                        toastNotificadoRef.current = logId;
                        emitToast(data.mensaje_error || 'El proceso de WooCommerce falló.', 'error');
                    }
                }
            } catch (err) {
                emitToast(err.message, 'error');
            }
        };

        poll();
        intervalRef.current = setInterval(poll, 2000);

        return detenerPolling;
    }, [logId, pollTick, canView, fetchLog, finalizarSeguimiento, detenerPolling]);

    const cancelar = async () => {
        if (!logId || !canSync) return;
        if (!window.confirm('¿Cancelar definitivamente este proceso de WooCommerce?')) return;

        setAccionando(true);
        try {
            const res = await fetch(route('woocommerce.sync.cancelar', logId), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo cancelar.');

            const actualizado = await fetchLog(logId);
            finalizarSeguimiento(actualizado, 'cancelado');
            setLogId(null);
            setLog(null);
            emitToast(data.message, 'success');
        } catch (err) {
            emitToast(err.message, 'error');
        } finally {
            setAccionando(false);
        }
    };

    const descartar = async () => {
        if (!logId || !canSync) return;
        if (!window.confirm(`¿Eliminar el proceso #${logId}? Se borrará de la base de datos.`)) return;

        setAccionando(true);
        try {
            const res = await fetch(route('woocommerce.sync.descartar', logId), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo eliminar.');

            detenerPolling();
            dismissWooSyncTracking();
            setLogId(null);
            setLog(null);
            emitToast(data.message, 'success');
        } catch (err) {
            emitToast(err.message, 'error');
        } finally {
            setAccionando(false);
        }
    };

    const continuar = async () => {
        if (!logId || !canSync) return;

        setAccionando(true);
        try {
            const res = await fetch(route('woocommerce.sync.continuar', logId), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo continuar.');

            toastNotificadoRef.current = null;
            conectarLog(data.log_id);
            setPollTick((t) => t + 1);
            emitToast(data.message, 'success');
        } catch (err) {
            emitToast(err.message, 'error');
        } finally {
            setAccionando(false);
        }
    };

    const cerrarWidget = () => {
        detenerPolling();
        setLogId(null);
        setLog(null);
        clearWooSyncTracking();
    };

    if (!canView || !logId) return null;

    const pct = log
        ? Math.round((log.procesados / Math.max(log.total_productos, 1)) * 100)
        : 0;
    const activo = log && ESTADOS_ACTIVOS.includes(log.estado);
    const puedeContinuar = logPuedeContinuar(log);
    const esFantasma = logEsFantasma(log);
    const titulo = log ? etiquetaTipoSync(log.tipo) : 'WooCommerce';

    if (minimized) {
        return (
            <button
                type="button"
                onClick={() => setMinimized(false)}
                className="fixed bottom-6 right-6 z-[99990] flex items-center gap-2 theme-surface border theme-border rounded-2xl px-4 py-3 shadow-2xl hover:scale-105 transition-transform"
                title="Ver progreso WooCommerce"
            >
                <GeliaLogo variant="sparkle" className="w-8 h-8" />
                <span className="text-[10px] font-black uppercase theme-text-main">{pct}%</span>
            </button>
        );
    }

    return (
        <div className="fixed bottom-6 right-6 z-[99990] w-[min(100vw-2rem,22rem)] theme-surface border theme-border rounded-[1.75rem] shadow-2xl overflow-hidden">
            <div
                className="absolute inset-0 opacity-15 pointer-events-none"
                style={{ background: 'radial-gradient(circle at top right, var(--color-primario), transparent 70%)' }}
            />

            <div className="relative p-4 space-y-3">
                <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-3 min-w-0">
                        <GeliaLogo variant="sparkle" className="w-10 h-10 shrink-0 drop-shadow-lg" />
                        <div className="min-w-0">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                Proceso #{logId}
                            </p>
                            <p className="text-xs font-black uppercase theme-text-main truncate m-0">{titulo}</p>
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

                <div>
                    <div className="w-full h-2.5 rounded-full theme-element overflow-hidden mb-1.5">
                        <div
                            className="h-full transition-all duration-500 rounded-full"
                            style={{ width: `${pct}%`, backgroundColor: 'var(--color-primario)' }}
                        />
                    </div>
                    <div className="flex justify-between text-[10px] font-black uppercase theme-text-muted">
                        <span>{log?.procesados ?? 0} / {log?.total_productos ?? '—'}</span>
                        <span>{pct}% · {log?.estado ?? 'conectando...'}</span>
                    </div>
                </div>

                {log?.mensaje_error && (
                    <p className="text-[10px] font-bold text-red-500 leading-snug m-0">{log.mensaje_error}</p>
                )}

                <div className="flex flex-wrap gap-2">
                    <Link
                        href={route('woocommerce.index')}
                        className="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase border theme-border theme-text-main hover:border-[var(--color-primario)]"
                    >
                        <ExternalLink className="w-3 h-3" /> Ir al módulo
                    </Link>

                    {activo && canSync && (
                        <button
                            type="button"
                            onClick={cancelar}
                            disabled={accionando}
                            className="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase border border-red-500/40 text-red-500 hover:bg-red-500/10 disabled:opacity-50"
                        >
                            <StopCircle className="w-3 h-3" /> Cancelar
                        </button>
                    )}

                    {puedeContinuar && canSync && (
                        <button
                            type="button"
                            onClick={continuar}
                            disabled={accionando}
                            className="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase text-white disabled:opacity-50"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            <Play className="w-3 h-3" /> Continuar
                        </button>
                    )}

                    {esFantasma && canSync && (
                        <button
                            type="button"
                            onClick={descartar}
                            disabled={accionando}
                            className="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase border border-red-500/40 text-red-500 hover:bg-red-500/10 disabled:opacity-50"
                        >
                            <Trash2 className="w-3 h-3" /> Eliminar
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
