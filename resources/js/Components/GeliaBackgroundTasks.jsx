import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { FileSpreadsheet, GripVertical, X } from 'lucide-react';
import GeliaLogo from './GeliaLogo';
import ModalReportesImagenes from '../Pages/Tiendanube/Partials/ModalReportesImagenes';
import { TASK_ADAPTERS, presentarLocal } from '../utils/backgroundTaskAdapters';
import {
    dismissBackgroundTask,
    forgetBackgroundTask,
    GELIA_TASK_DISMISSED,
    GELIA_TASK_LOCAL,
    GELIA_TASK_STARTED,
    getStoredBackgroundWidgetPos,
    listBackgroundTasks,
    setStoredBackgroundWidgetPos,
    startBackgroundTask,
} from '../utils/geliaBackgroundTasks';
import {
    clearTiendanubeImageImportTracking,
    getStoredTiendanubeImageImportId,
    TN_IMAGE_IMPORT_STORAGE_KEY,
} from '../utils/tiendanubeImageImportTracker';

function emitToast(mensaje, tipo = 'error') {
    window.dispatchEvent(new CustomEvent('gelia-toast', { detail: { mensaje, tipo } }));
}

const DEFAULT_POS = { x: null, y: null };
const EDGE_PX = 40;
const DRAG_THRESHOLD = 6;
const TAB_W = 44;

function clave(task) {
    return `${task.module}:${task.id}`;
}

