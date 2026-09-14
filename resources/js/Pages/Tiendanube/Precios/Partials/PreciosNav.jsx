import React from 'react';
import { router } from '@inertiajs/react';
import { GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK_COMPACT } from '../../../../utils/geliaTheme';

export default function PreciosNav({ activa = 'catalogo' }) {
    const tabs = [
        { id: 'catalogo', label: 'Catálogo', href: () => route('tiendanube.precios.index') },
        { id: 'fuentes', label: 'Costos y listas', href: () => route('tiendanube.precios.fuentes') },
        { id: 'reglas', label: 'Reglas', href: () => route('tiendanube.precios.reglas.index') },
        { id: 'historial', label: 'Historial', href: () => route('tiendanube.precios.historial.index') },
    ];

    return (
        <div className={`${GELIA_SEGMENT_TABS_SCROLL} mb-3`}>
            <nav
                aria-label="Secciones de precios"
                className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK_COMPACT} p-1 shadow-sm`}
                role="tablist"
            >
                {tabs.map((tab) => {
                    const activaTab = tab.id === activa;
                    const deshabilitada = !tab.href;
                    return (
                        <button
                            key={tab.id}
                            type="button"
                            role="tab"
                            disabled={deshabilitada}
                            aria-selected={activaTab}
                            onClick={() => tab.href && router.visit(tab.href())}
                            className={`gelia-segment-btn whitespace-nowrap ${deshabilitada ? 'opacity-50 cursor-not-allowed' : ''}`}
                            data-active={activaTab}
                            title={deshabilitada ? 'Disponible en una fase posterior' : undefined}
                        >
                            {tab.label}
                        </button>
                    );
                })}
            </nav>
        </div>
    );
}
