import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Calendar, ArrowLeft, Printer, AlertTriangle, Wallet, Scale, Eye } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, THEME_INPUT } from '../../../utils/geliaTheme';
import { RhFieldLabel, RhSelect } from '../Partials/rhFilterFields';
import { formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';

export default function Index({ auth, resumen, colaboradores, configuracion, filtros }) {
    const aplicar = (cambios) => {
        router.get(route('rh.consolidado_deducciones.index'), { ...filtros, ...cambios }, { preserveState: true });
    };

    // Calcular KPIs globales basados en los datos visibles
    const kpiTotalDeducciones = resumen.filas.reduce((acc, f) => acc + f.gran_total, 0);
    const kpiTotalPrestamos = resumen.filas.reduce((acc, f) => acc + f.prestamos, 0);
    const kpiTotalIncidencias = resumen.filas.reduce((acc, f) => acc + f.incidencias, 0);
    const kpiTotalSinIncidencias = resumen.filas.reduce((acc, f) => acc + f.sin_incidencias, 0);

    const handleImprimir = () => {
        window.print();
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Consolidado Deducciones | RH" />
            <GeliaPageShell className="space-y-6">
                <div className="flex justify-between items-center no-print">
                    <Link href={route('rh.index')} className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main">
                        <ArrowLeft className="w-4 h-4" /> Dashboard RH
                    </Link>
                    <button
                        onClick={handleImprimir}
                        className="px-4 py-2 rounded-xl text-[10px] font-black uppercase theme-element theme-border border flex items-center gap-2 hover:theme-text-main"
                    >
                        <Printer className="w-4 h-4" /> Imprimir Reporte
                    </button>
                </div>

                <header className={geliaCardClass('p-6 md:p-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-4')}>
                    <div>
                        <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0 mb-2" style={{ color: 'var(--color-primario)' }}>Pre-Nómina Gerencial</p>
                        <h1 className="text-2xl md:text-4xl font-black italic uppercase tracking-tighter theme-text-main m-0">Consolidado Deducciones</h1>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                            Periodo de análisis: {resumen.fecha_inicio} al {resumen.fecha_fin}
                        </p>
                    </div>
                </header>

                <div className="no-print">
                    <RhSubNav />
                </div>

                {/* Grid de KPIs */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div className={geliaCardClass('p-6 flex items-center justify-between')}>
                        <div>
                            <p className="gelia-rh-field-label m-0 ml-0">Total Deducciones</p>
                            <h3 className="text-2xl font-black theme-text-main m-0 mt-1">{formatoMoneda(kpiTotalDeducciones)}</h3>
                        </div>
                        <div className="p-3 rounded-2xl theme-element border theme-border">
                            <AlertTriangle className="w-6 h-6 text-red-500" />
                        </div>
                    </div>
                    <div className={geliaCardClass('p-6 flex items-center justify-between')}>
                        <div>
                            <p className="gelia-rh-field-label m-0 ml-0">Préstamos Pendientes</p>
                            <h3 className="text-2xl font-black theme-text-main m-0 mt-1">{formatoMoneda(kpiTotalPrestamos)}</h3>
                        </div>
                        <div className="p-3 rounded-2xl theme-element border theme-border">
                            <Wallet className="w-6 h-6 text-amber-500" />
                        </div>
                    </div>
                    <div className={geliaCardClass('p-6 flex items-center justify-between')}>
                        <div>
                            <p className="gelia-rh-field-label m-0 ml-0">Incidencias Operativas</p>
                            <h3 className="text-2xl font-black theme-text-main m-0 mt-1">{formatoMoneda(kpiTotalIncidencias)}</h3>
                        </div>
                        <div className="p-3 rounded-2xl theme-element border theme-border">
                            <Scale className="w-6 h-6 text-indigo-500" />
                        </div>
                    </div>
                    <div className={geliaCardClass('p-6 flex items-center justify-between')}>
                        <div>
                            <p className="gelia-rh-field-label m-0 ml-0">Deducciones Sin Incidencias</p>
                            <h3 className="text-2xl font-black theme-text-main m-0 mt-1">{formatoMoneda(kpiTotalSinIncidencias)}</h3>
                        </div>
                        <div className="p-3 rounded-2xl theme-element border theme-border">
                            <Eye className="w-6 h-6 text-emerald-500" />
                        </div>
                    </div>
                </div>

                {/* Filtros */}
                <div className={geliaCardClass('p-6 grid grid-cols-1 md:grid-cols-4 gap-4 no-print')}>
                    <div>
                        <RhFieldLabel>Fecha inicio</RhFieldLabel>
                        <input type="date" value={filtros.fecha_inicio || ''} onChange={(e) => aplicar({ fecha_inicio: e.target.value })} className={`${THEME_INPUT} w-full px-4 py-3 rounded-2xl text-[11px] font-bold`} />
                    </div>
                    <div>
                        <RhFieldLabel>Fecha fin</RhFieldLabel>
                        <input type="date" value={filtros.fecha_fin || ''} onChange={(e) => aplicar({ fecha_fin: e.target.value })} className={`${THEME_INPUT} w-full px-4 py-3 rounded-2xl text-[11px] font-bold`} />
                    </div>
                    <div>
                        <RhFieldLabel>Colaborador</RhFieldLabel>
                        <RhSelect value={filtros.rh_colaborador_id || ''} onChange={(e) => aplicar({ rh_colaborador_id: e.target.value || undefined })}>
                            <option value="">Todos</option>
                            {colaboradores.map((c) => (
                                <option key={c.id} value={c.id}>{nombreCompletoColaborador(c)}</option>
                            ))}
                        </RhSelect>
                    </div>
                    <div className="flex items-end">
                        <button type="button" onClick={() => aplicar({})} className="w-full px-4 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center justify-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Calendar className="w-4 h-4" /> Actualizar
                        </button>
                    </div>
                </div>

                {/* Tabla de Deducciones */}
                <div className={geliaCardClass('overflow-hidden')}>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse">
                            <thead>
                                <tr className="border-b theme-border bg-black/5 dark:bg-white/5">
                                    <th className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Colaborador</th>
                                    <th className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Departamento / Área</th>
                                    <th className="px-4 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Préstamos</th>
                                    <th className="px-4 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Incidencias Op.</th>
                                    <th className="px-4 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Faltas (Salario)</th>
                                    <th className="px-4 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Bono Productividad</th>
                                    <th className="px-4 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Bono Puntualidad</th>
                                    <th className="px-4 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Salidas Pers.</th>
                                    <th className="px-4 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Gran Total</th>
                                    <th className="px-4 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Sin Incidencias</th>
                                </tr>
                            </thead>
                            <tbody>
                                {resumen.filas.length === 0 ? (
                                    <tr>
                                        <td colSpan="10" className="px-4 py-8 text-center text-xs theme-text-muted">
                                            No se encontraron deducciones pendientes para el periodo de análisis.
                                        </td>
                                    </tr>
                                ) : (
                                    resumen.filas.map((fila) => (
                                        <tr key={fila.colaborador.id} className="border-b theme-border last:border-0 hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                            <td className="px-4 py-4">
                                                <div className="text-sm font-bold theme-text-main">{nombreCompletoColaborador(fila.colaborador)}</div>
                                                <div className="text-[10px] theme-text-muted font-mono">{fila.colaborador.folio}</div>
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="text-xs font-bold theme-text-main">{fila.colaborador.departamento?.nombre || '—'}</div>
                                                <div className="text-[10px] theme-text-muted">{fila.colaborador.area?.nombre || '—'}</div>
                                            </td>
                                            <td className="px-4 py-4 text-right text-xs font-semibold theme-text-main">
                                                {fila.prestamos > 0 ? formatoMoneda(fila.prestamos) : '—'}
                                            </td>
                                            <td className="px-4 py-4 text-right text-xs font-semibold theme-text-main">
                                                {fila.incidencias > 0 ? formatoMoneda(fila.incidencias) : '—'}
                                            </td>
                                            <td className="px-4 py-4 text-right text-xs font-semibold theme-text-main">
                                                {fila.faltas_salario > 0 ? formatoMoneda(fila.faltas_salario) : '—'}
                                            </td>
                                            <td className="px-4 py-4 text-right text-xs font-semibold theme-text-main">
                                                {fila.faltas_productividad > 0 ? formatoMoneda(fila.faltas_productividad) : '—'}
                                            </td>
                                            <td className="px-4 py-4 text-right text-xs font-semibold theme-text-main">
                                                {fila.faltas_puntualidad > 0 ? formatoMoneda(fila.faltas_puntualidad) : '—'}
                                            </td>
                                            <td className="px-4 py-4 text-right text-xs font-semibold theme-text-main">
                                                {fila.salidas_personales > 0 ? formatoMoneda(fila.salidas_personales) : '—'}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm font-black text-red-500">
                                                {fila.gran_total > 0 ? formatoMoneda(fila.gran_total) : '—'}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm font-black text-indigo-500">
                                                {fila.sin_incidencias > 0 ? formatoMoneda(fila.sin_incidencias) : '—'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </GeliaPageShell>
        </AppLayout>
    );
}
