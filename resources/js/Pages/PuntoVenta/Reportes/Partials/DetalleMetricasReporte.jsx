import React, { useState } from 'react';
import { ChevronDown, ChevronRight, HelpCircle } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import {
    bloqueMetricas,
    desgloseSucursal,
    formatearDuracion,
    formatearValorMetrica,
    metricaTieneDetalle,
} from '../reportesPdvUtils';

function FilaDetalle({ metricaId, etiqueta, metrica, definicion }) {
    const [abierto, setAbierto] = useState(false);
    const tieneDetalle = metricaTieneDetalle(metrica);

    return (
        <div className="border-b theme-border last:border-b-0">
            <button
                type="button"
                className="w-full flex items-center gap-3 px-3 py-3 text-left hover:bg-[color-mix(in_srgb,var(--theme-border)_25%,transparent)]"
                onClick={() => tieneDetalle && setAbierto((v) => !v)}
                disabled={!tieneDetalle}
            >
                <span className="shrink-0 theme-text-muted">
                    {tieneDetalle ? (abierto ? <ChevronDown className="w-4 h-4" /> : <ChevronRight className="w-4 h-4" />) : <span className="w-4 inline-block" />}
                </span>
                <span className="text-[11px] font-mono theme-text-muted w-12 shrink-0">{metricaId}</span>
                <span className="text-sm theme-text-main flex-1 min-w-0">{etiqueta}</span>
                <span className="text-sm font-semibold theme-text-main tabular-nums shrink-0">
                    {formatearValorMetrica(metrica)}
                </span>
                {definicion && (
                    <span className="shrink-0 theme-text-muted" title={definicion.definicion}>
                        <HelpCircle className="w-4 h-4" />
                    </span>
                )}
            </button>
            {abierto && tieneDetalle && (
                <div className="px-3 pb-3 pl-12 text-xs theme-text-muted space-y-2">
                    {definicion && (
                        <div className="space-y-1 pb-2 border-b theme-border">
                            <p className="m-0"><strong>Definición:</strong> {definicion.definicion}</p>
                            <p className="m-0"><strong>Alcance:</strong> {definicion.alcance}</p>
                        </div>
                    )}
                    {metrica?.conteo != null && <p className="m-0">Conteo: {metrica.conteo}</p>}
                    {metrica?.numerador != null && (
                        <p className="m-0">Numerador / denominador: {metrica.numerador} / {metrica.denominador}</p>
                    )}
                    {metrica?.percentiles && (
                        <p className="m-0">
                            Percentiles (s): P50 {formatearDuracion(metrica.percentiles.p50)} ·
                            P90 {formatearDuracion(metrica.percentiles.p90)} ·
                            P95 {formatearDuracion(metrica.percentiles.p95)}
                        </p>
                    )}
                    {metrica?.distribucion && (
                        <ul className="m-0 pl-4">
                            {Object.entries(metrica.distribucion).map(([k, v]) => (
                                <li key={k}>{k}: {v}%</li>
                            ))}
                        </ul>
                    )}
                    {metrica?.en_curso && <p className="m-0 text-amber-500">Hay registros en curso al corte del reporte.</p>}
                </div>
            )}
        </div>
    );
}

function TablaDesgloseSucursal({ desglose, etiquetasMetricas, idsMetricas }) {
    const sucursales = Object.keys(desglose || {});
    if (!sucursales.length) return null;

    return (
        <div className="mt-4 overflow-x-auto">
            <h3 className="text-sm font-semibold theme-text-main m-0 mb-2">Desglose por sucursal</h3>
            <table className="w-full text-sm border-collapse min-w-[480px]">
                <thead>
                    <tr className="border-b theme-border">
                        <th className="text-left py-2 pr-3 font-semibold theme-text-muted">Sucursal</th>
                        {idsMetricas.slice(0, 4).map((id) => (
                            <th key={id} className="text-right py-2 px-2 font-semibold theme-text-muted whitespace-nowrap">
                                {etiquetasMetricas[id] || id}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {sucursales.map((sucursalId) => (
                        <tr key={sucursalId} className="border-b theme-border">
                            <td className="py-2 pr-3 theme-text-main">#{sucursalId}</td>
                            {idsMetricas.slice(0, 4).map((id) => (
                                <td key={id} className="text-right py-2 px-2 tabular-nums theme-text-main">
                                    {formatearValorMetrica(desglose[sucursalId]?.[id])}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function DetalleMetricasReporte({
    seccion,
    payload,
    tipoReporte,
    etiquetasMetricas = {},
    definicionesMetricas = {},
}) {
    const metricas = bloqueMetricas(payload, seccion.id, tipoReporte);
    const desglose = desgloseSucursal(payload, seccion.id, tipoReporte);
    const ids = seccion.metricas || [];

    if (!ids.length) return null;

    return (
        <section className="space-y-3">
            <h2 className="text-base font-semibold theme-text-main m-0">Detalle — {seccion.label}</h2>
            <div className={geliaCardClass('overflow-hidden')}>
                {ids.map((id) => (
                    <FilaDetalle
                        key={id}
                        metricaId={id}
                        etiqueta={etiquetasMetricas[id] || id}
                        metrica={metricas[id]}
                        definicion={definicionesMetricas[id]}
                    />
                ))}
            </div>
            <TablaDesgloseSucursal
                desglose={desglose}
                etiquetasMetricas={etiquetasMetricas}
                idsMetricas={ids}
            />
        </section>
    );
}