export default function GeliaBackgroundTasks({ modulos = {} }) {
    const verTn = Boolean(modulos['tiendanube-imagenes']);
    const verClientes = Boolean(modulos['clientes-importacion']);
    const puedeVer = verTn || verClientes;
    const [tareas, setTareas] = useState([]);
    const [progresos, setProgresos] = useState({});
    const [local, setLocal] = useState(null);
    const [expanded, setExpanded] = useState(false);
    const [dock, setDock] = useState(null);
    const [pos, setPos] = useState(DEFAULT_POS);
    const [dragging, setDragging] = useState(false);
    const [reporteTn, setReporteTn] = useState(null);
    const dragOffset = useRef({ x: 0, y: 0 });
    const dragOrigin = useRef({ x: 0, y: 0 });
    const didDragRef = useRef(false);
    const suppressClickRef = useRef(false);
    const posRef = useRef(pos);
    const toastRef = useRef(new Set());

    useEffect(() => { posRef.current = pos; }, [pos]);

    const visibles = tareas.filter((task) => modulos[task.module]);
    const hayLocal = local && modulos[local.module];

    const upsert = useCallback((task) => {
        if (!task?.module || task.id == null) return;
        setTareas((prev) => {
            const exists = prev.some((item) => clave(item) === clave(task));
            return exists ? prev : [...prev, { module: String(task.module), id: task.id }];
        });
    }, []);

    const quitar = useCallback((task) => {
        setTareas((prev) => prev.filter((item) => clave(item) !== clave(task)));
        setProgresos((prev) => {
            const next = { ...prev };
            delete next[clave(task)];
            return next;
        });
    }, []);

    useEffect(() => {
        if (!puedeVer) return undefined;
        listBackgroundTasks().forEach(upsert);
        const tnId = getStoredTiendanubeImageImportId();
        if (tnId && verTn) {
            startBackgroundTask({ module: 'tiendanube-imagenes', id: tnId });
        }
        const saved = getStoredBackgroundWidgetPos();
        if (saved) {
            setPos({ x: saved.x, y: saved.y });
            if (saved.dock === 'left' || saved.dock === 'right') setDock(saved.dock);
        }
        return undefined;
    }, [puedeVer, verTn, upsert]);

    useEffect(() => {
        if (!verClientes) return undefined;
        const adapter = TASK_ADAPTERS['clientes-importacion'];
        let cancelled = false;
        fetch(adapter.descubrir(), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (cancelled || !data?.ids) return;
                data.ids.forEach((id) => startBackgroundTask({ module: 'clientes-importacion', id }));
            })
            .catch(() => {});
        return () => { cancelled = true; };
    }, [verClientes]);

    useEffect(() => {
        const onStarted = (event) => upsert(event.detail);
        const onDismissed = (event) => {
            if (event.detail) quitar(event.detail);
        };
        const onLocal = (event) => setLocal(event.detail || null);
        window.addEventListener(GELIA_TASK_STARTED, onStarted);
        window.addEventListener(GELIA_TASK_DISMISSED, onDismissed);
        window.addEventListener(GELIA_TASK_LOCAL, onLocal);
        return () => {
            window.removeEventListener(GELIA_TASK_STARTED, onStarted);
            window.removeEventListener(GELIA_TASK_DISMISSED, onDismissed);
            window.removeEventListener(GELIA_TASK_LOCAL, onLocal);
        };
    }, [upsert, quitar]);

    useEffect(() => {
        if (!puedeVer || visibles.length === 0) return undefined;
        let cancelled = false;
        const poll = async () => {
            await Promise.all(visibles.map(async (task) => {
                const adapter = TASK_ADAPTERS[task.module];
                if (!adapter || task.local) return;
                try {
                    const res = await fetch(adapter.url(task.id), {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (res.status === 404) {
                        if (task.module === 'tiendanube-imagenes') clearTiendanubeImageImportTracking();
                        else dismissBackgroundTask(task);
                        return;
                    }
                    if (!res.ok) return;
                    const data = await res.json();
                    if (cancelled) return;
                    const vista = adapter.presentar(data);
                    setProgresos((prev) => ({ ...prev, [clave(task)]: { ...vista, crudo: data } }));
                    if (vista.terminal && !toastRef.current.has(clave(task))) {
                        toastRef.current.add(clave(task));
                        const aviso = adapter.toast(data);
                        if (aviso?.mensaje) emitToast(aviso.mensaje, aviso.tipo);
                        if (task.module === 'tiendanube-imagenes') {
                            localStorage.removeItem(TN_IMAGE_IMPORT_STORAGE_KEY);
                        }
                        forgetBackgroundTask(task);
                        setTimeout(() => {
                            if (!cancelled) quitar(task);
                        }, 5000);
                    }
                } catch {
                    // El siguiente sondeo reintenta.
                }
            }));
        };
        poll();
        const timer = setInterval(poll, 2000);
        return () => {
            cancelled = true;
            clearInterval(timer);
        };
    }, [puedeVer, visibles.map(clave).join('|'), quitar]);

    const persistLayout = useCallback((nextPos, nextDock) => {
        if (nextPos?.x == null || nextPos?.y == null) return;
        setStoredBackgroundWidgetPos({ x: nextPos.x, y: nextPos.y, dock: nextDock || null });
    }, []);

    const onPointerDown = (event) => {
        if (event.button !== 0) return;
        event.preventDefault();
        event.stopPropagation();
        const root = event.currentTarget.closest('[data-gelia-tasks]');
        const rect = root?.getBoundingClientRect();
        if (!rect) return;
        didDragRef.current = false;
        suppressClickRef.current = false;
        dragOrigin.current = { x: event.clientX, y: event.clientY };
        dragOffset.current = { x: event.clientX - rect.left, y: event.clientY - rect.top };
        setDragging(true);
        event.currentTarget.setPointerCapture?.(event.pointerId);
    };

    useEffect(() => {
        if (!dragging) return undefined;
        const onMove = (event) => {
            const dx = event.clientX - dragOrigin.current.x;
            const dy = event.clientY - dragOrigin.current.y;
            if (Math.hypot(dx, dy) > DRAG_THRESHOLD) didDragRef.current = true;
            const next = {
                x: Math.max(0, Math.min(window.innerWidth - TAB_W, event.clientX - dragOffset.current.x)),
                y: Math.max(8, Math.min(window.innerHeight - 64, event.clientY - dragOffset.current.y)),
            };
            posRef.current = next;
            setPos(next);
        };
        const onUp = () => {
            const current = posRef.current;
            const dragged = didDragRef.current;
            setDragging(false);
            if (current.x == null || current.y == null) return;
            if (!dragged) return;
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
        };
        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
        return () => {
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp);
        };
    }, [dragging, persistLayout]);

    const openExpanded = (event) => {
        event?.preventDefault?.();
        event?.stopPropagation?.();
        if (didDragRef.current || suppressClickRef.current) {
            didDragRef.current = false;
            return;
        }
        setExpanded(true);
    };

    const cerrar = (task) => {
        if (task.module === 'tiendanube-imagenes') clearTiendanubeImageImportTracking();
        else dismissBackgroundTask(task);
        quitar(task);
    };

    if (!puedeVer || (visibles.length === 0 && !hayLocal)) return null;

    const vistas = [
        ...(hayLocal ? [{ id: 'local', module: local.module, vista: presentarLocal(local), crudo: null }] : []),
        ...visibles.map((task) => ({
            ...task,
            vista: progresos[clave(task)] || {
                titulo: TASK_ADAPTERS[task.module]?.titulo || 'Carga',
                etiqueta: 'Consultando',
                porcentaje: null,
                indeterminado: true,
                descripcion: 'Recuperando el estado de la operación.',
                activo: true,
                terminal: false,
            },
        })),
    ];
    const principal = vistas[0]?.vista;
    const pct = principal?.porcentaje ?? 0;
    const showDockedTab = !expanded && !dragging && (dock === 'left' || dock === 'right');
    const stylePos = pos.x != null && pos.y != null
        ? (showDockedTab
            ? { left: dock === 'left' ? 0 : undefined, right: dock === 'right' ? 0 : undefined, top: pos.y, bottom: 'auto' }
            : {
                left: expanded && dock === 'right' && typeof window !== 'undefined'
                    ? Math.max(8, window.innerWidth - 20 * 16)
                    : (expanded && dock === 'left' ? 8 : pos.x),
                top: pos.y,
                right: 'auto',
                bottom: 'auto',
            })
        : { right: 16, bottom: 96 };

    const modal = reporteTn ? (
        <ModalReportesImagenes
            open
            onClose={() => setReporteTn(null)}
            importId={reporteTn.id}
            alertasDimension={reporteTn.alertas_dimension ?? 0}
            fallidos={reporteTn.fallidos ?? 0}
        />
    ) : null;

    const barra = (valor, indeterminado) => (
        <span className="block h-full rounded-full overflow-hidden theme-element">
            {indeterminado ? (
                <span className="block h-full w-1/3 rounded-full animate-pulse" style={{ backgroundColor: 'var(--color-primario)' }} />
            ) : (
                <span
                    className="block h-full rounded-full transition-all duration-500"
                    style={{ width: `${Math.min(100, Math.max(0, valor || 0))}%`, backgroundColor: 'var(--color-primario)' }}
                />
            )}
        </span>
    );

    if (showDockedTab) {
        return (
            <div data-gelia-tasks className="fixed z-[99990]" style={stylePos}>
                <button
                    type="button"
                    onPointerDown={onPointerDown}
                    onClick={openExpanded}
                    className={`flex flex-col items-center gap-1.5 py-3 px-1.5 shadow-xl border theme-border theme-surface select-none cursor-grab active:cursor-grabbing ${dock === 'left' ? 'rounded-r-xl border-l-0' : 'rounded-l-xl border-r-0'}`}
                    style={{ width: TAB_W }}
                    title="Clic para detalle · Arrastrar para mover"
                >
                    <GeliaLogo variant="sparkle" className="w-6 h-6 pointer-events-none" />
                    <span className="text-[9px] font-black tabular-nums theme-text-main leading-none pointer-events-none">
                        {principal?.indeterminado ? '…' : `${pct}%`}
                    </span>
                    {vistas.length > 1 && (
                        <span className="text-[9px] font-black theme-text-muted pointer-events-none">{vistas.length}</span>
                    )}
                </button>
                {modal}
            </div>
        );
    }

    if (!expanded) {
        return (
            <div data-gelia-tasks className="fixed z-[99990]" style={stylePos}>
                <div className={`flex items-center gap-1 theme-surface border theme-border rounded-full pl-1.5 pr-2.5 py-1.5 shadow-xl select-none ${dragging ? 'opacity-90 scale-[1.02]' : ''}`}>
                    <span onPointerDown={onPointerDown} className="p-1 rounded-full theme-text-muted cursor-grab active:cursor-grabbing touch-none" title="Arrastrar · Al borde se convierte en pestaña">
                        <GripVertical className="w-3.5 h-3.5 pointer-events-none" />
                    </span>
                    <button type="button" onClick={openExpanded} className="flex items-center gap-1.5 min-w-0" title="Ver detalle">
                        <GeliaLogo variant="sparkle" className="w-6 h-6 shrink-0 pointer-events-none" />
                        <span className="text-[10px] font-black tabular-nums theme-text-main">
                            {principal?.indeterminado ? '…' : `${pct}%`}
                        </span>
                        <span className="w-10 h-1 rounded-full overflow-hidden">{barra(pct, principal?.indeterminado)}</span>
                        {vistas.length > 1 && <span className="text-[10px] font-black theme-text-muted">{vistas.length}</span>}
                    </button>
                </div>
                {modal}
            </div>
        );
    }

    return (
        <div data-gelia-tasks className="fixed z-[99990] w-[min(100vw-2rem,20rem)]" style={stylePos}>
            <div className="theme-surface border theme-border rounded-2xl shadow-xl overflow-hidden max-h-[min(70vh,28rem)] flex flex-col">
                <div className="p-3 flex items-center justify-between gap-2 border-b theme-border">
                    <div className="flex items-center gap-2 min-w-0 cursor-grab active:cursor-grabbing touch-none" onPointerDown={onPointerDown}>
                        <GripVertical className="w-3.5 h-3.5 theme-text-muted shrink-0 pointer-events-none" />
                        <GeliaLogo variant="sparkle" className="w-7 h-7 shrink-0 pointer-events-none" />
                        <p className="text-[10px] font-black uppercase theme-text-main m-0 truncate">Cargas en curso</p>
                    </div>
                    <button type="button" onClick={() => setExpanded(false)} className="p-1 rounded-lg theme-text-muted hover:theme-text-main text-[10px] font-black" title="Minimizar">—</button>
                </div>
                <div className="overflow-y-auto p-3 space-y-3">
                    {vistas.map((task) => {
                        const vista = task.vista;
                        const crudo = vista.crudo || progresos[clave(task)]?.crudo;
                        return (
                            <section key={clave(task)} className="space-y-1.5">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                                            {task.id === 'local' ? 'Transferencia' : `#${task.id}`}
                                        </p>
                                        <p className="text-[10px] font-black uppercase theme-text-main m-0">{vista.titulo}</p>
                                        <p className="text-[10px] theme-text-main m-0">{vista.etiqueta}</p>
                                    </div>
                                    {!vista.activo && task.id !== 'local' && (
                                        <button type="button" onClick={() => cerrar(task)} className="p-1 rounded-lg theme-text-muted hover:theme-text-main" aria-label="Cerrar seguimiento">
                                            <X className="w-3.5 h-3.5" />
                                        </button>
                                    )}
                                </div>
                                <div className="w-full h-1.5 rounded-full overflow-hidden">{barra(vista.porcentaje, vista.indeterminado)}</div>
                                <p className="text-[10px] theme-text-muted m-0">
                                    {vista.indeterminado ? 'Avance por confirmar' : `${vista.porcentaje ?? 0}%`}
                                    {vista.detalle ? ` · ${vista.detalle}` : ''}
                                </p>
                                <p className="text-[10px] theme-text-main m-0">{vista.descripcion}</p>
                                {vista.error && <p className="text-[10px] font-bold theme-text-peligro m-0">{vista.error}</p>}
                                <div className="flex flex-wrap gap-1.5">
                                    {vista.href && (
                                        <Link href={vista.href} className="inline-flex items-center px-2.5 py-1 rounded-lg text-[9px] font-black uppercase border theme-border theme-text-main">
                                            {vista.hrefLabel}
                                        </Link>
                                    )}
                                    {task.module === 'tiendanube-imagenes' && ((crudo?.fallidos ?? 0) > 0 || (crudo?.alertas_dimension ?? 0) > 0) && (
                                        <button
                                            type="button"
                                            onClick={() => setReporteTn({ id: task.id, ...crudo })}
                                            className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[9px] font-black uppercase border theme-border theme-text-main"
                                        >
                                            <FileSpreadsheet className="w-3 h-3" /> Reportes
                                        </button>
                                    )}
                                </div>
                            </section>
                        );
                    })}
                </div>
            </div>
            {modal}
        </div>
    );
}
