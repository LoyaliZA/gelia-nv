import React from 'react';
import { GELIA_ESTADO_VIVO_TONO } from '../../utils/geliaTheme';

/**
 * Pill de estado compacto para cabeceras de widgets del panel.
 */
export default function DashboardWidgetStatus({ tono = 'neutro', icon: Icon, children }) {
    const claseTono = GELIA_ESTADO_VIVO_TONO[tono] || GELIA_ESTADO_VIVO_TONO.neutro;

    return (
        <span
            className={`gelia-estado-vivo gelia-estado-vivo--compacto inline-flex items-center gap-1.5 text-[10px] font-semibold shrink-0 ${claseTono}`}
        >
            {Icon && <Icon className="w-3 h-3 shrink-0" aria-hidden />}
            {children}
        </span>
    );
}
