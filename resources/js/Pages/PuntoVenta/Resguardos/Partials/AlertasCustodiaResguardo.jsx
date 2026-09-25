import React from 'react';
import { Settings2 } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { metricasAntiguedadClaves } from './resguardosUtils';
import { tonoAlertaAntiguedad } from './resguardosStyles';

const TITULO_SECCION = {
    por_recibir: 'Recepción rezagada',
    en_custodia: 'Alertas de custodia',
};

export default function AlertasCustodiaResguardo({
    bandeja = 'en_custodia',
    catalogos = {},
    metricas = {},
    totalBandeja = 0,
    antiguedadActiva = '',
    onAntiguedad,
    antiguedadConfigurada = false,
    puedeVerVencidos = false,
    puedeVerRezagados = false,
}) {
    const metricasVisibles = metricasAntiguedadClaves(bandeja, puedeVerVencidos, puedeVerRezagados);
    const titulo = TITULO_SECCION[bandeja] || 'Antigüedad operativa';
    const todosActivos = !antiguedadActiva;

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
            ) : (
                <div
                    className={`grid grid-cols-1 gap-3 ${
                        metricasVisibles.length >= 2 ? 'sm:grid-cols-2 lg:grid-cols-3' : metricasVisibles.length === 1 ? 'sm:grid-cols-2' : ''
                    }`}
                    role="group"
                    aria-label="Filtros de antigüedad"
                >
                    <button
                        type="button"
                        onClick={() => onAntiguedad?.('')}
                        aria-pressed={todosActivos}
                        className={`${geliaCardClass()} p-4 text-left transition-all hover:ring-1 hover:ring-[var(--color-primario)]/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] ${
                            todosActivos ? 'ring-2 ring-[var(--color-primario)]/40 bg-[var(--color-primario)]/10' : ''
                        }`}
                    >
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                            Todos
                        </p>
                        <p className="text-2xl font-black m-0 mt-1 tabular-nums" style={{ color: 'var(--color-primario)' }}>
                            {totalBandeja}
                        </p>
                    </button>

                    {metricasVisibles.map((key) => {
                        const { tone, ring, bg } = tonoAlertaAntiguedad(key);
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
