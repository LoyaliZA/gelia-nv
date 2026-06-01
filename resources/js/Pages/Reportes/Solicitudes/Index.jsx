import React, { useCallback, useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { FileSpreadsheet, FileText, Download, BarChart3 } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import FiltrosSolicitudes from '../../Solicitudes/Partials/FiltrosSolicitudes';
import { geliaCardClass } from '../../../utils/geliaTheme';

const BTN_SECONDARY = 'inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border theme-border theme-element theme-text-main text-[10px] font-black uppercase tracking-widest hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all';

const calcularRangoFechas = (tipo, fInicio, fFin) => {
    let inicioCalculado = fInicio;
    let finCalculado = fFin;

    if (tipo !== 'PERSONALIZADO' && tipo !== 'TODAS') {
        const hoy = new Date();
        const formatDate = (d) => d.toISOString().split('T')[0];

        if (tipo === 'HOY') {
            inicioCalculado = finCalculado = formatDate(hoy);
        } else if (tipo === 'AYER') {
            const ayer = new Date(hoy);
            ayer.setDate(ayer.getDate() - 1);
            inicioCalculado = finCalculado = formatDate(ayer);
        } else if (tipo === 'SEMANA') {
            const primerDia = new Date(hoy);
            primerDia.setDate(primerDia.getDate() - primerDia.getDay() + 1);
            inicioCalculado = formatDate(primerDia);
            finCalculado = formatDate(hoy);
        } else if (tipo === 'MES') {
            const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
            inicioCalculado = formatDate(primerDiaMes);
            finCalculado = formatDate(hoy);
        }
    } else if (tipo === 'TODAS') {
        inicioCalculado = '';
        finCalculado = '';
    }

    return { inicioCalculado, finCalculado };
};

export default function Index({ auth, filtros = {}, total = 0, vendedores = [] }) {
    const [tabActiva, setTabActiva] = useState(filtros.tab || 'TODAS');
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [filtroVendedor, setFiltroVendedor] = useState(filtros.vendedor_id || '');
    const [filtroMotivo, setFiltroMotivo] = useState(filtros.motivo_incorrecta || '');
    const [tipoFecha, setTipoFecha] = useState(filtros.tipo_fecha || 'TODAS');
    const [fechaInicio, setFechaInicio] = useState(filtros.fecha_inicio || '');
    const [fechaFin, setFechaFin] = useState(filtros.fecha_fin || '');
    const [cargando, setCargando] = useState(false);

    const filtrosAdicionalesActivos = [filtroVendedor, filtroMotivo].filter(Boolean).length;

    const construirParams = useCallback(
        (overrides = {}) => {
            const tab = overrides.tab ?? tabActiva;
            const vendedorId = overrides.vendedor_id ?? filtroVendedor;
            const motivo = overrides.motivo_incorrecta ?? filtroMotivo;
            const tipo = overrides.tipo_fecha ?? tipoFecha;
            const q = overrides.q !== undefined ? overrides.q : busqueda;
            const fInicio = overrides.fecha_inicio ?? fechaInicio;
            const fFin = overrides.fecha_fin ?? fechaFin;
            const { inicioCalculado, finCalculado } = calcularRangoFechas(tipo, fInicio, fFin);
            const tabFinal = motivo ? 'INCORRECTAS' : tab;

            return Object.fromEntries(
                Object.entries({
                    tab: tabFinal !== 'TODAS' ? tabFinal : undefined,
                    vendedor_id: vendedorId || undefined,
                    tipo_fecha: tipo !== 'TODAS' ? tipo : undefined,
                    fecha_inicio: inicioCalculado || undefined,
                    fecha_fin: finCalculado || undefined,
                    motivo_incorrecta: motivo || undefined,
                    q: q?.trim() || undefined,
                }).filter(([, v]) => v !== '' && v !== null && v !== undefined)
            );
        },
        [tabActiva, filtroVendedor, filtroMotivo, tipoFecha, busqueda, fechaInicio, fechaFin]
    );

    const aplicarFiltros = (overrides = {}) => {
        const tab = overrides.tab ?? tabActiva;
        const vendedorId = overrides.vendedor_id ?? filtroVendedor;
        const motivo = overrides.motivo_incorrecta ?? filtroMotivo;
        const tipo = overrides.tipo_fecha ?? tipoFecha;
        const q = overrides.q !== undefined ? overrides.q : busqueda;
        const fInicio = overrides.fecha_inicio ?? fechaInicio;
        const fFin = overrides.fecha_fin ?? fechaFin;

        if (overrides.tab !== undefined) setTabActiva(tab);
        if (overrides.vendedor_id !== undefined) setFiltroVendedor(vendedorId);
        if (overrides.motivo_incorrecta !== undefined) {
            setFiltroMotivo(motivo);
            if (motivo) setTabActiva('INCORRECTAS');
        }
        if (overrides.tipo_fecha !== undefined) setTipoFecha(tipo);
        if (overrides.q !== undefined) setBusqueda(q);
        if (overrides.fecha_inicio !== undefined) setFechaInicio(fInicio);
        if (overrides.fecha_fin !== undefined) setFechaFin(fFin);

        setCargando(true);
        router.get(route('reportes.solicitudes.index'), construirParams(overrides), {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setCargando(false),
        });
    };

    const limpiarFiltrosAdicionales = () => {
        const nuevaTab = filtroMotivo ? 'TODAS' : tabActiva;
        aplicarFiltros({ vendedor_id: '', motivo_incorrecta: '', tab: nuevaTab });
    };

    const exportParams = useMemo(() => construirParams(), [construirParams]);

    const cardHeader = geliaCardClass('p-6 md:p-10 flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-6');
    const cardMetricas = geliaCardClass('p-5 md:p-6 flex items-center gap-4 min-w-0');

    return (
        <AppLayout auth={auth}>
            <Head title="Reporte de Solicitudes Financieras" />

            <div className="gelia-page-shell space-y-6 md:space-y-8">
                <header className={cardHeader}>
                    <div className="min-w-0">
                        <div className="flex items-center gap-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full shrink-0" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0" style={{ color: 'var(--color-primario)' }}>
                                Reportes
                            </p>
                        </div>
                        <h1 className="text-2xl sm:text-3xl md:text-5xl font-black italic uppercase tracking-tighter theme-text-main m-0 leading-none">
                            Solicitudes <span style={{ color: 'var(--color-primario)' }}>Financieras</span>
                        </h1>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                            Exportación personalizada en PDF, Excel y CSV
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2 w-full lg:w-auto shrink-0">
                        <Link href={route('solicitudes.index')} className={BTN_SECONDARY}>
                            Ver listado
                        </Link>
                        <a href={route('reportes.solicitudes.exportar', { ...exportParams, format: 'pdf' })} className={BTN_SECONDARY}>
                            <FileText className="w-4 h-4 shrink-0" /> PDF
                        </a>
                        <a href={route('reportes.solicitudes.exportar', { ...exportParams, format: 'xlsx' })} className={BTN_SECONDARY}>
                            <FileSpreadsheet className="w-4 h-4 shrink-0" /> Excel
                        </a>
                        <a href={route('reportes.solicitudes.exportar', { ...exportParams, format: 'csv' })} className={BTN_SECONDARY}>
                            <Download className="w-4 h-4 shrink-0" /> CSV
                        </a>
                    </div>
                </header>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 md:gap-6 min-w-0">
                    <div className={cardMetricas}>
                        <div className="p-3 rounded-2xl theme-element border theme-border shrink-0">
                            <BarChart3 className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        </div>
                        <div className="min-w-0">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Registros coincidentes</p>
                            <p className="text-2xl font-black theme-text-main m-0 tabular-nums leading-tight mt-1">
                                {cargando ? '…' : total.toLocaleString('es-MX')}
                            </p>
                        </div>
                    </div>
                    <div className={cardMetricas}>
                        <div className="p-3 rounded-2xl theme-element border theme-border shrink-0">
                            <FileSpreadsheet className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        </div>
                        <div className="min-w-0">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Columnas del reporte</p>
                            <p className="text-xs font-bold theme-text-main m-0 leading-relaxed mt-1">
                                Vendedora, emisión, cliente, tipo, proceso, resolución y fecha final
                            </p>
                        </div>
                    </div>
                </div>

                <FiltrosSolicitudes
                    tabActiva={tabActiva}
                    busqueda={busqueda}
                    tipoFecha={tipoFecha}
                    fechaInicio={fechaInicio}
                    fechaFin={fechaFin}
                    filtroVendedor={filtroVendedor}
                    filtroMotivo={filtroMotivo}
                    vendedores={vendedores}
                    filtrosActivos={filtrosAdicionalesActivos}
                    onCambiarTab={(tab) => aplicarFiltros({ tab })}
                    onCambiarBusqueda={setBusqueda}
                    onAplicarFiltros={aplicarFiltros}
                    onLimpiarAdicionales={limpiarFiltrosAdicionales}
                />
            </div>
        </AppLayout>
    );
}
