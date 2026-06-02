import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { FileSpreadsheet, FileText, Download, BarChart3 } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import FiltrosSolicitudes from '@/Components/Filtros/FiltrosSolicitudes';
import useFiltrosSolicitudesPage from '@/hooks/useFiltrosSolicitudesPage';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';

const BTN_SECONDARY = 'inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border theme-border theme-element theme-text-main text-[10px] font-black uppercase tracking-widest hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all';

export default function Index({ auth, filtros = {}, total = 0, vendedores = [] }) {
    const [cargando, setCargando] = useState(false);

    const {
        tabActiva,
        busqueda,
        tipoFecha,
        fechaInicio,
        fechaFin,
        filtroVendedor,
        filtroMotivo,
        filtrosAdicionalesActivos,
        exportParams,
        aplicarFiltros,
        limpiarFiltrosAdicionales,
    } = useFiltrosSolicitudesPage({
        filtros,
        rutaIndex: route('reportes.solicitudes.index'),
        onInicioConsulta: () => setCargando(true),
        onFinConsulta: () => setCargando(false),
    });

    const cardHeader = geliaCardClass('p-6 md:p-10 flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-6');
    const cardMetricas = geliaCardClass('p-5 md:p-6 flex items-center gap-4 min-w-0');

    return (
        <AppLayout auth={auth}>
            <Head title="Reporte de Solicitudes Financieras" />

            <GeliaPageShell className="space-y-6 md:space-y-8">
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
                    idPrefixFechas="reporte-fecha"
                    etiquetaBuscar="Buscar en el reporte"
                    onCambiarTab={(tab) => aplicarFiltros({ tab })}
                    onAplicarFiltros={aplicarFiltros}
                    onLimpiarAdicionales={limpiarFiltrosAdicionales}
                />
            </GeliaPageShell>
        </AppLayout>
    );
}
