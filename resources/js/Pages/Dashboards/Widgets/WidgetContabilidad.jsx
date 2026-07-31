import React from 'react';
import { Calculator } from 'lucide-react';
import DashboardAdaptiveWidget from '../../../Components/Dashboard/DashboardAdaptiveWidget';
import { formatoMoneda } from '../../../utils/formatoMoneda';
import { contabilidadRoutes } from '../../Contabilidad/contabilidadRoutes';

export default function WidgetContabilidad({ metricas = {}, variant = 'desktop' }) {
    const ventas = metricas.ventas ?? 0;
    const margen = metricas.margen ?? 0;
    const utilidad = metricas.utilidad ?? (metricas.ganancias ?? 0) + (metricas.perdidas ?? 0);

    return (
        <DashboardAdaptiveWidget
            variant={variant}
            title="Contabilidad_"
            icon={Calculator}
            iconClassName="text-teal-500"
            href={contabilidadRoutes.index()}
            ctaLabel="Abrir contabilidad"
            minimalCount={null}
            minimalCountLabel=""
            badge={(
                <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-teal-600 bg-teal-500/10 px-2 py-1 rounded-md border border-teal-500/20 shrink-0">
                    Mes
                </span>
            )}
            summary={(
                <span className="text-[9px] font-black uppercase px-2 py-1 rounded-lg bg-teal-500/10 text-teal-600 border border-teal-500/20">
                    Margen {Number(margen).toFixed(1)}%
                </span>
            )}
        >
            <div className="space-y-3">
                <div className="theme-element border theme-border p-4 rounded-2xl">
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Ventas del mes</p>
                    <p className="text-xl font-black italic theme-text-main m-0 mt-1 tabular-nums">{formatoMoneda(ventas)}</p>
                </div>
                <div className="theme-element border theme-border p-4 rounded-2xl">
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Utilidad neta</p>
                    <p className={`text-xl font-black italic m-0 mt-1 tabular-nums ${utilidad >= 0 ? 'text-emerald-500' : 'text-red-500'}`}>
                        {formatoMoneda(utilidad)}
                    </p>
                </div>
            </div>
        </DashboardAdaptiveWidget>
    );
}
