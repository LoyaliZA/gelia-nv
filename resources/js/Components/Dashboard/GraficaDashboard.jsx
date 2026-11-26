import { useEffect, useId, useRef } from 'react';
import { cargarChartJs } from '../../utils/cargarChartJs';
import { dashboardTonoClass } from './dashboardModulesCatalog';

function colorTexto() {
    if (typeof document === 'undefined') return 'currentColor';
    return getComputedStyle(document.documentElement).getPropertyValue('--theme-text-muted').trim() || 'currentColor';
}

function colorGrid() {
    return 'color-mix(in srgb, var(--theme-text-muted) 22%, transparent)';
}

function conAlfa(color, alfa) {
    const valor = (color || '').trim();
    const rgb = valor.match(/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)/i);
    if (rgb) {
        return `rgba(${rgb[1]}, ${rgb[2]}, ${rgb[3]}, ${alfa})`;
    }
    return valor;
}

function moduloDesdeNodo(nodo) {
    if (!nodo || typeof getComputedStyle === 'undefined') return '';
    const estilo = getComputedStyle(nodo);
    return (
        estilo.getPropertyValue('--dashboard-modulo').trim() ||
        estilo.getPropertyValue('--color-primario').trim()
    );
}

/**
 * Chart.js 4 (vendido) con color de módulo GELIA.
 * datasets: [{ label, data, rol: 'principal'|'comparacion' }]
 */
export default function GraficaDashboard({
    type = 'bar',
    labels = [],
    datasets = [],
    tono = 'primario',
    emptyLabel = 'Sin datos en este periodo.',
    ariaLabel,
    heightClass = 'h-52',
}) {
    const canvasRef = useRef(null);
    const wrapRef = useRef(null);
    const chartRef = useRef(null);
    const fallbackId = useId();
    const hayDatos = datasets.some((serie) => (serie.data || []).some((valor) => Number(valor) !== 0));
    const firmaDatos = JSON.stringify({ type, labels, datos: datasets.map((serie) => ({ label: serie.label, data: serie.data, rol: serie.rol })) });

    useEffect(() => {
        if (!canvasRef.current || !hayDatos) return undefined;

        let cancelado = false;
        const reducirMovimiento = typeof window !== 'undefined'
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        cargarChartJs().then((Chart) => {
            if (cancelado || !canvasRef.current) return;

            const colorModulo = moduloDesdeNodo(wrapRef.current) || 'currentColor';
            const colorMuted = colorTexto();

            if (chartRef.current) {
                chartRef.current.destroy();
            }

            chartRef.current = new Chart(canvasRef.current, {
                type,
                data: {
                    labels,
                    datasets: datasets.map((serie) => {
                        const esComparacion = serie.rol === 'comparacion';
                        return {
                            type: esComparacion ? 'line' : type,
                            label: serie.label,
                            data: serie.data,
                            borderColor: esComparacion ? colorMuted : colorModulo,
                            backgroundColor: esComparacion
                                ? 'transparent'
                                : conAlfa(colorModulo, 0.35),
                            borderWidth: esComparacion ? 2 : 1.5,
                            borderDash: esComparacion ? [6, 4] : undefined,
                            pointRadius: esComparacion ? 0 : 2,
                            tension: 0.25,
                            fill: !esComparacion && type === 'line',
                            order: esComparacion ? 0 : 1,
                        };
                    }),
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: reducirMovimiento ? false : { duration: 280 },
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            display: datasets.length > 1,
                            labels: { color: colorMuted, boxWidth: 10, font: { size: 11 } },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: colorGrid() },
                            ticks: {
                                color: colorMuted,
                                callback: (val) => Number(val).toLocaleString('es-MX'),
                            },
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: colorMuted, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                        },
                    },
                },
            });
        });

        return () => {
            cancelado = true;
            if (chartRef.current) {
                chartRef.current.destroy();
                chartRef.current = null;
            }
        };
    }, [firmaDatos, hayDatos, type]);

    return (
        <div ref={wrapRef} className={`dashboard-chart ${dashboardTonoClass(tono)}`}>
            {hayDatos ? (
                <div className={`relative w-full ${heightClass}`}>
                    <canvas
                        ref={canvasRef}
                        role="img"
                        aria-label={ariaLabel || 'Gráfica'}
                    />
                </div>
            ) : (
                <p className="dashboard-widget-empty m-0">{emptyLabel}</p>
            )}
            {hayDatos && (
                <table className="sr-only" id={fallbackId}>
                    <caption>{ariaLabel || 'Datos de la gráfica'}</caption>
                    <thead>
                        <tr>
                            <th>Periodo</th>
                            {datasets.map((serie) => (
                                <th key={serie.label}>{serie.label}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {labels.map((label, i) => (
                            <tr key={`${label}-${i}`}>
                                <td>{label}</td>
                                {datasets.map((serie) => (
                                    <td key={serie.label}>{serie.data?.[i] ?? 0}</td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}
