import React from 'react';
import { Menu } from 'lucide-react';

/** Abre/cierra el menú de navegación global (Sidebar) desde la vista de mensajería en móvil. */
export default function MensajeriaNavMenuButton({ className = '' }) {
    return (
        <button
            type="button"
            onClick={() => window.dispatchEvent(new CustomEvent('gelia-sidebar-toggle-menu'))}
            className={`p-2 rounded-full theme-element theme-text-main hover:border-[var(--color-primario)] border theme-border transition-colors shrink-0 md:hidden ${className}`}
            title="Menú de navegación"
            aria-label="Abrir menú de navegación"
        >
            <Menu className="w-4 h-4" />
        </button>
    );
}
