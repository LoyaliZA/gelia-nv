import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Loader2, RefreshCw, X, BarChart3 } from 'lucide-react';
import { formatoMoneda } from '../../../utils/formatoMoneda';
import { cargarChartJs } from '../../../utils/cargarChartJs';
import {
    BTN_PRIMARY,
    BTN_PRIMARY_STYLE,
    CONTABILIDAD_INNER,
    KPI_BORDES,
    SECTION_TITLE,
} from './contabilidadStyles';
import { THEME_INPUT, THEME_MODAL_OVERLAY as OVERLAY, THEME_MODAL_SHELL as SHELL } from '../../../utils/geliaTheme';
import { contabilidadRoutes } from '../contabilidadRoutes';

const MESES = [
    { value: '01', label: 'Enero' },
    { value: '02', label: 'Febrero' },
    { value: '03', label: 'Marzo' },
    { value: '04', label: 'Abril' },
    { value: '05', label: 'Mayo' },
    { value: '06', label: 'Junio' },
    { value: '07', label: 'Julio' },
    { value: '08', label: 'Agosto' },
    { value: '09', label: 'Septiembre' },
    { value: '10', label: 'Octubre' },
    { value: '11', label: 'Noviembre' },
    { value: '12', label: 'Diciembre' },
];

function tickColor() {
    return getComputedStyle(document.documentElement).getPropertyValue('--theme-text-muted').trim() || '#94a3b8';
}

