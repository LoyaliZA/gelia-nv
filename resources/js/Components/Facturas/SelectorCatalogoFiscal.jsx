import React, { useEffect, useId, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ChevronDown, Search, Check } from 'lucide-react';
import { THEME_INPUT } from '../../utils/geliaTheme';

const normalizar = (valor) => String(valor ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es-MX');

/** Keep the popup inside its dialog so the focus trap also contains its search field. */
export default function SelectorCatalogoFiscal({ opciones = [], value = '', onChange, placeholder = 'Seleccione…', required = false, disabled = false, invalid = false, className = '', id, name, label = 'Catálogo fiscal', describedBy }) {
    const uid = useId();
    const controlId = id || uid;
    const [abierto, setAbierto] = useState(false);
    const [busqueda, setBusqueda] = useState('');
    const [coords, setCoords] = useState(null);
    const [indice, setIndice] = useState(0);
    const rootRef = useRef(null);
    const panelRef = useRef(null);
    const searchRef = useRef(null);
    const triggerRef = useRef(null);
    const seleccionada = opciones.find((op) => String(op.codigo) === String(value));
    const filtradas = useMemo(() => {
        const palabras = normalizar(busqueda).trim().split(/\s+/).filter(Boolean);
        return opciones.filter((op) => palabras.every((q) => normalizar(`${op.codigo} ${op.nombre}`).includes(q)));
    }, [opciones, busqueda]);
    const cerrar = (devolverFoco = false) => {
        setAbierto(false);
        setBusqueda('');
        if (devolverFoco) triggerRef.current?.focus();
    };
    const elegir = (op) => { onChange(op.codigo); cerrar(true); };

    useLayoutEffect(() => {
        if (!abierto) return undefined;
        const posicionar = () => {
            const rect = rootRef.current?.getBoundingClientRect();
            if (!rect) return;
            const debajo = window.innerHeight - rect.bottom - 12;
            const encima = rect.top - 12;
            const haciaArriba = debajo < 240 && encima > debajo;
            const espacio = haciaArriba ? encima : debajo;
            setCoords({ left: Math.max(8, Math.min(rect.left, window.innerWidth - rect.width - 8)), width: Math.min(rect.width, window.innerWidth - 16),
                ...(haciaArriba ? { bottom: window.innerHeight - rect.top + 4 } : { top: rect.bottom + 4 }), maxHeight: Math.max(96, Math.min(280, espacio)) });
        };
        posicionar();
        window.addEventListener('resize', posicionar);
        window.addEventListener('scroll', posicionar, true);
        return () => { window.removeEventListener('resize', posicionar); window.removeEventListener('scroll', posicionar, true); };
    }, [abierto]);

    useEffect(() => {
        if (!abierto || !coords) return undefined;
        searchRef.current?.focus({ preventScroll: true });
    }, [abierto, Boolean(coords)]);

    useEffect(() => {
        if (!abierto) return undefined;
        const fuera = (event) => {
            if (!rootRef.current?.contains(event.target) && !panelRef.current?.contains(event.target)) cerrar();
        };
        // The dialog listens on document capture. Escape must dismiss this popup first.
        const escape = (event) => {
            if (event.key === 'Escape') { event.preventDefault(); event.stopImmediatePropagation(); cerrar(true); }
        };
        document.addEventListener('mousedown', fuera);
        window.addEventListener('keydown', escape, true);
        return () => { document.removeEventListener('mousedown', fuera); window.removeEventListener('keydown', escape, true); };
    }, [abierto]);

    useEffect(() => { setIndice(0); }, [busqueda]);
    useEffect(() => { panelRef.current?.querySelector('[data-highlighted="true"]')?.scrollIntoView?.({ block: 'nearest' }); }, [indice]);
    useEffect(() => { if (disabled) cerrar(); }, [disabled]);

    const teclado = (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setIndice((actual) => Math.max(0, Math.min(filtradas.length - 1, actual + (event.key === 'ArrowDown' ? 1 : -1))));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (filtradas[indice]) elegir(filtradas[indice]);
        } else if (event.key === 'Tab') cerrar();
    };

    return (
        <div className={`relative ${className}`} ref={rootRef}>
            {name && <input type="hidden" name={name} value={value || ''} />}
            <button ref={triggerRef} id={controlId} type="button" disabled={disabled} aria-label={label} aria-required={required} aria-invalid={invalid} aria-describedby={describedBy} aria-haspopup="listbox" aria-controls={abierto ? `${uid}-lista` : undefined} aria-expanded={abierto}
                onClick={() => { setAbierto((v) => !v); setBusqueda(''); setIndice(0); }}
                onKeyDown={(event) => { if (event.key === 'ArrowDown') { event.preventDefault(); setAbierto(true); } }}
                className={`${THEME_INPUT} w-full flex items-center justify-between gap-2 text-left cursor-pointer disabled:opacity-50 ${invalid ? '!border-[var(--color-peligro)]' : ''}`}>
                <span className={`min-w-0 break-words ${seleccionada ? 'theme-text-main' : 'theme-text-muted'}`}>{seleccionada ? `${seleccionada.codigo} — ${seleccionada.nombre}` : value || placeholder}</span>
                <ChevronDown className="w-4 h-4 shrink-0 theme-text-muted" aria-hidden="true" />
            </button>
            {abierto && coords && createPortal(
                <div ref={panelRef} className="gelia-fiscal-popup fixed flex flex-col overflow-hidden rounded-xl border theme-border shadow-xl" style={{ ...coords, background: 'var(--theme-surface-solid)', zIndex: 'calc(var(--gelia-z-toast) + 2)' }}>
                    <div className="flex items-center gap-2 border-b theme-border p-3">
                        <Search className="w-4 h-4 shrink-0 theme-text-muted" aria-hidden="true" />
                        <input ref={searchRef} type="search" role="combobox" aria-label={`Buscar ${label.toLowerCase()}`} aria-expanded="true" aria-controls={`${uid}-lista`} aria-autocomplete="list" aria-activedescendant={filtradas[indice] ? `${uid}-op-${indice}` : undefined}
                            value={busqueda} onChange={(event) => setBusqueda(event.target.value)} onKeyDown={teclado} placeholder="Clave o descripción…" autoComplete="off" className="theme-input w-full !py-2" />
                    </div>
                    <div id={`${uid}-lista`} role="listbox" aria-label={label} className="overflow-y-auto overscroll-contain min-h-0">
                        {filtradas.map((op, i) => <button key={op.codigo} id={`${uid}-op-${i}`} type="button" role="option" tabIndex={-1} aria-selected={String(op.codigo) === String(value)} data-highlighted={i === indice}
                            onMouseDown={(event) => event.preventDefault()} onClick={() => elegir(op)} className="gelia-fiscal-option w-full flex items-start gap-3 px-3 py-3 text-left border-b theme-border last:border-0">
                            <span className="text-sm font-semibold theme-text-primario shrink-0">{op.codigo}</span><span className="text-sm theme-text-main flex-1 break-words">{op.nombre}</span>{String(op.codigo) === String(value) && <Check className="w-4 h-4 shrink-0 theme-text-primario" aria-hidden="true" />}
                        </button>)}
                        {filtradas.length === 0 && <p className="p-4 text-sm theme-text-muted m-0">Sin coincidencias. Pruebe con la clave o parte del nombre.</p>}
                    </div>
                    <p className="sr-only" role="status">{filtradas.length} opciones disponibles</p>
                </div>, rootRef.current?.closest('[role="dialog"]') || document.body
            )}
        </div>
    );
}
