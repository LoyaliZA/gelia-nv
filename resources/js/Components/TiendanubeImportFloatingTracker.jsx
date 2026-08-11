import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { FileSpreadsheet, GripVertical, X } from 'lucide-react';
import GeliaLogo from './GeliaLogo';
import ModalReportesImagenes from '../Pages/Tiendanube/Partials/ModalReportesImagenes';
import {
    clearTiendanubeImageImportTracking,
    dismissTiendanubeImageImportTracking,
    ESTADOS_ACTIVOS,
    ESTADOS_TERMINALES,
    getStoredTiendanubeImageImportId,
    getStoredTiendanubeImportWidgetPos,
    setStoredTiendanubeImportWidgetPos,
    TN_IMAGE_IMPORT_DISMISSED_EVENT,
    TN_IMAGE_IMPORT_STARTED_EVENT,
} from '../utils/tiendanubeImageImportTracker';

function emitToast(mensaje, tipo = 'error') {
    window.dispatchEvent(new CustomEvent('gelia-toast', { detail: { mensaje, tipo } }));
}

const DEFAULT_POS = { x: null, y: null };
const EDGE_PX = 40;
const DRAG_THRESHOLD = 6;
const TAB_W = 44;

export default function TiendanubeImportFloatingTracker({ canView = false }) {
    const [importId, setImportId] = useState(null);
    const [progreso, setProgreso] = useState(null);
    const [expanded, setExpanded] = useState(false);
    const [dock, setDock] = useState(null); // null | 'left' | 'right' — solo al soltar
    const [pos, setPos] = useState(DEFAULT_POS);
    const [dragging, setDragging] = useState(false);
    const [showReportes, setShowReportes] = useState(false);
    const dragOffset = useRef({ x: 0, y: 0 });
    const dragOrigin = useRef({ x: 0, y: 0 });
    const didDragRef = useRef(false);
    const suppressClickRef = useRef(false);
    const intervalRef = useRef(null);
    const toastNotificadoRef = useRef(null);
    const posRef = useRef(pos);

    useEffect(() => {
        posRef.current = pos;
    }, [pos]);

    const detenerPolling = useCallback(() => {
        if (intervalRef.current) {
            clearInterval(intervalRef.current);
            intervalRef.current = null;
        }
    }, []);

    const onStarted = useCallback((e) => {
        const id = e.detail?.importId;
        if (!id) return;
        setImportId(id);
        setExpanded(false);
    }, []);

    const cerrarWidget = useCallback(() => {
        detenerPolling();
        setImportId(null);
        setProgreso(null);
        clearTiendanubeImageImportTracking();
    }, [detenerPolling]);

    const persistLayout = useCallback((nextPos, nextDock) => {
        if (nextPos?.x == null || nextPos?.y == null) return;
        setStoredTiendanubeImportWidgetPos({
            x: nextPos.x,
            y: nextPos.y,
            dock: nextDock || null,
        });
    }, []);

    useEffect(() => {
        const stored = getStoredTiendanubeImageImportId();
        if (stored) setImportId(stored);
        const saved = getStoredTiendanubeImportWidgetPos();
        if (saved) {
            setPos({ x: saved.x, y: saved.y });
            if (saved.dock === 'left' || saved.dock === 'right') {
                setDock(saved.dock);
            }
        }
    }, []);

    useEffect(() => {
        const onDismissed = () => {
            detenerPolling();
            setImportId(null);
            setProgreso(null);
        };
        window.addEventListener(TN_IMAGE_IMPORT_STARTED_EVENT, onStarted);
        window.addEventListener(TN_IMAGE_IMPORT_DISMISSED_EVENT, onDismissed);
        return () => {
            window.removeEventListener(TN_IMAGE_IMPORT_STARTED_EVENT, onStarted);
            window.removeEventListener(TN_IMAGE_IMPORT_DISMISSED_EVENT, onDismissed);
        };
    }, [onStarted, detenerPolling]);

    useEffect(() => {
        if (!canView || !importId) {
            detenerPolling();
            return undefined;
        }

        let cancelled = false;
        const poll = async () => {
            try {
                const res = await fetch(route('tiendanube.imagenes.importar.progreso', importId), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (res.status === 404) {
                    cerrarWidget();
                    return;
                }
                if (!res.ok) return;
                const data = await res.json();
                if (cancelled) return;
                setProgreso(data);

                if (ESTADOS_TERMINALES.includes(data.estado)) {
                    detenerPolling();
                    if (toastNotificadoRef.current !== data.id) {
                        toastNotificadoRef.current = data.id;
                        if (data.estado === 'completado') {
                            emitToast(
                                `Carga Tiendanube #${data.id}: ${data.exitosos}/${data.total_archivos} OK.`,
                                'success'
                            );
                        } else {
                            emitToast(data.mensaje_error || `Carga Tiendanube #${data.id} falló.`, 'error');
                        }
                    }
                    clearTiendanubeImageImportTracking();
                    setTimeout(() => {
                        if (!cancelled) {
                            setImportId(null);
                            setProgreso(null);
                        }
                    }, 5000);
                }
            } catch {
                // ignore
            }
        };

        poll();
        intervalRef.current = setInterval(poll, 2000);
        return () => {
            cancelled = true;
            detenerPolling();
        };
    }, [canView, importId, detenerPolling, cerrarWidget]);

    const onPointerDown = (e) => {
        if (e.button !== 0) return;
        e.preventDefault();
        e.stopPropagation();
        const root = e.currentTarget.closest('[data-tn-import-widget]');
        const rect = root?.getBoundingClientRect();
        if (!rect) return;
        didDragRef.current = false;
        suppressClickRef.current = false;
        dragOrigin.current = { x: e.clientX, y: e.clientY };
        dragOffset.current = { x: e.clientX - rect.left, y: e.clientY - rect.top };
        setDragging(true);
        e.currentTarget.setPointerCapture?.(e.pointerId);
    };

    useEffect(() => {
        if (!dragging) return undefined;

        const onMove = (e) => {
            const dx = e.clientX - dragOrigin.current.x;
            const dy = e.clientY - dragOrigin.current.y;
            if (Math.hypot(dx, dy) > DRAG_THRESHOLD) {
                didDragRef.current = true;
            }
            const next = {
                x: Math.max(0, Math.min(window.innerWidth - TAB_W, e.clientX - dragOffset.current.x)),
                y: Math.max(8, Math.min(window.innerHeight - 64, e.clientY - dragOffset.current.y)),
            };
            posRef.current = next;
            setPos(next);
            // No setDock aquí: remount mid-drag abría el panel / perdía el pointer.
        };

        const onUp = () => {
            const current = posRef.current;
            const dragged = didDragRef.current;
            setDragging(false);

            if (current.x == null || current.y == null) return;

            if (dragged) {
                suppressClickRef.current = true;
                let nextDock = null;
                let nextPos = { ...current };
                if (current.x <= EDGE_PX) {
                    nextDock = 'left';
                    nextPos = { x: 0, y: current.y };
                    setExpanded(false);
                } else if (current.x >= window.innerWidth - EDGE_PX - TAB_W) {
                    nextDock = 'right';
                    nextPos = { x: window.innerWidth - TAB_W, y: current.y };
                    setExpanded(false);
                }
                posRef.current = nextPos;
                setDock(nextDock);
                setPos(nextPos);
                persistLayout(nextPos, nextDock);
            }
        };

        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
        return () => {
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp);
        };
    }, [dragging, persistLayout]);

    const openExpanded = (e) => {
        e?.preventDefault?.();
        e?.stopPropagation?.();
        if (didDragRef.current || suppressClickRef.current) {
            didDragRef.current = false;
            // suppressClickRef sigue true hasta el próximo pointerdown (evita click fantasma).
            return;
        }
        setExpanded(true);
    };

    if (!canView || !importId) return null;

    const pct = progreso?.porcentaje ?? 0;
    const activo = progreso && ESTADOS_ACTIVOS.includes(progreso.estado);
    // Mientras se arrastra, siempre pastilla flotante (evita remount a pestaña).
    const showDockedTab = !expanded && !dragging && (dock === 'left' || dock === 'right');

    const stylePos =
        pos.x != null && pos.y != null
            ? (() => {
                  if (showDockedTab) {
                      return {
                          left: dock === 'left' ? 0 : undefined,
                          right: dock === 'right' ? 0 : undefined,
                          top: pos.y,
                          bottom: 'auto',
                      };
                  }
                  let left = pos.x;
                  if (expanded && dock === 'left') left = 8;
                  if (expanded && dock === 'right') {
                      left = typeof window !== 'undefined' ? Math.max(8, window.innerWidth - 18 * 16 - 8) : pos.x;
                  }
                  return { left, top: pos.y, right: 'auto', bottom: 'auto' };
              })()
            : { right: 16, bottom: 16 };

    const modal = (
        <ModalReportesImagenes
            open={showReportes}
            onClose={() => setShowReportes(false)}
            importId={importId}
            alertasDimension={progreso?.alertas_dimension ?? 0}
            fallidos={progreso?.fallidos ?? 0}
        />
    );

    if (showDockedTab) {
        return (
            <div data-tn-import-widget className="fixed z-[99990]" style={stylePos}>
                <button
                    type="button"
                    onPointerDown={onPointerDown}
                    onClick={openExpanded}
                    className={`flex flex-col items-center gap-1.5 py-3 px-1.5 shadow-xl border theme-border theme-surface select-none cursor-grab active:cursor-grabbing ${
                        dock === 'left' ? 'rounded-r-xl border-l-0' : 'rounded-l-xl border-r-0'
                    }`}
                    style={{ width: TAB_W }}
                    title="Clic para detalle · Arrastrá para mover"
                >
                    <GeliaLogo variant="sparkle" className="w-6 h-6 pointer-events-none" />
                    <span className="text-[9px] font-black tabular-nums theme-text-main leading-none pointer-events-none">
                        {pct}%
                    </span>
                    <span className="w-1.5 h-10 rounded-full theme-element overflow-hidden relative pointer-events-none" aria-hidden>
                        <span
                            className="absolute bottom-0 left-0 right-0 transition-all duration-500 rounded-full"
                            style={{
                                height: `${Math.min(100, Math.max(4, pct))}%`,
                                backgroundColor: 'var(--color-primario)',
                            }}
                        />
                    </span>
                </button>
                {modal}
            </div>
        );
    }

    if (!expanded) {
        return (
            <div data-tn-import-widget className="fixed z-[99990]" style={stylePos}>
                <div
                    className={`flex items-center gap-1 theme-surface border theme-border rounded-full pl-1.5 pr-2.5 py-1.5 shadow-xl select-none ${
                        dragging ? 'opacity-90 scale-[1.02]' : ''
                    }`}
                >
                    <span
                        onPointerDown={onPointerDown}
                        className="p-1 rounded-full theme-text-muted cursor-grab active:cursor-grabbing touch-none"
                        title="Arrastrar · Al borde se convierte en pestaña"
                    >
                        <GripVertical className="w-3.5 h-3.5 pointer-events-none" />
                    </span>
                    <button
                        type="button"
                        onClick={openExpanded}
                        className="flex items-center gap-1.5 min-w-0"
                        title="Ver detalle"
                    >
                        <GeliaLogo variant="sparkle" className="w-6 h-6 shrink-0 pointer-events-none" />
                        <span className="text-[10px] font-black tabular-nums theme-text-main">{pct}%</span>
                        <span className="w-10 h-1 rounded-full theme-element overflow-hidden">
                            <span
                                className="block h-full rounded-full transition-all duration-500"
                                style={{ width: `${pct}%`, backgroundColor: 'var(--color-primario)' }}
                            />
                        </span>
                    </button>
                </div>
                {modal}
            </div>
        );
    }

    return (
        <div data-tn-import-widget className="fixed z-[99990] w-[min(100vw-2rem,18rem)]" style={stylePos}>
            <div className="theme-surface border theme-border rounded-2xl shadow-xl overflow-hidden">
                <div className="p-3 space-y-2.5">
                    <div className="flex items-start justify-between gap-2">
                        <div
                            className="flex items-center gap-2 min-w-0 cursor-grab active:cursor-grabbing touch-none"
                            onPointerDown={onPointerDown}
                        >
                            <GripVertical className="w-3.5 h-3.5 theme-text-muted shrink-0 pointer-events-none" />
                            <GeliaLogo variant="sparkle" className="w-7 h-7 shrink-0 pointer-events-none" />
                            <div className="min-w-0 pointer-events-none">
                                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                                    #{importId}
                                </p>
                                <p className="text-[10px] font-black uppercase theme-text-main truncate m-0">
                                    Carga imágenes
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-0.5 shrink-0">
                            <button
                                type="button"
                                onClick={() => setExpanded(false)}
                                className="p-1 rounded-lg theme-text-muted hover:theme-text-main text-[10px] font-black"
                                title="Minimizar"
                            >
                                —
                            </button>
                            {!activo && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        dismissTiendanubeImageImportTracking();
                                        setImportId(null);
                                        setProgreso(null);
                                    }}
                                    className="p-1 rounded-lg theme-text-muted hover:theme-text-main"
                                >
                                    <X className="w-3.5 h-3.5" />
                                </button>
                            )}
                        </div>
                    </div>

                    <div>
                        <div className="w-full h-1.5 rounded-full theme-element overflow-hidden mb-1">
                            <div
                                className="h-full transition-all duration-500 rounded-full"
                                style={{ width: `${pct}%`, backgroundColor: 'var(--color-primario)' }}
                            />
                        </div>
                        <p className="text-[10px] theme-text-muted m-0">
                            {progreso?.procesados ?? 0}/{progreso?.total_archivos ?? '—'} · {pct}%
                            {' · '}OK {progreso?.exitosos ?? 0}
                            {(progreso?.fallidos ?? 0) > 0 && <> · Fallidos {progreso.fallidos}</>}
                        </p>
                    </div>

                    {progreso?.mensaje_error && (
                        <p className="text-[10px] font-bold text-red-500 leading-snug m-0">{progreso.mensaje_error}</p>
                    )}

                    <div className="flex flex-wrap gap-1.5">
                        <Link
                            href={route('tiendanube.imagenes.index')}
                            className="inline-flex items-center px-2.5 py-1 rounded-lg text-[9px] font-black uppercase border theme-border theme-text-main"
                        >
                            Imágenes
                        </Link>
                        {((progreso?.fallidos ?? 0) > 0 || (progreso?.alertas_dimension ?? 0) > 0) && (
                            <button
                                type="button"
                                onClick={() => setShowReportes(true)}
                                className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[9px] font-black uppercase border theme-border theme-text-main"
                            >
                                <FileSpreadsheet className="w-3 h-3" /> Reportes
                            </button>
                        )}
                    </div>
                </div>
            </div>
            {modal}
        </div>
    );
}
