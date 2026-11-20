import React, { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import {
    Plus,
    FileSpreadsheet,
    Link2,
    Loader2,
    MoreHorizontal,
    BookOpen,
    ClipboardList,
} from 'lucide-react';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../utils/geliaTheme';

const BTN_COMPACT_PRIMARY = `${THEME_BTN_PRIMARY} theme-btn-primary--compact min-h-[44px] md:!min-h-0 !py-2.5 md:!py-2 !px-3 text-xs hover:!scale-[1.01]`;
const BTN_COMPACT_SECONDARY = `${THEME_BTN_SECONDARY} theme-btn-primary--compact min-h-[44px] min-w-[44px] md:min-h-0 md:min-w-0 !py-2.5 md:!py-2 !px-3 text-xs hover:!scale-100`;

const MENU_Z_INDEX = 60;

function LineaSincronizacion({ cargando, ultimaSync }) {
    const hora = ultimaSync && !cargando
        ? ultimaSync.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
        : null;

    return (
        <p className="m-0 inline-flex items-center gap-1.5 text-xs font-medium theme-text-muted min-w-0" aria-live="polite">
            {cargando ? (
                <>
                    <Loader2 className="w-3 h-3 shrink-0 animate-spin theme-text-primario" aria-hidden />
                    <span>Actualizando…</span>
                </>
            ) : (
                <>
                    <span className="w-1.5 h-1.5 rounded-full shrink-0 bg-[var(--color-exito,#16a34a)]" aria-hidden />
                    <span className="tabular-nums">{hora ? `Actualizado ${hora}` : 'En vivo'}</span>
                </>
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

    useEffect(() => {
        if (!abierto || !pos) return;
        menuRef.current?.querySelector('button')?.focus({ preventScroll: true });
    }, [abierto, Boolean(pos)]);

    const tecladoMenu = (event) => {
        const controles = [...menuRef.current.querySelectorAll('button:not([disabled])')];
        const index = controles.indexOf(document.activeElement);
        if (event.key === 'Escape') {
            event.preventDefault();
            setAbierto(false);
            triggerRef.current?.focus();
        } else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
            event.preventDefault();
            const siguiente = event.key === 'Home' ? 0 : event.key === 'End' ? controles.length - 1
                : (index + (event.key === 'ArrowDown' ? 1 : -1) + controles.length) % controles.length;
            controles[siguiente]?.focus();
        } else if (event.key === 'Tab') setAbierto(false);
    };

    if (items.length === 0) return null;

    const menu = abierto && pos ? (
        <div
            ref={menuRef}
            role="menu"
            onKeyDown={tecladoMenu}
            aria-labelledby={id}
            className="fixed min-w-[15rem] p-1.5 rounded-xl border theme-border theme-surface shadow-lg flex flex-col gap-0.5"
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
                    className="w-full flex items-center gap-2 px-3 py-2 min-h-11 rounded-lg text-left text-xs font-semibold theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none"
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

    const secundarias = [];
    if (puedeExportar) secundarias.push({ key: 'export', label: 'Exportar CSV', icon: FileSpreadsheet, onClick: onExportar });
    if (puedeLink) secundarias.push({ key: 'link', label: 'Link de dirección', icon: Link2, onClick: onLinkDireccion });
    secundarias.push({
        key: 'manual',
        label: 'Manual para registrar pedidos',
        icon: BookOpen,
        onClick: () => router.visit(route('soporte.manuales.show', 'registrar-pedidos-vendedor')),
    });

    return (
        <header className={geliaCardClass('px-4 py-3 md:px-5 overflow-visible relative z-20')}>
            <div className="flex items-center justify-between gap-3">
                <div className="min-w-0 flex-1 flex items-center gap-3">
                    <span className="gelia-pedidos-icono hidden sm:inline-flex" aria-hidden="true"><ClipboardList className="w-6 h-6" /></span>
                    <div className="min-w-0">
                        <h1 className="m-0 text-xl md:text-2xl font-bold leading-tight tracking-tight theme-text-main truncate">
                            Gestión de pedidos
                        </h1>
                        <p className="m-0 mt-0.5 text-sm theme-text-muted">Da seguimiento a tus pedidos, traslados y envíos.</p>
                        <div className="mt-1"><LineaSincronizacion cargando={cargando} ultimaSync={ultimaSync} /></div>
                    </div>
                </div>

                <div className="flex items-center gap-2 shrink-0 overflow-visible">
                    {puedeCrear && (
                        <button
                            type="button"
                            onClick={onNuevo}
                            className={`${BTN_COMPACT_PRIMARY} inline-flex items-center justify-center gap-1.5 outline-none`}
                        >
                            <Plus className="w-4 h-4 shrink-0" aria-hidden />
                            Nuevo pedido
                        </button>
                    )}
                    <MenuAccionesSecundarias items={secundarias} id="pedidos-header-mas-acciones" />
                </div>
            </div>
        </header>
    );
}
