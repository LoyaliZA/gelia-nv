import React from 'react';
import { GELIA_ESTADO_VIVO_TONO } from '../../utils/geliaTheme';

/** Chip secundario en summary del widget (métricas que no van en el badge principal). */
export default function DashboardWidgetSummaryChip({ tono = 'neutro', children }) {
    const claseTono = GELIA_ESTADO_VIVO_TONO[tono] || GELIA_ESTADO_VIVO_TONO.neutro;

    return (
        <span className={`gelia-estado-vivo gelia-estado-vivo--compacto inline-flex text-[10px] font-semibold ${claseTono}`}>
            {children}
        </span>
    );
}
