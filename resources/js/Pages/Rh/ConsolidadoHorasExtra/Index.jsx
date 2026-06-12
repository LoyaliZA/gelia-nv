import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Calendar, ArrowLeft, Printer, Clock, Coins, Users, CheckCircle } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, THEME_INPUT } from '../../../utils/geliaTheme';
import { RhFieldLabel, RhSelect } from '../Partials/rhFilterFields';
import { formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';

export default function Index({ auth, resumen, colaboradores, configuracion, filtros, puedeLiquidar }) {
    const [localFiltros, setLocalFiltros] = useState({
        fecha_inicio: filtros.fecha_inicio || '',
        fecha_fin: filtros.fecha_fin || '',
        rh_colaborador_id: filtros.rh_colaborador_id || '',
    });

    const aplicar = () => {
        router.get(route('rh.consolidado_horas_extra.index'), localFiltros, { preserveState: true, preserveScroll: true });
    };

    // KPIs globales basados en los datos visibles
    const kpiTotalHoras = resumen.filas.reduce((acc, f) => acc + f.horas_extra_acumuladas, 0);
    const kpiTotalMonto = resumen.filas.reduce((acc, f) => acc + f.total_economico_acumulado, 0);
    const kpiColaboradoresConHe = resumen.filas.filter(f => f.horas_extra_acumuladas > 0).length;

    const liquidarPago = () => {
        const mensaje = filtros.rh_colaborador_id
            ? '¿Confirmas que deseas procesar y liquidar el pago de las horas extra pendientes para este colaborador en el periodo visible?'
            : '¿Confirmas que deseas procesar y liquidar el pago de las horas extra pendientes para TODOS los colaboradores en el periodo visible?';

        if (!window.confirm(mensaje)) return;

        router.post(route('rh.consolidado_horas_extra.liquidar'), {
            fecha_inicio: localFiltros.fecha_inicio,
            fecha_fin: localFiltros.fecha_fin,
            rh_colaborador_id: localFiltros.rh_colaborador_id,
        }, {
            preserveScroll: true
        });
    };

    const handleImprimir = () => {
        window.print();
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Consolidado Horas Extra | RH" />
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
                        <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0 mb-2" style={{ color: 'var(--color-primario)' }}>Percepciones Adicionales</p>
                        <h1 className="text-2xl md:text-4xl font-black italic uppercase tracking-tighter theme-text-main m-0">Consolidado Horas Extra</h1>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                            Pendientes de cobro al: {resumen.fecha_fin}
                        </p>
                    </div>
                    {puedeLiquidar && kpiTotalHoras > 0 && (
                        <button
                            type="button"
                            onClick={liquidarPago}
                            className="no-print px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center gap-2 shadow-sm transition-all hover:opacity-90"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            <CheckCircle className="w-4 h-4" /> Confirmar y Procesar Pago
                        </button>
                    )}
                </header>

                <div className="no-print">
                    <RhSubNav />
                </div>

                {/* Grid de KPIs */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className={geliaCardClass('p-6 flex items-center justify-between')}>
                        <div>
                            <p className="gelia-rh-field-label m-0 ml-0">Horas Extra Pendientes</p>
                            <h3 className="text-2xl font-black theme-text-main m-0 mt-1">{kpiTotalHoras} hrs</h3>
                        </div>
                        <div className="p-3 rounded-2xl theme-element border theme-border">
                            <Clock className="w-6 h-6 text-amber-500" />
                        </div>
                    </div>
                    <div className={geliaCardClass('p-6 flex items-center justify-between')}>
                        <div>
                            <p className="gelia-rh-field-label m-0 ml-0">Monto Económico Acumulado</p>
                            <h3 className="text-2xl font-black theme-text-main m-0 mt-1">{formatoMoneda(kpiTotalMonto)}</h3>
                        </div>
                        <div className="p-3 rounded-2xl theme-element border theme-border">
                            <Coins className="w-6 h-6 text-emerald-500" />
                        </div>
                    </div>
                    <div className={geliaCardClass('p-6 flex items-center justify-between')}>
                        <div>
                            <p className="gelia-rh-field-label m-0 ml-0">Colaboradores con Horas Extra</p>
                            <h3 className="text-2xl font-black theme-text-main m-0 mt-1">{kpiColaboradoresConHe} colaboradores</h3>
                        </div>
                        <div className="p-3 rounded-2xl theme-element border theme-border">
                            <Users className="w-6 h-6 text-indigo-500" />
                        </div>
                    </div>
                </div>

                {/* Filtros */}
                <div className={geliaCardClass('p-6 grid grid-cols-1 md:grid-cols-4 gap-4 no-print')}>
                    <div>
                        <RhFieldLabel>Fecha inicio</RhFieldLabel>
                        <input type="date" value={localFiltros.fecha_inicio} onChange={(e) => setLocalFiltros(prev => ({ ...prev, fecha_inicio: e.target.value }))} className={`${THEME_INPUT} w-full px-4 py-3 rounded-2xl text-[11px] font-bold`} />
                    </div>
                    <div>
                        <RhFieldLabel>Fecha corte (Fin)</RhFieldLabel>
                        <input type="date" value={localFiltros.fecha_fin} onChange={(e) => setLocalFiltros(prev => ({ ...prev, fecha_fin: e.target.value }))} className={`${THEME_INPUT} w-full px-4 py-3 rounded-2xl text-[11px] font-bold`} />
                    </div>
                    <div>
                        <RhFieldLabel>Colaborador</RhFieldLabel>
                        <RhSelect value={localFiltros.rh_colaborador_id} onChange={(e) => setLocalFiltros(prev => ({ ...prev, rh_colaborador_id: e.target.value }))}>
                            <option value="">Todos</option>
                            {colaboradores.map((c) => (
                                <option key={c.id} value={c.id}>{nombreCompletoColaborador(c)}</option>
                            ))}
                        </RhSelect>
                    </div>
                    <div className="flex items-end">
                        <button type="button" onClick={aplicar} className="w-full px-4 py-3 rounded-2xl text-[10px] font-black uppercase theme-element border theme-border hover:theme-text-main flex items-center justify-center gap-2">
                            <Calendar className="w-4 h-4" /> Actualizar Filtros
                        </button>
                    </div>
                </div>

                {/* Tabla de Horas Extra */}
                <div className={geliaCardClass('overflow-hidden')}>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse">
                            <thead>
                                <tr className="border-b theme-border bg-black/5 dark:bg-white/5">
                                    <th className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Colaborador</th>
                                    <th className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Departamento / Área</th>
                                    <th className="px-4 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Horas Extras Acumuladas</th>
                                    <th className="px-4 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Total Económico Acumulado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {resumen.filas.length === 0 ? (
                                    <tr>
                                        <td colSpan="4" className="px-4 py-8 text-center text-xs theme-text-muted">
                                            No se encontraron horas extra pendientes para el periodo de corte seleccionado.
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
                                            <td className="px-4 py-4 text-right text-xs font-black theme-text-main">
                                                {fila.horas_extra_acumuladas > 0 ? `${fila.horas_extra_acumuladas} hrs` : '—'}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm font-black text-emerald-500">
                                                {fila.total_economico_acumulado > 0 ? formatoMoneda(fila.total_economico_acumulado) : '—'}
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
