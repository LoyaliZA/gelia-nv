import React, { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ChevronDown, Search } from 'lucide-react';
import { THEME_INPUT } from '../../utils/geliaTheme';

/** Altura aproximada de 4 opciones (~2.75rem c/u). */
const LISTA_MAX_H = '11rem';

/**
 * Desplegable Gelia para catálogos fiscales (código + nombre), con búsqueda.
 * Se abre hacia abajo; lista con scroll (~4 visibles).
 */
export default function SelectorCatalogoFiscal({
    opciones = [],
    value = '',
    onChange,
    placeholder = 'Seleccione…',
    required = false,
    disabled = false,
    invalid = false,
    className = '',
}) {
    const [abierto, setAbierto] = useState(false);
    const [busqueda, setBusqueda] = useState('');
    const [coords, setCoords] = useState(null);
    const rootRef = useRef(null);
    const panelRef = useRef(null);
    const searchRef = useRef(null);

    const seleccionada = useMemo(
        () => opciones.find((op) => String(op.codigo) === String(value)) || null,
        [opciones, value]
    );

    const filtradas = useMemo(() => {
        const q = busqueda.trim().toLowerCase();
        if (!q) return opciones;
        return opciones.filter((op) => {
            const codigo = String(op.codigo || '').toLowerCase();
            const nombre = String(op.nombre || '').toLowerCase();
            return codigo.includes(q) || nombre.includes(q);
        });
    }, [opciones, busqueda]);

    const actualizarCoords = () => {
        const el = rootRef.current;
        if (!el) return;
        const r = el.getBoundingClientRect();
        setCoords({
            top: r.bottom + 4,
            left: r.left,
            width: r.width,
        });
    };

    useLayoutEffect(() => {
        if (!abierto) return undefined;
        actualizarCoords();
        const onScrollOrResize = () => actualizarCoords();
        window.addEventListener('resize', onScrollOrResize);
        window.addEventListener('scroll', onScrollOrResize, true);
        return () => {
            window.removeEventListener('resize', onScrollOrResize);
            window.removeEventListener('scroll', onScrollOrResize, true);
        };
    }, [abierto]);

    useEffect(() => {
        if (!abierto) return undefined;
        const onDoc = (e) => {
            const t = e.target;
            if (rootRef.current?.contains(t) || panelRef.current?.contains(t)) return;
            setAbierto(false);
            setBusqueda('');
        };
        const onKey = (e) => {
            if (e.key === 'Escape') {
                setAbierto(false);
                setBusqueda('');
            }
        };
        document.addEventListener('mousedown', onDoc);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDoc);
            document.removeEventListener('keydown', onKey);
        };
    }, [abierto]);

    useEffect(() => {
        if (abierto) {
            requestAnimationFrame(() => searchRef.current?.focus());
        }
    }, [abierto]);

    const etiqueta = seleccionada
        ? `${seleccionada.codigo} — ${seleccionada.nombre}`
        : '';

    const elegir = (op) => {
        onChange(op.codigo);
        setAbierto(false);
        setBusqueda('');
    };

    const panel = abierto && coords
        ? createPortal(
            <div
                ref={panelRef}
                className="fixed z-[80] overflow-hidden rounded-xl border theme-border theme-surface shadow-xl"
                style={{ top: coords.top, left: coords.left, width: coords.width }}
                role="listbox"
            >
                <div className="flex items-center gap-2 border-b theme-border px-3 py-2 theme-element">
                    <Search className="w-3.5 h-3.5 shrink-0 theme-text-muted" />
                    <input
                        ref={searchRef}
                        type="text"
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                        placeholder="Buscar por clave o descripción…"
                        className="w-full bg-transparent border-0 outline-none text-sm font-bold theme-text-main placeholder:theme-text-muted"
                        onClick={(e) => e.stopPropagation()}
                    />
                </div>
                <div
                    className="overflow-y-auto overscroll-contain custom-scrollbar"
                    style={{ maxHeight: LISTA_MAX_H }}
                >
                    {filtradas.length === 0 ? (
                        <p className="px-3 py-3 text-xs font-bold theme-text-muted m-0 italic">
                            Sin coincidencias
                        </p>
                    ) : (
                        filtradas.map((op) => {
                            const activo = String(op.codigo) === String(value);
                            return (
                                <button
                                    key={op.codigo}
                                    type="button"
                                    role="option"
                                    aria-selected={activo}
                                    onClick={() => elegir(op)}
                                    className={`w-full text-left px-3 py-2.5 outline-none transition-colors border-b theme-border last:border-b-0 ${
                                        activo
                                            ? 'bg-[color-mix(in_srgb,var(--color-primario)_14%,transparent)] theme-text-main'
                                            : 'theme-text-main hover:bg-[color-mix(in_srgb,var(--color-primario)_10%,transparent)]'
                                    }`}
                                >
                                    <span
                                        className="block text-[10px] font-black uppercase tracking-widest"
                                        style={{ color: 'var(--color-primario)' }}
                                    >
                                        {op.codigo}
                                    </span>
                                    <span className="block text-xs font-bold leading-snug mt-0.5">
                                        {op.nombre}
                                    </span>
                                </button>
                            );
                        })
                    )}
                </div>
            </div>,
            document.body
        )
        : null;

    return (
        <div className={`relative ${className}`} ref={rootRef}>
            <input
                tabIndex={-1}
                className="sr-only"
                value={value || ''}
                onChange={() => {}}
                required={required}
                aria-hidden
            />

            <button
                type="button"
                disabled={disabled}
                aria-haspopup="listbox"
                aria-expanded={abierto}
                onClick={() => {
                    if (disabled) return;
                    setAbierto((v) => {
                        if (v) setBusqueda('');
                        return !v;
                    });
                }}
                className={`${THEME_INPUT} w-full flex items-center justify-between gap-2 text-left cursor-pointer disabled:opacity-50 ${
                    invalid ? '!border-red-500 focus:!border-red-500 focus:!ring-red-500/30' : ''
                }`}
            >
                <span className={`min-w-0 truncate ${etiqueta ? 'theme-text-main' : 'theme-text-muted'}`}>
                    {etiqueta || placeholder}
                </span>
                <ChevronDown
                    className={`w-4 h-4 shrink-0 theme-text-muted transition-transform ${abierto ? 'rotate-180' : ''}`}
                />
            </button>

            {panel}
        </div>
    );
}
