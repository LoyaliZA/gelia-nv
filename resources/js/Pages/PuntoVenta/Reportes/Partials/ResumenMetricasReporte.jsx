import React, { useState } from 'react';
import { HelpCircle } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { bloqueMetricas, formatearValorMetrica } from '../reportesPdvUtils';

function TarjetaMetrica({ metricaId, etiqueta, metrica, definicion, destacada = false }) {
    const [mostrarAyuda, setMostrarAyuda] = useState(false);

    return (
        <div
            className={geliaCardClass([
                'p-4 flex flex-col gap-2 min-w-0',
                destacada ? 'ring-1 ring-[color-mix(in_srgb,var(--color-primario)_35%,transparent)]' : '',
            ].join(' '))}
        >
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="text-[11px] font-semibold uppercase tracking-wide theme-text-muted m-0 truncate">
                        {metricaId}
                    </p>
                    <p className="text-sm font-semibold theme-text-main m-0 mt-0.5 leading-snug">
                        {etiqueta}
                    </p>
                </div>
                {definicion && (
                    <button
                        type="button"
                        className="shrink-0 p-1 rounded-md theme-text-muted hover:theme-text-main"
                        onClick={() => setMostrarAyuda((v) => !v)}
                        aria-label={`Definición de ${etiqueta}`}
                    >
                        <HelpCircle className="w-4 h-4" />
                    </button>
                )}
            </div>
            <p className="text-2xl font-bold theme-text-main m-0 tabular-nums leading-tight">
                {formatearValorMetrica(metrica)}
            </p>
            {metrica?.en_curso && (
                <p className="text-[11px] font-medium text-amber-500 m-0">Incluye registros en curso al corte</p>
            )}
            {mostrarAyuda && definicion && (
                <div className="text-xs theme-text-muted space-y-1 border-t theme-border pt-2">
                    <p className="m-0"><strong>Definición:</strong> {definicion.definicion}</p>
                    <p className="m-0"><strong>Alcance:</strong> {definicion.alcance}</p>
                </div>
            )}
        </div>
    );
}

export default function ResumenMetricasReporte({
    seccion,
    payload,
    tipoReporte,
    etiquetasMetricas = {},
    definicionesMetricas = {},
    metricasDestacadas = [],
}) {
    const metricas = bloqueMetricas(payload, seccion.id, tipoReporte);
    const ids = seccion.metricas || [];
    const destacadasSet = new Set(metricasDestacadas.length ? metricasDestacadas : ids.slice(0, 4));

    if (!ids.length) {
        return null;
    }

    return (
        <section className="space-y-3">
            <h2 className="text-base font-semibold theme-text-main m-0">{seccion.label}</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                {ids.filter((id) => destacadasSet.has(id)).map((id) => (
                    <TarjetaMetrica
                        key={id}
                        metricaId={id}
                        etiqueta={etiquetasMetricas[id] || id}
                        metrica={metricas[id]}
                        definicion={definicionesMetricas[id]}
                        destacada
                    />
                ))}
            </div>
        </section>
    );
}
