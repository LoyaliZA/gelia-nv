import React from 'react';
import { Search, ChevronDown } from 'lucide-react';
import { THEME_INPUT, THEME_SELECT } from '../../../utils/geliaTheme';

export { default as RhSectionLabel } from './RhSectionLabel';

/** Etiqueta de filtro/campo en formularios RH (sobre tarjetas) */
export function RhFieldLabel({ htmlFor, children, className = '' }) {
    return (
        <label htmlFor={htmlFor} className={`gelia-rh-field-label ${className}`.trim()}>
            {children}
        </label>
    );
}

const INPUT_CLASS = `${THEME_INPUT} w-full py-3 pr-4 rounded-2xl text-[11px] font-bold normal-case tracking-normal`;
const SELECT_CLASS = `${THEME_SELECT} w-full py-3 pl-4 pr-10 rounded-2xl text-[11px] font-bold`;

/**
 * Campo de búsqueda RH: solo actualiza estado local; disparar búsqueda con Enter o botón externo.
 */
export function RhSearchField({
    id,
    value,
    onChange,
    onSubmit,
    placeholder = 'Buscar...',
    className = '',
}) {
    const handleKeyDown = (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        onSubmit?.();
    };

    return (
        <div className={`theme-field-with-icon min-w-0 flex-1 ${className}`.trim()}>
            <Search className="theme-field-icon" aria-hidden />
            <input
                id={id}
                type="text"
                value={value}
                onChange={onChange}
                onKeyDown={handleKeyDown}
                placeholder={placeholder}
                autoComplete="off"
                enterKeyHint="search"
                className={INPUT_CLASS}
            />
        </div>
    );
}

/** Select con chevron indicador de desplegable */
export function RhSelect({ className = '', children, disabled, ...props }) {
    return (
        <div className={`relative min-w-0 ${disabled ? 'opacity-60' : ''} ${className}`.trim()}>
            <select
                {...props}
                disabled={disabled}
                className={SELECT_CLASS}
            >
                {children}
            </select>
            <ChevronDown
                className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted"
                aria-hidden
            />
        </div>
    );
}
