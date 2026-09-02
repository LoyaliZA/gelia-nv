import React from 'react';
import { Settings2 } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { metricasAntiguedadClaves } from './resguardosUtils';

const METRICAS_ANTIGUEDAD = {
    rezagado: { tone: 'text-orange-600', ring: 'ring-orange-500/40', bg: 'bg-orange-500/10' },
    proximo_a_vencer: { tone: 'text-amber-600', ring: 'ring-amber-500/40', bg: 'bg-amber-500/10' },
    vencido: { tone: 'text-red-600', ring: 'ring-red-500/40', bg: 'bg-red-500/10' },
};

const TITULO_SECCION = {
    por_recibir: 'Recepción rezagada',
    en_custodia: 'Alertas de custodia',
};

export default function AlertasCustodiaResguardo({
    bandeja = 'en_custodia',
    catalogos = {},
    metricas = {},
    antiguedadActiva = '',
    onAntiguedad,
    antiguedadConfigurada = false,
    puedeVerVencidos = false,
}) {
    const metricasVisibles = metricasAntiguedadClaves(bandeja, puedeVerVencidos);
    const titulo = TITULO_SECCION[bandeja] || 'Antigüedad operativa';

    return (
        <section className="space-y-3" aria-label={titulo}>
            <div className="flex items-center gap-2">
                <h2 className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                    {titulo}
                </h2>
            </div>

            {!antiguedadConfigurada ? (
                <div className={`${geliaCardClass()} p-4 flex items-start gap-3`}>
                    <Settings2 className="w-5 h-5 shrink-0 mt-0.5 theme-text-muted" aria-hidden />
                    <div className="min-w-0">
                        <p className="text-sm font-bold theme-text-main m-0">Pendiente de configuración</p>
                        <p className="text-xs theme-text-muted m-0 mt-1">
                            Los plazos operativos de custodia aún no están definidos. Las alertas de rezago,
                            vencimiento y antigüedad se activarán cuando exista la configuración.
                        </p>
                    </div>
                </div>
            ) : metricasVisibles.length === 0 ? null : (
                <div className={`grid grid-cols-1 gap-3 ${metricasVisibles.length > 1 ? 'sm:grid-cols-2' : ''}`}>
                    {metricasVisibles.map((key) => {
                        const { tone, ring, bg } = METRICAS_ANTIGUEDAD[key];
                        const activa = antiguedadActiva === key;
                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => onAntiguedad?.(activa ? '' : key)}
                                aria-pressed={activa}
                                className={`${geliaCardClass()} p-4 text-left transition-all hover:ring-1 hover:ring-[var(--color-primario)]/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] ${
                                    activa ? `ring-2 ${ring} ${bg}` : ''
                                }`}
                            >
                                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                                    {catalogos.antiguedades?.[key] || key}
                                </p>
                                <p className={`text-2xl font-black m-0 mt-1 tabular-nums ${tone}`}>
                                    {metricas?.[key] ?? 0}
                                </p>
                            </button>
                        );
                    })}
                </div>
            )}
        </section>
    );
}
