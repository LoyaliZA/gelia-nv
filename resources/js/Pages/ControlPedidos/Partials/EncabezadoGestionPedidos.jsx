import React, { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import {
    Plus,
    FileSpreadsheet,
    Link2,
    Loader2,
    Package,
    MoreHorizontal,
} from 'lucide-react';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../utils/geliaTheme';

const DESCRIPCION_ESCRITORIO =
    'Bandejas operativas, borradores y seguimiento de envío para el equipo de ventas.';
const DESCRIPCION_MOVIL = 'Bandejas, borradores y envío.';

const BTN_COMPACT_PRIMARY = `${THEME_BTN_PRIMARY} theme-btn-primary--compact min-h-[44px] md:!min-h-0 !py-2.5 md:!py-2 !px-3 text-xs hover:!scale-[1.01]`;
const BTN_COMPACT_SECONDARY = `${THEME_BTN_SECONDARY} theme-btn-primary--compact min-h-[44px] min-w-[44px] md:min-h-0 md:min-w-0 !py-2.5 md:!py-2 !px-3 text-xs hover:!scale-100`;

const MENU_Z_INDEX = 60;

function LineaSincronizacion({ cargando, ultimaSync }) {
    const hora = ultimaSync && !cargando
        ? ultimaSync.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
        : null;

    return (
        <p
            className="m-0 text-[11px] md:text-xs font-medium theme-text-muted min-w-0"
            aria-live="polite"
        >
            {cargando ? (
                <span className="inline-flex items-center gap-1.5">
                    <Loader2 className="w-3 h-3 shrink-0 animate-spin theme-text-primario" aria-hidden />
                    <span>Sincronizando listado…</span>
                </span>
            ) : (
                <span className="block leading-snug">
                    En vivo · 15 s
                    {hora ? (
                        <>
                            <span className="hidden sm:inline">
                                <span className="mx-1 opacity-50" aria-hidden>·</span>
                                Actualizado {hora}
                            </span>
                            <span className="sm:hidden block tabular-nums mt-0.5">Actualizado {hora}</span>
                        </>
                    ) : null}
                </span>
            )}
        </p>
    );
}

function MenuAccionesSecundarias({ items, id }) {
    const [abierto, setAbierto] = useState(false);
    const triggerRef = useRef(null);
    const menuRef = useRef(null);
    const [pos, setPos] = useState(null);

    const actualizarPosicion = useCallback(() => {
        const el = triggerRef.current;
        if (!el) return;
        const rect = el.getBoundingClientRect();
        setPos({
            top: rect.bottom + 4,
            right: window.innerWidth - rect.right,
        });
    }, []);

    useLayoutEffect(() => {
        if (!abierto) {
            setPos(null);
            return undefined;
        }
        actualizarPosicion();
        window.addEventListener('resize', actualizarPosicion);
        window.addEventListener('scroll', actualizarPosicion, true);
        return () => {
            window.removeEventListener('resize', actualizarPosicion);
            window.removeEventListener('scroll', actualizarPosicion, true);
        };
    }, [abierto, actualizarPosicion]);

    useEffect(() => {
        if (!abierto) return undefined;
        const onDoc = (e) => {
            const t = e.target;
            if (triggerRef.current?.contains(t) || menuRef.current?.contains(t)) return;
            setAbierto(false);
        };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, [abierto]);

    if (items.length === 0) return null;

    const menu = abierto && pos ? (
        <div
            ref={menuRef}
            role="menu"
            aria-labelledby={id}
            className="fixed min-w-[11.5rem] p-1.5 rounded-xl border theme-border theme-surface shadow-lg flex flex-col gap-0.5"
            style={{ top: pos.top, right: pos.right, zIndex: MENU_Z_INDEX }}
        >
            {items.map((item) => (
                <button
                    key={item.key}
                    type="button"
                    role="menuitem"
                    onClick={() => {
                        setAbierto(false);
                        item.onClick();
                    }}
                    className="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-left text-xs font-semibold theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none"
                >
                    <item.icon className="w-4 h-4 shrink-0 theme-text-muted" aria-hidden />
                    {item.label}
                </button>
            ))}
        </div>
    ) : null;

    return (
        <>
            <button
                ref={triggerRef}
                type="button"
                id={id}
                aria-expanded={abierto}
                aria-haspopup="menu"
                aria-label="Más acciones"
                onClick={() => setAbierto((v) => !v)}
                className={`${BTN_COMPACT_SECONDARY} inline-flex items-center justify-center !px-2.5 shrink-0`}
            >
                <MoreHorizontal className="w-4 h-4" aria-hidden />
            </button>
            {typeof document !== 'undefined' && menu ? createPortal(menu, document.body) : null}
        </>
    );
}

export default function EncabezadoGestionPedidos({
    can,
    cargando = false,
    ultimaSync = null,
    onNuevo,
    onExportar,
    onLinkDireccion,
}) {
    const puedeExportar = can('control_pedidos.exportar');
    const puedeLink = can('clientes.direcciones.generar_enlace');
    const puedeCrear = can('control_pedidos.crear');

    const secundariasEscritorio = [];
    if (puedeExportar) {
        secundariasEscritorio.push(
            <button
                key="export"
                type="button"
                onClick={onExportar}
                className={`${BTN_COMPACT_SECONDARY} inline-flex items-center gap-1.5 outline-none`}
            >
                <FileSpreadsheet className="w-3.5 h-3.5" aria-hidden />
                Exportar CSV
            </button>,
        );
    }
    if (puedeLink) {
        secundariasEscritorio.push(
            <button
                key="link"
                type="button"
                onClick={onLinkDireccion}
                className={`${BTN_COMPACT_SECONDARY} inline-flex items-center gap-1.5 outline-none`}
            >
                <Link2 className="w-3.5 h-3.5" aria-hidden />
                Link de dirección
            </button>,
        );
    }

    const secundariasMovil = [];
    if (puedeExportar) {
        secundariasMovil.push({
            key: 'export',
            label: 'Exportar CSV',
            icon: FileSpreadsheet,
            onClick: onExportar,
        });
    }
    if (puedeLink) {
        secundariasMovil.push({
            key: 'link',
            label: 'Link de dirección',
            icon: Link2,
            onClick: onLinkDireccion,
        });
    }

    return (
        <header className={geliaCardClass('p-5 md:p-6 overflow-visible relative z-20')}>
            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between md:gap-3">
                <div className="min-w-0 flex-1 space-y-1">
                    <p className="m-0 flex items-center gap-1.5 text-xs font-semibold theme-text-muted">
                        <Package className="w-3.5 h-3.5 shrink-0 opacity-70" style={{ color: 'var(--color-primario)' }} aria-hidden />
                        Control de pedidos
                    </p>
                    <h1 className="m-0 text-[1.375rem] md:text-[1.75rem] font-bold leading-tight tracking-tight theme-text-main">
                        Gestión de pedidos
                    </h1>
                    <p className="hidden md:block m-0 text-xs md:text-sm font-medium theme-text-muted leading-snug max-w-xl">
                        {DESCRIPCION_ESCRITORIO}
                    </p>
                    <p className="md:hidden m-0 text-xs font-medium theme-text-muted leading-snug line-clamp-1">
                        {DESCRIPCION_MOVIL}
                    </p>
                </div>

                <div className="flex flex-col gap-2 shrink-0 w-full md:w-auto md:items-end md:max-w-md 2xl:max-w-sm overflow-visible">
                    <div className="hidden md:flex flex-wrap items-center justify-end gap-2">
                        {secundariasEscritorio}
                        {puedeCrear && (
                            <button
                                type="button"
                                onClick={onNuevo}
                                className={`${BTN_COMPACT_PRIMARY} inline-flex items-center gap-1.5 outline-none`}
                            >
                                <Plus className="w-3.5 h-3.5" aria-hidden />
                                Nuevo pedido
                            </button>
                        )}
                    </div>

                    <div className="flex md:hidden items-center gap-2 overflow-visible">
                        {puedeCrear && (
                            <button
                                type="button"
                                onClick={onNuevo}
                                className={`${BTN_COMPACT_PRIMARY} flex-1 inline-flex items-center justify-center gap-1.5 outline-none min-w-0`}
                            >
                                <Plus className="w-4 h-4 shrink-0" aria-hidden />
                                Nuevo pedido
                            </button>
                        )}
                        <MenuAccionesSecundarias
                            items={secundariasMovil}
                            id="pedidos-header-mas-acciones"
                        />
                    </div>

                    <LineaSincronizacion cargando={cargando} ultimaSync={ultimaSync} />
                </div>
            </div>
        </header>
    );
}