export default function ModalAnalisis({ abierto, onCerrar, filtrosIniciales }) {
    const [filtroTipo, setFiltroTipo] = useState('mes');
    const [mes, setMes] = useState(String(filtrosIniciales?.mes || new Date().getMonth() + 1).padStart(2, '0'));
    const [anio, setAnio] = useState(String(filtrosIniciales?.anio || new Date().getFullYear()));
    const [fecha, setFecha] = useState(new Date().toISOString().split('T')[0]);
    const [inicio, setInicio] = useState('');
    const [fin, setFin] = useState('');
    const [cargando, setCargando] = useState(false);
    const [datos, setDatos] = useState(null);

    const chartPlatRef = useRef(null);
    const chartLineRef = useRef(null);
    const canvasPlatRef = useRef(null);
    const canvasLineRef = useRef(null);

    const consultar = useCallback(async () => {
        setCargando(true);
        try {
            const params = new URLSearchParams({ filtro: filtroTipo });
            if (filtroTipo === 'mes') {
                params.set('mes', mes);
                params.set('anio', anio);
            } else if (filtroTipo === 'dia') {
                params.set('fecha', fecha);
            } else if (filtroTipo === 'anio') {
                params.set('anio', anio);
            } else if (filtroTipo === 'custom') {
                params.set('inicio', inicio);
                params.set('fin', fin);
            }

            const res = await fetch(`${contabilidadRoutes.dashboardData()}?${params}`);
            const json = await res.json();
            setDatos(json);
        } catch (e) {
            console.error(e);
        } finally {
            setCargando(false);
        }
    }, [filtroTipo, mes, anio, fecha, inicio, fin]);

    useEffect(() => {
        if (abierto) {
            consultar();
        }
    }, [abierto, consultar]);

    useEffect(() => {
        if (!abierto || !datos || !canvasPlatRef.current || !canvasLineRef.current) return;

        let cancelado = false;

        cargarChartJs().then((Chart) => {
            if (cancelado) return;

            if (chartPlatRef.current) chartPlatRef.current.destroy();
            if (chartLineRef.current) chartLineRef.current.destroy();

            const platLabels = Object.keys(datos.plataformas || {});
            const platValues = platLabels.map((k) => datos.plataformas[k]);

            chartPlatRef.current = new Chart(canvasPlatRef.current, {
                type: 'doughnut',
                data: {
                    labels: platLabels,
                    datasets: [{
                        data: platValues,
                        backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#06b6d4'],
                        borderWidth: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: tickColor(), boxWidth: 12 } } },
                },
            });

            const grafLabels = Object.keys(datos.grafica || {});
            const ventas = grafLabels.map((k) => datos.grafica[k]?.venta ?? 0);
            const utilidad = grafLabels.map((k) => datos.grafica[k]?.utilidad ?? 0);

            chartLineRef.current = new Chart(canvasLineRef.current, {
                type: 'line',
                data: {
                    labels: grafLabels,
                    datasets: [
                        {
                            label: 'Venta',
                            data: ventas,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.3,
                            fill: true,
                        },
                        {
                            label: 'Utilidad',
                            data: utilidad,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.3,
                            fill: true,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: tickColor() } } },
                    scales: {
                        y: {
                            ticks: { color: tickColor(), callback: (v) => `$${Number(v).toLocaleString('es-MX')}` },
                            grid: { color: 'color-mix(in srgb, var(--theme-text-muted) 20%, transparent)' },
                        },
                        x: { ticks: { color: tickColor(), maxRotation: 45 }, grid: { display: false } },
                    },
                },
            });
        });

        return () => {
            cancelado = true;
            if (chartPlatRef.current) {
                chartPlatRef.current.destroy();
                chartPlatRef.current = null;
            }
            if (chartLineRef.current) {
                chartLineRef.current.destroy();
                chartLineRef.current = null;
            }
        };
    }, [abierto, datos]);

    if (!abierto) return null;

    const kpis = datos?.kpis || {};

    const tarjetas = [
        { id: 'ventas', label: 'Venta bruta', valor: formatoMoneda(kpis.ventas), borde: KPI_BORDES.ventas, color: 'text-blue-500' },
        { id: 'notas', label: 'Notas AE', valor: formatoMoneda(kpis.notasAE), borde: KPI_BORDES.notas, color: 'text-indigo-500' },
        { id: 'ganancias', label: 'Ganancia neta', valor: formatoMoneda(kpis.ganancias), borde: KPI_BORDES.ganancia, color: 'text-emerald-500' },
        { id: 'margen', label: 'Margen', valor: `${Number(kpis.margen || 0).toFixed(2)}%`, borde: KPI_BORDES.margen, color: 'text-teal-500' },
        { id: 'perdidas', label: 'Pérdidas', valor: formatoMoneda(kpis.perdidas), borde: KPI_BORDES.perdidas, color: 'text-red-500' },
        { id: 'comisiones', label: 'Comisiones', valor: formatoMoneda(kpis.comisiones), borde: KPI_BORDES.comisiones, color: 'text-orange-500' },
        { id: 'envios', label: 'Envíos empresa', valor: formatoMoneda(kpis.enviosEmpresa), borde: KPI_BORDES.envios, color: 'text-violet-500' },
        {
            id: 'enviosCli',
            label: 'Envíos cliente',
            valor: `${kpis.enviosClientesCount || 0} ped. (${formatoMoneda(kpis.enviosClientesMonto || 0)})`,
            borde: KPI_BORDES.enviosCliente,
            color: 'text-slate-400',
        },
    ];

    return createPortal(
        <div className={`${OVERLAY} z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md`} onClick={onCerrar}>
            <div
                className={`${SHELL} w-full max-w-6xl max-h-[95vh] overflow-hidden flex flex-col rounded-[2rem] shadow-2xl`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between gap-3 p-6 md:p-8 border-b theme-border shrink-0 theme-surface">
                    <div className="flex items-center gap-3">
                        <BarChart3 className="w-6 h-6 text-[var(--color-primario)] shrink-0" />
                        <h2 className="text-xl font-black italic theme-text-main uppercase m-0">
                            Análisis financiero
                        </h2>
                    </div>
                    <button type="button" onClick={onCerrar} className="theme-btn-icon rounded-xl p-2 theme-text-muted hover:theme-text-main transition-transform hover:scale-110 outline-none">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="overflow-y-auto custom-scrollbar p-5 md:p-6 space-y-5 theme-surface flex-1">
                    <div className={`${CONTABILIDAD_INNER} p-4 md:p-5 flex flex-wrap gap-3 items-end`}>
                        <div>
                            <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Filtrar por</label>
                            <select
                                className={`${THEME_INPUT} mt-1 min-w-[140px] rounded-xl font-bold`}
                                value={filtroTipo}
                                onChange={(e) => setFiltroTipo(e.target.value)}
                            >
                                <option value="mes">Mes específico</option>
                                <option value="dia">Día específico</option>
                                <option value="anio">Todo el año</option>
                                <option value="custom">Rango personalizado</option>
                            </select>
                        </div>

                        {filtroTipo === 'mes' && (
                            <div className="flex gap-2">
                                <select className={`${THEME_INPUT} rounded-xl font-bold`} value={mes} onChange={(e) => setMes(e.target.value)}>
                                    {MESES.map((m) => (
                                        <option key={m.value} value={m.value}>{m.label}</option>
                                    ))}
                                </select>
                                <select className={`${THEME_INPUT} rounded-xl font-bold`} value={anio} onChange={(e) => setAnio(e.target.value)}>
                                    {[2025, 2026, 2027].map((y) => (
                                        <option key={y} value={y}>{y}</option>
                                    ))}
                                </select>
                            </div>
                        )}

                        {filtroTipo === 'dia' && (
                            <input type="date" className={`${THEME_INPUT} rounded-xl font-bold`} value={fecha} onChange={(e) => setFecha(e.target.value)} />
                        )}

                        {filtroTipo === 'anio' && (
                            <select className={`${THEME_INPUT} rounded-xl font-bold`} value={anio} onChange={(e) => setAnio(e.target.value)}>
                                {[2025, 2026, 2027].map((y) => (
                                    <option key={y} value={y}>{y}</option>
                                ))}
                            </select>
                        )}

                        {filtroTipo === 'custom' && (
                            <div className="flex items-center gap-2">
                                <input type="date" className={`${THEME_INPUT} rounded-xl font-bold`} value={inicio} onChange={(e) => setInicio(e.target.value)} />
                                <span className="theme-text-muted text-sm font-bold">a</span>
                                <input type="date" className={`${THEME_INPUT} rounded-xl font-bold`} value={fin} onChange={(e) => setFin(e.target.value)} />
                            </div>
                        )}

                        <button type="button" onClick={consultar} disabled={cargando} className={BTN_PRIMARY} style={BTN_PRIMARY_STYLE}>
                            {cargando ? <Loader2 className="w-4 h-4 animate-spin" /> : <RefreshCw className="w-4 h-4" />}
                            Consultar
                        </button>
                    </div>

                    {cargando && !datos ? (
                        <div className="flex justify-center py-16">
                            <Loader2 className="w-8 h-8 animate-spin text-[var(--color-primario)]" />
                        </div>
                    ) : (
                        <>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                {tarjetas.map((t) => (
                                    <div key={t.id} className={`${CONTABILIDAD_INNER} p-4 md:p-5 ${t.borde}`}>
                                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">{t.label}</p>
                                        <p className={`text-lg md:text-xl font-black italic mt-2 ${t.color}`}>{t.valor}</p>
                                    </div>
                                ))}
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className={`${CONTABILIDAD_INNER} p-4 md:p-6 md:col-span-1`}>
                                    <h3 className={`${SECTION_TITLE} text-center mb-4`}>
                                        Comisiones por plataforma
                                    </h3>
                                    <div className="h-64 relative">
                                        <canvas ref={canvasPlatRef} />
                                    </div>
                                </div>
                                <div className={`${CONTABILIDAD_INNER} p-4 md:p-6 md:col-span-2`}>
                                    <h3 className={`${SECTION_TITLE} text-center mb-4`}>
                                        Venta vs utilidad
                                    </h3>
                                    <div className="h-64 relative">
                                        <canvas ref={canvasLineRef} />
                                    </div>
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}
