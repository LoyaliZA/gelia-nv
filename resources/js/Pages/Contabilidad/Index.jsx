import React, { useCallback, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Calculator,
    HelpCircle,
    LineChart,
    Settings,
    Wallet,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { geliaCardClass, THEME_INPUT } from '../../utils/geliaTheme';
import { formatoMoneda } from '../../utils/formatoMoneda';
import { puedePermiso } from '../../utils/permisos';
import {
    BTN_ACCION,
    HERO_EYEBROW,
    HERO_SUBTITLE,
    HERO_TITLE,
    KPI_BORDES,
    SECTION_TITLE,
    contabilidadCard,
} from './Partials/contabilidadStyles';
import FormRegistroPedido from './Partials/FormRegistroPedido';
import ModalAnalisis from './Partials/ModalAnalisis';
import ModalConfigComisiones from './Partials/ModalConfigComisiones';
import HistorialPedidos from './Partials/HistorialPedidos';
import GraficaUtilidadPeriodo from './Partials/GraficaUtilidadPeriodo';
import { contabilidadRoutes } from './contabilidadRoutes';

const MESES = [
    { value: 1, label: 'Enero' },
    { value: 2, label: 'Febrero' },
    { value: 3, label: 'Marzo' },
    { value: 4, label: 'Abril' },
    { value: 5, label: 'Mayo' },
    { value: 6, label: 'Junio' },
    { value: 7, label: 'Julio' },
    { value: 8, label: 'Agosto' },
    { value: 9, label: 'Septiembre' },
    { value: 10, label: 'Octubre' },
    { value: 11, label: 'Noviembre' },
    { value: 12, label: 'Diciembre' },
];

const KPIS = [
    { id: 'pedidos', label: 'Pedidos', key: 'total_pedidos', icon: Calculator, color: 'text-sky-500', borde: KPI_BORDES.pedidos, format: (v) => v },
    { id: 'ventas', label: 'Ventas', key: 'ventas_total', icon: Wallet, color: 'text-emerald-500', borde: KPI_BORDES.ventas, format: formatoMoneda },
    { id: 'utilidad', label: 'Utilidad neta', key: 'utilidad_total', icon: BarChart3, color: 'text-violet-500', borde: KPI_BORDES.utilidad, format: formatoMoneda },
    { id: 'pendientes', label: 'Pendientes retiro', key: 'pendientes', icon: Wallet, color: 'text-amber-500', borde: KPI_BORDES.pendientes, format: (v) => v },
];

export default function Index({
    auth,
    pedidos,
    metricas,
    plataformas,
    tipos_transaccion,
    estatus_pago,
    datos_grafica,
    filtros,
    configuracion,
}) {
    const { flash } = usePage().props;
    const [modalAnalisis, setModalAnalisis] = useState(false);
    const [modalConfig, setModalConfig] = useState(false);

    const puedeCrear = puedePermiso(auth, 'contabilidad.pedidos.crear');
    const puedeConfig = puedePermiso(auth, 'contabilidad.plataformas.configurar');

    const aplicarFiltros = useCallback(
        (extra = {}) => {
            router.get(
                contabilidadRoutes.index(),
                {
                    mes: filtros.mes,
                    anio: filtros.anio,
                    q: filtros.q || undefined,
                    plataforma_id: filtros.plataforma_id || undefined,
                    estatus_pago_id: filtros.estatus_pago_id || undefined,
                    tipo_transaccion_id: filtros.tipo_transaccion_id || undefined,
                    ...extra,
                },
                { preserveState: true, preserveScroll: true, replace: true }
            );
        },
        [filtros]
    );

    return (
        <AppLayout auth={auth}>
            <Head title="Contabilidad Bellaroma" />

            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8 contabilidad-page">
                {flash?.success && (
                    <div className="theme-surface theme-card border border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 rounded-2xl px-4 py-3 text-sm font-bold animate-page-reveal">
                        {flash.success}
                    </div>
                )}

                {/* Hero header */}
                <header className={`${contabilidadCard()} p-6 md:p-12 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6`}>
                    <div className="w-full md:w-auto text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full shrink-0" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p className={HERO_EYEBROW} style={{ color: 'var(--color-primario)' }}>
                                Panel Contable
                            </p>
                            <div className="group relative hidden sm:block">
                                <HelpCircle className="w-4 h-4 theme-text-muted hover:theme-text-main cursor-help" />
                                <div className="absolute left-0 top-full mt-2 w-72 p-3 theme-surface theme-card border theme-border rounded-xl text-xs theme-text-muted opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50 shadow-xl">
                                    <p className="font-bold theme-text-main mb-1">Guía rápida</p>
                                    <ul className="space-y-1 list-disc pl-4">
                                        <li>Carga el Excel de precios para habilitar nuevos pedidos.</li>
                                        <li>Usa Análisis para KPIs y gráficas del periodo.</li>
                                        <li>Filtra por mes y busca por número de pedido o cliente.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <h1 className={HERO_TITLE}>
                            CONTABILIDAD <span style={{ color: 'var(--color-primario)' }}>BELLAROMA</span>
                        </h1>
                        <p className={HERO_SUBTITLE}>
                            Comisiones, utilidad y conciliación e-commerce
                        </p>
                    </div>

                    <div className="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2 w-full md:w-auto">
                        <div className={`flex overflow-hidden rounded-xl border theme-border ${geliaCardClass('!p-0')}`}>
                            <select
                                className={`${THEME_INPUT} border-0 rounded-none min-w-[110px] text-[11px] font-bold uppercase`}
                                value={filtros.mes}
                                onChange={(e) => aplicarFiltros({ mes: Number(e.target.value), page: 1 })}
                            >
                                {MESES.map((m) => (
                                    <option key={m.value} value={m.value}>{m.label}</option>
                                ))}
                            </select>
                            <select
                                className={`${THEME_INPUT} border-0 border-l theme-border rounded-none min-w-[90px] text-[11px] font-bold uppercase`}
                                value={filtros.anio}
                                onChange={(e) => aplicarFiltros({ anio: Number(e.target.value), page: 1 })}
                            >
                                {[2025, 2026, 2027].map((y) => (
                                    <option key={y} value={y}>{y}</option>
                                ))}
                            </select>
                        </div>

                        <button
                            type="button"
                            onClick={() => setModalAnalisis(true)}
                            className={BTN_ACCION.analisis}
                        >
                            <LineChart className="w-4 h-4 shrink-0" />
                            Análisis
                        </button>

                        <Link href={contabilidadRoutes.retiros()} className={BTN_ACCION.retiros}>
                            <Wallet className="w-4 h-4 shrink-0" />
                            Retiros
                        </Link>

                        {puedeConfig && (
                            <button type="button" onClick={() => setModalConfig(true)} className={BTN_ACCION.analisis} title="Configurar comisiones">
                                <Settings className="w-4 h-4 shrink-0" />
                                Config
                            </button>
                        )}
                    </div>
                </header>

                {/* KPIs */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
                    {KPIS.map((kpi, index) => (
                        <div
                            key={kpi.id}
                            className={`${contabilidadCard('p-4 md:p-5')} ${kpi.borde}`}
                            style={{ animationDelay: `${100 + index * 50}ms` }}
                        >
                            <div className="flex justify-between items-start">
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">{kpi.label}</p>
                                <kpi.icon className={`w-4 h-4 shrink-0 ${kpi.color}`} />
                            </div>
                            <p className={`text-xl md:text-2xl font-black italic mt-2 ${kpi.color}`}>
                                {kpi.format(metricas[kpi.key])}
                            </p>
                        </div>
                    ))}
                </div>

                {/* Registro de pedidos */}
                <FormRegistroPedido
                    plataformas={plataformas}
                    tiposTransaccion={tipos_transaccion}
                    puedeCrear={puedeCrear}
                    configuracion={configuracion}
                    puedeConfigurar={puedeConfig}
                />

                <HistorialPedidos
                    auth={auth}
                    pedidos={pedidos}
                    plataformas={plataformas}
                    tiposTransaccion={tipos_transaccion}
                    estatusPago={estatus_pago}
                    filtros={filtros}
                />

                {/* Gráfica periodo */}
                <div className={`${contabilidadCard('p-6 md:p-8')}`} style={{ animationDelay: '250ms' }}>
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                        <h2 className={SECTION_TITLE}>Análisis de utilidad del periodo</h2>
                        <div className="flex gap-6 text-right">
                            <div>
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Ventas totales</p>
                                <p className="text-lg font-black italic theme-text-main">{formatoMoneda(metricas.ventas_total)}</p>
                            </div>
                            <div className="border-l theme-border pl-6">
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Utilidad neta</p>
                                <p className={`text-lg font-black italic ${metricas.utilidad_total >= 0 ? 'text-emerald-500' : 'text-red-500'}`}>
                                    {formatoMoneda(metricas.utilidad_total)}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="theme-element border theme-border rounded-2xl p-4 md:p-6">
                        <GraficaUtilidadPeriodo datos={datos_grafica || {}} />
                    </div>
                </div>
            </div>

            <ModalAnalisis
                abierto={modalAnalisis}
                onCerrar={() => setModalAnalisis(false)}
                filtrosIniciales={filtros}
            />

            <ModalConfigComisiones
                abierto={modalConfig}
                plataformas={plataformas}
                configuracion={configuracion}
                onCerrar={() => setModalConfig(false)}
            />
        </AppLayout>
    );
}
