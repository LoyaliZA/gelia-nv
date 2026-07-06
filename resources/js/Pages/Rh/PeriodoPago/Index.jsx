import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Calendar, ArrowLeft, Zap, Settings, Printer } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, THEME_INPUT } from '../../../utils/geliaTheme';
import { RhFieldLabel, RhSelect } from '../Partials/rhFilterFields';
import { formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import ModalVistaPreviaRecibo from '../Partials/ModalVistaPreviaRecibo';

export default function Index({ auth, resumen, comisionesAuditor, colaboradores, configuracion, filtros, puedeGenerarCuotas, puedeSellarSalidas, puedeGenerarRecibos = false }) {
    const [previewRecibo, setPreviewRecibo] = useState(null);
    const [localFiltros, setLocalFiltros] = useState({
        fecha_inicio: filtros.fecha_inicio || configuracion.periodo_actual_inicio || '',
        fecha_fin: filtros.fecha_fin || configuracion.periodo_actual_fin || '',
        dias_periodo: filtros.dias_periodo || configuracion.dias_periodo_pago || 30,
        rh_colaborador_id: filtros.rh_colaborador_id || '',
    });

    const [showConfigModal, setShowConfigModal] = useState(false);
    const [configData, setConfigData] = useState({
        fecha_inicio: configuracion.periodo_actual_inicio || '',
        fecha_fin: configuracion.periodo_actual_fin || '',
        dias_periodo: configuracion.dias_periodo_pago || 30,
    });

    const aplicar = () => {
        router.get(route('rh.periodo_pago.index'), localFiltros, { preserveState: true, preserveScroll: true });
    };

    const handleFechaInicioChange = (e) => {
        const nuevaFechaInicio = e.target.value;
        if (!nuevaFechaInicio) {
            setLocalFiltros(prev => ({ ...prev, fecha_inicio: '' }));
            return;
        }
        
        const date = new Date(nuevaFechaInicio);
        // JS Dates handle overflow automatically, but setting the date timezone correctly is important
        // We'll use local timezone math
        const [year, month, day] = nuevaFechaInicio.split('-');
        const d = new Date(year, month - 1, day);
        d.setDate(d.getDate() + Number(localFiltros.dias_periodo) - 1);
        
        const nuevaFechaFin = d.toISOString().split('T')[0];
        
        setLocalFiltros(prev => ({ 
            ...prev, 
            fecha_inicio: nuevaFechaInicio,
            fecha_fin: nuevaFechaFin
        }));
    };

    const handleDiasChange = (e) => {
        const dias = e.target.value;
        if (!dias || !localFiltros.fecha_inicio) {
            setLocalFiltros(prev => ({ ...prev, dias_periodo: dias }));
            return;
        }
        
        const [year, month, day] = localFiltros.fecha_inicio.split('-');
        const d = new Date(year, month - 1, day);
        d.setDate(d.getDate() + Number(dias) - 1);
        const nuevaFechaFin = d.toISOString().split('T')[0];
        
        setLocalFiltros(prev => ({ 
            ...prev, 
            dias_periodo: dias,
            fecha_fin: nuevaFechaFin
        }));
    };

    const handleFechaFinChange = (e) => {
        const nuevaFin = e.target.value;
        if (!nuevaFin || !localFiltros.fecha_inicio) {
            setLocalFiltros(prev => ({ ...prev, fecha_fin: nuevaFin }));
            return;
        }
        const inicio = new Date(localFiltros.fecha_inicio);
        const fin = new Date(nuevaFin);
        const diffTime = Math.abs(fin - inicio);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 because inclusive
        setLocalFiltros(prev => ({ ...prev, fecha_fin: nuevaFin, dias_periodo: diffDays }));
    };

    const generarCuotas = () => {
        if (!window.confirm('¿Generar cuotas de préstamos para el periodo visible?')) return;
        router.post(route('rh.prestamos.generar_cuotas'), {
            fecha_inicio: localFiltros.fecha_inicio,
            fecha_fin: localFiltros.fecha_fin,
        }, { preserveScroll: true });
    };

    const sellarSalidas = () => {
        const fechaPago = window.prompt('Confirme la fecha de pago para el sellado de las salidas (YYYY-MM-DD):', localFiltros.fecha_fin || new Date().toISOString().split('T')[0]);
        if (!fechaPago) return;
        router.post(route('rh.salidas_personales.sellar_periodo'), {
            fecha_inicio: localFiltros.fecha_inicio,
            fecha_fin: localFiltros.fecha_fin,
            fecha_pago: fechaPago,
        }, { preserveScroll: true });
    };

    const handleConfigFechaInicioChange = (e) => {
        const nueva = e.target.value;
        if (!nueva) {
            setConfigData(prev => ({ ...prev, fecha_inicio: '' }));
            return;
        }
        const [year, month, day] = nueva.split('-');
        const d = new Date(year, month - 1, day);
        d.setDate(d.getDate() + Number(configData.dias_periodo) - 1);
        setConfigData(prev => ({ ...prev, fecha_inicio: nueva, fecha_fin: d.toISOString().split('T')[0] }));
    };

    const handleConfigDiasChange = (e) => {
        const dias = e.target.value;
        if (!dias || !configData.fecha_inicio) {
            setConfigData(prev => ({ ...prev, dias_periodo: dias }));
            return;
        }
        const [year, month, day] = configData.fecha_inicio.split('-');
        const d = new Date(year, month - 1, day);
        d.setDate(d.getDate() + Number(dias) - 1);
        setConfigData(prev => ({ ...prev, dias_periodo: dias, fecha_fin: d.toISOString().split('T')[0] }));
    };

    const handleConfigFechaFinChange = (e) => {
        const nuevaFin = e.target.value;
        if (!nuevaFin || !configData.fecha_inicio) {
            setConfigData(prev => ({ ...prev, fecha_fin: nuevaFin }));
            return;
        }
        const inicio = new Date(configData.fecha_inicio);
        const fin = new Date(nuevaFin);
        const diffTime = Math.abs(fin - inicio);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 because inclusive
        setConfigData(prev => ({ ...prev, fecha_fin: nuevaFin, dias_periodo: diffDays }));
    };

    const guardarConfigGlobal = (e) => {
        e.preventDefault();
        router.post(route('rh.configuracion.periodo_actual.update'), configData, {
            preserveState: false,
            preserveScroll: true,
            onSuccess: () => setShowConfigModal(false),
        });
    };

    const avanzarPeriodo = () => {
        if (!window.confirm('¿Avanzar el periodo global automáticamente (basado en los días configurados)?')) return;
        router.post(route('rh.configuracion.periodo_actual.avanzar'), {}, {
            preserveState: false,
            preserveScroll: true,
            onSuccess: () => setShowConfigModal(false),
        });
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
                            Periodo en cálculo: {filtros.dias_periodo || configuracion.dias_periodo_pago} días · {resumen.fecha_inicio} al {resumen.fecha_fin}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {auth.user?.permissions?.includes('rh.configuracion.editar') || true ? (
                            <button type="button" onClick={() => setShowConfigModal(true)} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Settings className="w-4 h-4" /> Configurar Periodo de Pago actual
                            </button>
                        ) : null}
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

                <div className={geliaCardClass('p-6 grid grid-cols-1 md:grid-cols-5 gap-4')}>
                    <div>
                        <RhFieldLabel>Fecha inicio</RhFieldLabel>
                        <input type="date" value={localFiltros.fecha_inicio} onChange={handleFechaInicioChange} className={`${THEME_INPUT} w-full px-4 py-3 rounded-2xl text-[11px] font-bold`} />
                    </div>
                    <div>
                        <RhFieldLabel>Días de Periodo</RhFieldLabel>
                        <input type="number" min="1" value={localFiltros.dias_periodo} onChange={handleDiasChange} className={`${THEME_INPUT} w-full px-4 py-3 rounded-2xl text-[11px] font-bold`} />
                    </div>
                    <div>
                        <RhFieldLabel>Fecha fin</RhFieldLabel>
                        <input type="date" value={localFiltros.fecha_fin} onChange={handleFechaFinChange} className={`${THEME_INPUT} w-full px-4 py-3 rounded-2xl text-[11px] font-bold`} />
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
                        <button type="button" onClick={aplicar} className="w-full px-4 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center justify-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Calendar className="w-4 h-4" /> Actualizar
                        </button>
                    </div>
                </div>

                <div className={geliaCardClass('overflow-hidden')}>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse">
                            <thead>
                                <tr className="border-b theme-border">
                                    {['Colaborador', 'Salario mensual', 'Salario diario', 'Estimado rango', 'HE', 'Deducciones', 'Salidas Pers.', 'Préstamos activos', 'Neto estimado', puedeGenerarRecibos ? 'Recibo' : null].filter(Boolean).map((h) => (
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
                                        {puedeGenerarRecibos && (
                                            <td className="px-4 py-4">
                                                <button
                                                    type="button"
                                                    onClick={() => setPreviewRecibo({
                                                        previewUrl: `${route('rh.periodo_pago.recibo_incidencias.vista_previa', fila.colaborador.id)}?fecha_inicio=${localFiltros.fecha_inicio}&fecha_fin=${localFiltros.fecha_fin}`,
                                                        downloadUrl: `${route('rh.periodo_pago.recibo_incidencias', fila.colaborador.id)}?fecha_inicio=${localFiltros.fecha_inicio}&fecha_fin=${localFiltros.fecha_fin}`,
                                                        titulo: `Recibo periodo — ${nombreCompletoColaborador(fila.colaborador)}`,
                                                    })}
                                                    className="text-[10px] font-black uppercase inline-flex items-center gap-1 theme-text-muted"
                                                >
                                                    <Printer className="w-3.5 h-3.5" /> Periodo
                                                </button>
                                            </td>
                                        )}
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

            {showConfigModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60">
                    <form onSubmit={guardarConfigGlobal} className={`${geliaCardClass('p-6 md:p-8 w-full max-w-md')} shadow-2xl`}>
                        <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main m-0 mb-4">Configurar Periodo Global</h2>
                        <p className="text-xs theme-text-muted mb-6">Estas fechas se usarán por defecto en todas las funciones del módulo (horas extras, préstamos, nómina).</p>
                        
                        <div className="space-y-4">
                            <div>
                                <RhFieldLabel>Fecha de Inicio del Periodo</RhFieldLabel>
                                <input type="date" required value={configData.fecha_inicio} onChange={handleConfigFechaInicioChange} className={`${THEME_INPUT} w-full px-4 py-3 rounded-2xl text-xs font-bold`} />
                            </div>
                            <div>
                                <RhFieldLabel>Días de Periodo de Pago</RhFieldLabel>
                                <input type="number" min="1" required value={configData.dias_periodo} onChange={handleConfigDiasChange} className={`${THEME_INPUT} w-full px-4 py-3 rounded-2xl text-xs font-bold`} />
                            </div>
                            <div>
                                <RhFieldLabel>Fecha de Fin del Periodo</RhFieldLabel>
                                <input type="date" required value={configData.fecha_fin} onChange={handleConfigFechaFinChange} className={`${THEME_INPUT} w-full px-4 py-3 rounded-2xl text-xs font-bold`} />
                            </div>
                        </div>

                        <div className="mt-8 flex flex-col md:flex-row justify-between items-center gap-4">
                            <button type="button" onClick={avanzarPeriodo} className="w-full md:w-auto px-5 py-3 rounded-2xl text-xs font-black uppercase text-amber-500 border border-amber-500 hover:bg-amber-500/10">
                                Avanzar Automáticamente
                            </button>
                            <div className="flex gap-3 w-full md:w-auto">
                                <button type="button" onClick={() => setShowConfigModal(false)} className="flex-1 md:flex-none px-5 py-3 rounded-2xl text-xs font-bold uppercase theme-element">Cancelar</button>
                                <button type="submit" className="flex-1 md:flex-none px-5 py-3 rounded-2xl text-xs font-black text-white uppercase" style={{ backgroundColor: 'var(--color-primario)' }}>Guardar Global</button>
                            </div>
                        </div>
                    </form>
                </div>
            )}
            <ModalVistaPreviaRecibo
                abierto={!!previewRecibo}
                onCerrar={() => setPreviewRecibo(null)}
                previewUrl={previewRecibo?.previewUrl}
                downloadUrl={previewRecibo?.downloadUrl}
                titulo={previewRecibo?.titulo}
            />
        </AppLayout>
    );
}
