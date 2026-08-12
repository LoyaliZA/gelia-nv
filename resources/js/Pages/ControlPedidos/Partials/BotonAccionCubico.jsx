import React from 'react';

const TONES = {
    default: 'theme-element border theme-border theme-text-main hover:border-[var(--color-primario)]',
    danger: 'theme-element border border-red-500/40 text-red-600 hover:border-red-500 hover:bg-red-500/10',
    warn: 'theme-element border border-orange-500/40 text-orange-600 hover:border-orange-500 hover:bg-orange-500/10',
    purple: 'theme-element border theme-border theme-text-main hover:border-purple-500',
    fuchsia: 'theme-element border theme-border text-fuchsia-600 hover:border-fuchsia-500',
    teal: 'theme-element border theme-border text-teal-600 hover:border-teal-500',
    amber: 'theme-element border theme-border text-amber-600 hover:border-amber-500',
};

/**
 * Cubo de acción: slot fijo 40×40 (sin reflow); en hover se expande en capa absoluta.
 * con `conLabel` (móvil) muestra icono+texto a ancho equitativo.
 */
export default function BotonAccionCubico({
    icon: Icon,
    label,
    onClick,
    tone = 'default',
    className = '',
    conLabel = false,
    title,
    disabled = false,
}) {
    const toneClass = TONES[tone] || TONES.default;

    if (conLabel) {
        return (
            <button
                type="button"
                onClick={onClick}
                disabled={disabled}
                title={title || label}
                className={`min-h-[44px] w-full inline-flex flex-col items-center justify-center gap-0.5 px-1.5 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest outline-none disabled:opacity-50 ${toneClass} ${className}`}
            >
                {Icon && <Icon className="w-4 h-4 shrink-0" />}
                <span className="leading-tight text-center truncate max-w-full">{label}</span>
            </button>
        );
    }

    return (
        <div className={`relative h-10 w-10 shrink-0 ${className}`}>
            <button
                type="button"
                onClick={onClick}
                disabled={disabled}
                aria-label={label}
                title={title || label}
                className={`group/accion absolute right-0 top-0 z-20 h-10 w-10 inline-flex items-center justify-center gap-0 rounded-xl pl-2.5 pr-2.5 outline-none disabled:opacity-50 overflow-hidden whitespace-nowrap transition-[width,padding,gap,max-width] duration-200 ease-out hover:w-auto hover:max-w-none hover:gap-1.5 hover:pr-3 hover:pl-2.5 hover:justify-start ${toneClass}`}
            >
                {Icon && <Icon className="w-4 h-4 shrink-0" />}
                <span
                    className="max-w-0 opacity-0 overflow-hidden whitespace-nowrap text-[9px] font-black uppercase tracking-widest transition-all duration-200 ease-out group-hover/accion:max-w-[9rem] group-hover/accion:opacity-100"
                >
                    {label}
                </span>
            </button>
        </div>
    );
}
