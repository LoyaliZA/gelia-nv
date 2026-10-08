import React from 'react';
import { LayoutGrid, List } from 'lucide-react';
import { VISTA_RESGUARDOS_POR_RECIBIR } from './resguardosUtils';

export default function SelectorVistaResguardos({ vista, onCambiar, className = '' }) {
    const opciones = [
        { id: VISTA_RESGUARDOS_POR_RECIBIR.CARD, etiqueta: 'Tarjetas', icon: LayoutGrid },
        { id: VISTA_RESGUARDOS_POR_RECIBIR.LISTA, etiqueta: 'Lista', icon: List },
    ];

    return (
        <div
            className={`inline-flex items-center gap-1 p-1 rounded-xl theme-element border theme-border ${className}`.trim()}
            role="group"
            aria-label="Modo de visualización"
        >
            {opciones.map(({ id, etiqueta, icon: Icon }) => {
                const activa = vista === id;
                return (
                    <button
                        key={id}
                        type="button"
                        onClick={() => onCambiar?.(id)}
                        aria-pressed={activa}
                        className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold min-h-[44px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] ${
                            activa
                                ? 'bg-[var(--color-primario)]/15 text-[var(--color-primario)]'
                                : 'theme-text-muted hover:theme-text-main'
                        }`}
                    >
                        <Icon className="w-4 h-4 shrink-0" aria-hidden />
                        <span className="hidden sm:inline">{etiqueta}</span>
                    </button>
                );
            })}
        </div>
    );
}
