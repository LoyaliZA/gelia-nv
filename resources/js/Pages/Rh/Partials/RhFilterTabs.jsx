import React from 'react';
import { GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK } from '../../../utils/geliaTheme';

/**
 * Pestañas de estado RH (segment tabs compartidos con Facturas/Operativas).
 */
export default function RhFilterTabs({
    tabs = [],
    tabActiva,
    onTabChange,
    formatLabel,
    ariaLabel = 'Filtrar por estado',
}) {
    const resolveId = (tab) => (typeof tab === 'object' ? tab.id : tab);

    const resolveLabel = (tab) => {
        if (typeof tab === 'object' && tab.label) return tab.label;
        const id = resolveId(tab);
        if (formatLabel) return formatLabel(id);
        if (id === 'TODAS') return 'Todas';
        return id.charAt(0) + id.slice(1).toLowerCase();
    };

    return (
        <div className={GELIA_SEGMENT_TABS_SCROLL}>
            <div
                className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 shadow-sm`}
                role="tablist"
                aria-label={ariaLabel}
            >
                {tabs.map((tab) => {
                    const id = resolveId(tab);
                    const isActive = tabActiva === id;
                    return (
                        <button
                            key={id}
                            type="button"
                            role="tab"
                            aria-selected={isActive}
                            onClick={() => onTabChange?.(id)}
                            className="gelia-segment-btn whitespace-nowrap"
                            data-active={isActive}
                        >
                            {resolveLabel(tab)}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
