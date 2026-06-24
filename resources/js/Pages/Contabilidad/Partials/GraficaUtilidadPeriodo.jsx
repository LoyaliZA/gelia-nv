import { useEffect, useRef } from 'react';
import { cargarChartJs } from '../../../utils/cargarChartJs';

function colorTexto() {
    if (typeof document === 'undefined') return '#94a3b8';
    return getComputedStyle(document.documentElement).getPropertyValue('--theme-text-muted').trim() || '#94a3b8';
}

function colorGrid() {
    return 'color-mix(in srgb, var(--theme-text-muted) 25%, transparent)';
}

export default function GraficaUtilidadPeriodo({ datos = {}, className = '' }) {
    const canvasRef = useRef(null);
    const chartRef = useRef(null);

    useEffect(() => {
        if (!canvasRef.current) return;

        let cancelado = false;

        cargarChartJs().then((Chart) => {
            if (cancelado || !canvasRef.current) return;

            const labels = Object.keys(datos).sort();
            const values = labels.map((k) => datos[k]?.utilidad ?? 0);

            if (chartRef.current) {
                chartRef.current.destroy();
            }

            chartRef.current = new Chart(canvasRef.current, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Utilidad neta',
                            data: values,
                            backgroundColor: values.map((v) =>
                                v >= 0 ? 'rgba(16, 185, 129, 0.45)' : 'rgba(239, 68, 68, 0.45)'
                            ),
                            borderColor: values.map((v) =>
                                v >= 0 ? 'rgba(16, 185, 129, 1)' : 'rgba(239, 68, 68, 1)'
                            ),
                            borderWidth: 1,
                            borderRadius: 4,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                    },
                    scales: {
                        y: {
                            grid: { color: colorGrid() },
                            ticks: {
                                color: colorTexto(),
                                callback: (val) => `$${Number(val).toLocaleString('es-MX')}`,
                            },
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: colorTexto(), maxRotation: 45 },
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
    }, [datos]);

    return (
        <div className={`relative w-full h-[360px] ${className}`}>
            <canvas ref={canvasRef} />
        </div>
    );
}
