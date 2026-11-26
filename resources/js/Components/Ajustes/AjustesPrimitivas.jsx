import React from 'react';
import { Minus, Plus } from 'lucide-react';

/** Encabezado de bloque: icono en mosaico + título + línea de apoyo (identidad GELIA). */
export function AjustesBloque({ icon: Icon, title, hint, children }) {
    return (
        <section className="gelia-ajustes__bloque">
            <header className="gelia-ajustes__bloque-head">
                <span className="gelia-ajustes__tile" aria-hidden><Icon className="w-4 h-4" /></span>
                <div className="min-w-0">
                    <h3 className="gelia-ajustes__bloque-title">{title}</h3>
                    {hint && <p className="gelia-ajustes__bloque-hint">{hint}</p>}
                </div>
            </header>
            {children}
        </section>
    );
}

/** Fila de ajuste: icono + texto a la izquierda, control a la derecha (apilado en móvil). */
export function AjustesFila({ icon: Icon, title, subtitle, children, inline = false }) {
    return (
        <div className={`gelia-ajustes__fila ${inline ? 'gelia-ajustes__fila--inline' : ''}`}>
            <div className="gelia-ajustes__fila-texto">
                <span className="gelia-ajustes__tile gelia-ajustes__tile--sm" aria-hidden><Icon className="w-4 h-4" /></span>
                <div className="min-w-0">
                    <p className="gelia-ajustes__fila-title">{title}</p>
                    {subtitle && <p className="gelia-ajustes__fila-sub">{subtitle}</p>}
                </div>
            </div>
            <div className="gelia-ajustes__fila-control">{children}</div>
        </div>
    );
}

export function AjustesInterruptor({ activo, onChange, label }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={Boolean(activo)}
            aria-label={label}
            className="gelia-switch shrink-0"
            data-active={Boolean(activo)}
            onClick={() => onChange(!activo)}
        >
            <span className="gelia-switch-thumb" />
        </button>
    );
}

export function AjustesPaso({ valor, etiqueta, onMenos, onMas, deshabilitarMenos, deshabilitarMas, nombre }) {
    return (
        <div className="gelia-ajustes__paso" role="group" aria-label={nombre}>
            <button type="button" className="gelia-ajustes__paso-btn" onClick={onMenos} disabled={deshabilitarMenos} aria-label={`Reducir ${nombre.toLowerCase()}`}>
                <Minus className="w-4 h-4" aria-hidden />
            </button>
            <output className="gelia-ajustes__paso-valor" aria-live="polite">{etiqueta ?? valor}</output>
            <button type="button" className="gelia-ajustes__paso-btn" onClick={onMas} disabled={deshabilitarMas} aria-label={`Aumentar ${nombre.toLowerCase()}`}>
                <Plus className="w-4 h-4" aria-hidden />
            </button>
        </div>
    );
}

/** Segmentos exclusivos: `opciones` = [[valor, etiqueta], …]. */
export function AjustesSegmentos({ valor, opciones, onChange, nombre }) {
    return (
        <div className="gelia-segment gelia-ajustes__segmentos" role="group" aria-label={nombre}>
            {opciones.map(([id, etiqueta, Icono]) => (
                <button
                    key={id}
                    type="button"
                    className="gelia-segment-btn"
                    data-active={valor === id}
                    aria-pressed={valor === id}
                    onClick={() => onChange(id)}
                >
                    {Icono && <Icono className="w-4 h-4" aria-hidden />}
                    {etiqueta}
                </button>
            ))}
        </div>
    );
}
