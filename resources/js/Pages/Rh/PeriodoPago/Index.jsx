import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Calendar, ArrowLeft, Zap } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, THEME_INPUT } from '../../../utils/geliaTheme';
import { RhFieldLabel, RhSelect } from '../Partials/rhFilterFields';
import { formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';

export default function Index({ auth, resumen, comisionesAuditor, colaboradores, configuracion, filtros, puedeGenerarCuotas, puedeSellarSalidas }) {
    const aplicar = (cambios) => {
        router.get(route('rh.periodo_pago.index'), { ...filtros, ...cambios }, { preserveState: true });
    };

    const generarCuotas = () => {
        if (!window.confirm('¿Generar cuotas de préstamos para el periodo visible?')) return;
        router.post(route('rh.prestamos.generar_cuotas'), {
            fecha_inicio: filtros.fecha_inicio,
            fecha_fin: filtros.fecha_fin,
        }, { preserveScroll: true });
    };

    const sellarSalidas = () => {
        const fechaPago = window.prompt('Confirme la fecha de pago para el sellado de las salidas (YYYY-MM-DD):', filtros.fecha_fin || new Date().toISOString().split('T')[0]);
        if (!fechaPago) return;
        router.post(route('rh.salidas_personales.sellar_periodo'), {
            fecha_inicio: filtros.fecha_inicio,
            fecha_fin: filtros.fecha_fin,
            fecha_pago: fechaPago,
        }, { preserveScroll: true });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Periodo de Pago | RH" />
            <GeliaPageShell className="space-y-6">
                <Link href={route('rh.index')} className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main">
                    <ArrowLeft className="w-4 h-4" /> Dashboard RH
                </Link>

                <header className={geliaCardClass('p-6 md:p-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-4')}>
                    <div>
                        <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0 mb-2" style={{ color: 'var(--color-primario)' }}>Nómina estimada</p>
                        <h1 className="text-2xl md:text-4xl font-black italic uppercase tracking-tighter theme-text-main m-0">Periodo de pago</h1>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                            Periodo configurado: {configuracion.dias_periodo_pago} días · {resumen.fecha_inicio} al {resumen.fecha_fin}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {puedeGenerarCuotas && (
                            <button type="button" onClick={generarCuotas} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase theme-element theme-border border flex items-center gap-2">
                                <Zap className="w-4 h-4" /> Generar cuotas del periodo
                            </button>
                        )}
                        {puedeSellarSalidas && (
                            <button type="button" onClick={sellarSalidas} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase theme-element theme-border border flex items-center gap-2" style={{ borderColor: 'var(--color-primario)', color: 'var(--color-primario)' }}>
                                <Calendar className="w-4 h-4" /> Sellar salidas de periodo
                            </button>
                        )}
                    </div>
                </header>

                <RhSubNav />

                <div className={geliaCardClass('p-6 grid grid-cols-1 md:grid-cols-4 gap-4')}>
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

                <div className={geliaCardClass('overflow-hidden')}>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse">
                            <thead>
                                <tr className="border-b theme-border">
                                    {['Colaborador', 'Salario mensual', 'Salario diario', 'Estimado rango', 'HE', 'Deducciones', 'Salidas Pers.', 'Préstamos activos', 'Neto estimado'].map((h) => (
                                        <th key={h} className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {resumen.filas.map((fila) => (
                                    <tr key={fila.colaborador.id} className="border-b theme-border last:border-0">
                                        <td className="px-4 py-4 text-sm font-bold">{nombreCompletoColaborador(fila.colaborador)}</td>
                                        <td className="px-4 py-4 text-xs">{formatoMoneda(fila.salario_mensual)}</td>
                                        <td className="px-4 py-4 text-xs">{formatoMoneda(fila.salario_diario)}</td>
                                        <td className="px-4 py-4 text-xs">{formatoMoneda(fila.salario_rango_estimado)} + {formatoMoneda(fila.bonos_rango_estimado)} bonos</td>
                                        <td className="px-4 py-4 text-xs">{formatoMoneda(fila.horas_extra_total)}</td>
                                        <td className="px-4 py-4 text-xs text-red-500">-{formatoMoneda(fila.deducciones_total)}</td>
                                        <td className="px-4 py-4 text-xs text-red-500 font-medium">
                                            -{formatoMoneda(fila.salidas_deduccion_total || 0)}
                                            {fila.salidas_deduccion_pendiente > 0 && (
                                                <span className="block text-[9px] text-amber-500 font-bold">
                                                    ({formatoMoneda(fila.salidas_deduccion_pendiente)} pend.)
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-4 text-xs text-amber-600">{formatoMoneda(fila.prestamos_activos_cuota || 0)}</td>
                                        <td className="px-4 py-4 text-sm font-bold">{formatoMoneda(fila.neto_estimado)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className={geliaCardClass('p-6')}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0 mb-3">Comisiones auditoras en periodo</h2>
                    <p className="text-sm m-0">Total: {formatoMoneda(comisionesAuditor.total)} · Pendiente: {formatoMoneda(comisionesAuditor.pendiente)}</p>
                </div>
            </GeliaPageShell>
        </AppLayout>
    );
}
