import React from 'react';

const TONES = {
    default: 'theme-element border theme-border theme-text-main hover:border-[var(--color-primario)]',
    danger: 'theme-element border border-[color:color-mix(in_srgb,var(--color-peligro)_35%,var(--theme-border))] theme-text-peligro hover:border-[var(--color-peligro)]',
    warn: 'theme-element border border-[color:color-mix(in_srgb,var(--color-aviso)_35%,var(--theme-border))] theme-text-aviso hover:border-[var(--color-aviso)]',
};

/**
 * Cubo de acción: slot fijo 44×44 (sin reflow); en hover se expande en capa absoluta.
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
    role,
}) {
    const toneClass = TONES[tone] || TONES.default;

    if (conLabel) {
        return (
            <button
                type="button"
                role={role}
                onClick={onClick}
                disabled={disabled}
                title={title || label}
                className={`min-h-[44px] w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)] disabled:opacity-50 ${toneClass} ${className}`}
            >
                {Icon && <Icon className="w-4 h-4 shrink-0" aria-hidden="true" />}
                <span className="leading-tight text-center break-words max-w-full">{label}</span>
            </button>
        );
    }

    return (
        <div className={`relative h-11 w-11 shrink-0 ${className}`}>
            <button
                type="button"
                role={role}
                onClick={onClick}
                disabled={disabled}
                aria-label={label}
                title={title || label}
                className={`group/accion absolute right-0 top-0 z-20 h-11 w-11 inline-flex items-center justify-center gap-0 rounded-xl pl-2.5 pr-2.5 outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)] disabled:opacity-50 overflow-hidden whitespace-nowrap transition-[width,padding,gap,max-width] duration-200 ease-out hover:w-auto hover:max-w-none hover:gap-1.5 hover:pr-3 hover:pl-2.5 hover:justify-start focus-visible:w-auto focus-visible:gap-1.5 focus-visible:justify-start ${toneClass}`}
            >
                {Icon && <Icon className="w-4 h-4 shrink-0" aria-hidden="true" />}
                <span
                    className="max-w-0 opacity-0 overflow-hidden whitespace-nowrap text-xs font-semibold transition-[max-width,opacity] duration-200 ease-out group-hover/accion:max-w-[9rem] group-hover/accion:opacity-100 group-focus-visible/accion:max-w-[9rem] group-focus-visible/accion:opacity-100"
                >
                    {label}
                </span>
            </button>
        </div>
    );
}
