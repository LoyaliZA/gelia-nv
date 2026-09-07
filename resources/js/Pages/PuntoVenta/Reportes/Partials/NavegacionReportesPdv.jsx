import React from 'react';
import { Link } from '@inertiajs/react';
import { BarChart3, Layers, Shield, Users } from 'lucide-react';
import { geliaCardClass, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK } from '../../../../utils/geliaTheme';
import {
    ETIQUETAS_TIPO_REPORTE,
    puedeVerTipoReporte,
    RUTAS_REPORTE_PDV,
    TIPO_REPORTE_CONJUNTO,
    TIPO_REPORTE_RESGUARDOS,
    TIPO_REPORTE_TURNOS_OPERACION,
} from '../reportesPdvUtils';

const TABS = [
    { id: TIPO_REPORTE_RESGUARDOS, icon: Shield, label: ETIQUETAS_TIPO_REPORTE[TIPO_REPORTE_RESGUARDOS] },
    { id: TIPO_REPORTE_TURNOS_OPERACION, icon: Users, label: ETIQUETAS_TIPO_REPORTE[TIPO_REPORTE_TURNOS_OPERACION] },
    { id: TIPO_REPORTE_CONJUNTO, icon: Layers, label: ETIQUETAS_TIPO_REPORTE[TIPO_REPORTE_CONJUNTO] },
];

export default function NavegacionReportesPdv({ tipoActivo, vistasDisponibles = {} }) {
    const visibles = TABS.filter((tab) => puedeVerTipoReporte(vistasDisponibles, tab.id));

    if (visibles.length <= 1) {
        return null;
    }

    return (
        <div className={geliaCardClass('p-2')}>
            <div className={GELIA_SEGMENT_TABS_SCROLL}>
                <div className={GELIA_SEGMENT_TABS_TRACK}>
                    {visibles.map((tab) => {
                        const activo = tab.id === tipoActivo;
                        const href = route(RUTAS_REPORTE_PDV[tab.id]);
                        const Icon = tab.icon;

                        return (
                            <Link
                                key={tab.id}
                                href={href}
                                preserveState
                                className={[
                                    'inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors whitespace-nowrap',
                                    activo
                                        ? 'bg-[color-mix(in_srgb,var(--color-primario)_15%,transparent)] theme-text-main'
                                        : 'theme-text-muted hover:theme-text-main',
                                ].join(' ')}
                                aria-current={activo ? 'page' : undefined}
                            >
                                <Icon className="w-4 h-4 shrink-0" />
                                <span>{tab.label}</span>
                            </Link>
                        );
                    })}
                </div>
            </div>
            <p className="text-[11px] theme-text-muted m-0 mt-2 px-2 flex items-center gap-1.5">
                <BarChart3 className="w-3.5 h-3.5" />
                Los valores provienen del backend; la interfaz no recalcula métricas.
            </p>
        </div>
    );
}
