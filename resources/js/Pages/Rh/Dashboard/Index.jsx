import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Users, Clock, Settings, Plus, ArrowRight, AlertTriangle } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoDeduccionEntera, formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import { ESTADO_PAGO_BADGE, ESTADO_PAGO_LABELS } from '../HorasExtra/Partials/horasExtraStyles';
import { ESTADO_DEDUCCION_BADGE, ESTADO_DEDUCCION_LABELS } from '../Deducciones/Partials/deduccionesStyles';

export default function Index({
    auth,
    metricas,
    configuracion,
    ultimosRegistros = [],
    ultimasIncidencias = [],
    puedeColaboradores,
    puedeHorasExtra,
    puedeIncidencias,
    puedeConfigurar,
    puedeCrearHe,
    puedeCrearIncidencia,
}) {
    const kpis = [
        { label: 'Colaboradores activos', value: metricas.colaboradores_activos, accent: true },
        { label: 'Registros HE hoy', value: metricas.registros_hoy, color: '#3b82f6' },
        { label: 'Deducciones hoy', value: metricas.deducciones_hoy ?? metricas.incidencias_hoy, color: '#8b5cf6' },
        { label: 'Deducciones pendientes', value: formatoDeduccionEntera(metricas.monto_deduccion_pendiente), color: '#ef4444' },
    ];

    const accesos = [
        puedeColaboradores && { titulo: 'Colaboradores', desc: 'Perfiles laborales y nómina base', href: route('rh.colaboradores.index'), icon: Users },
        puedeHorasExtra && { titulo: 'Horas Extra', desc: 'Captura y cálculo de tiempo adicional', href: route('rh.horas_extra.index'), icon: Clock },
        puedeIncidencias && { titulo: 'Deducciones', desc: 'Reporte integral y expediente digital', href: route('rh.deducciones.index'), icon: AlertTriangle },
        puedeConfigurar && { titulo: 'Configuración', desc: 'Folios, periodo de pago y catálogos', href: route('rh.configuracion'), icon: Settings },
    ].filter(Boolean);

    return (
        <AppLayout auth={auth}>
            <Head title="Recursos Humanos" />
            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                <header className={geliaCardClass('p-6 md:p-10')}>
                    <div className="flex items-center gap-3 mb-2">
                        <span className="h-1.5 w-12 rounded-full shrink-0" style={{ backgroundColor: 'var(--color-primario)' }} />
                        <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0" style={{ color: 'var(--color-primario)' }}>Módulo RH</p>
                    </div>
                    <h1 className="text-2xl sm:text-3xl md:text-5xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                        Recursos <span style={{ color: 'var(--color-primario)' }}>Humanos</span>
                    </h1>
                    <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                        Periodo de pago: {configuracion.dias_periodo_pago} días
                        {metricas.programadas_proximas > 0 && ` · ${metricas.programadas_proximas} pagos programados próximos 7 días`}
                    </p>
                </header>

                <RhSubNav />

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
                    {kpis.map(({ label, value, color, accent }) => (
                        <div key={label} className={geliaCardClass('p-5 md:p-6')}>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2">{label}</p>
                            <p
                                className="text-2xl md:text-3xl font-black italic m-0"
                                style={accent ? { color: 'var(--color-primario)' } : { color: color || 'inherit' }}
                            >
                                {value}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-1 space-y-4">
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Accesos rápidos</h2>
                        {accesos.map((item) => {
                            const Icon = item.icon;
                            return (
                                <Link
                                    key={item.titulo}
                                    href={item.href}
                                    className={`${geliaCardClass('p-5 flex items-center gap-4 hover:scale-[1.01] transition-transform block')}`}
                                >
                                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                                        <Icon className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-black uppercase italic theme-text-main m-0">{item.titulo}</p>
                                        <p className="text-[10px] theme-text-muted m-0 mt-0.5">{item.desc}</p>
                                    </div>
                                    <ArrowRight className="w-4 h-4 theme-text-muted shrink-0" />
                                </Link>
                            );
                        })}
                        {puedeCrearHe && (
                            <Link
                                href={route('rh.horas_extra.index')}
                                className="flex items-center justify-center gap-2 w-full py-3 rounded-2xl text-[10px] font-black uppercase text-white"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Plus className="w-4 h-4" /> Nuevo registro de horas extra
                            </Link>
                        )}
                        {puedeCrearIncidencia && (
                            <Link
                                href={route('rh.deducciones.index')}
                                className="flex items-center justify-center gap-2 w-full py-3 rounded-2xl text-[10px] font-black uppercase theme-element theme-border border"
                            >
                                <Plus className="w-4 h-4" /> Nueva incidencia
                            </Link>
                        )}
                    </div>

                    <div className="lg:col-span-2 space-y-6">
                    <div className={geliaCardClass('overflow-hidden')}>
                        <div className="p-6 border-b theme-border flex justify-between items-center">
                            <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Últimos registros HE</h2>
                            {puedeHorasExtra && (
                                <Link href={route('rh.horas_extra.index')} className="text-[10px] font-black uppercase" style={{ color: 'var(--color-primario)' }}>
                                    Ver todos
                                </Link>
                            )}
                        </div>
                        {ultimosRegistros.length === 0 ? (
                            <div className="p-10 text-center">
                                <Clock className="w-10 h-10 mx-auto mb-3 opacity-40 theme-text-muted" />
                                <p className="text-sm theme-text-muted italic m-0">Sin registros de horas extra aún.</p>
                            </div>
                        ) : (
                            <div className="divide-y theme-border">
                                {ultimosRegistros.map((reg) => (
                                    <Link
                                        key={reg.id}
                                        href={route('rh.horas_extra.show', reg.id)}
                                        className="flex flex-wrap items-center justify-between gap-3 p-4 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors"
                                    >
                                        <div className="min-w-0">
                                            <p className="text-xs font-mono font-bold theme-text-main m-0">{reg.folio}</p>
                                            <p className="text-sm font-bold theme-text-main m-0 mt-0.5">
                                                {nombreCompletoColaborador(reg.colaborador)}
                                            </p>
                                            <p className="text-[10px] theme-text-muted m-0">{reg.fecha_turno}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-bold theme-text-main m-0">{formatoMoneda(reg.total_economico)}</p>
                                            <p className="text-[10px] theme-text-muted m-0">{reg.horas_extra_a_pagar} hr(s) extra</p>
                                            <span className={`inline-block mt-1 px-2 py-0.5 rounded-lg text-[9px] font-black uppercase border ${ESTADO_PAGO_BADGE[reg.estado_pago] || ''}`}>
                                                {ESTADO_PAGO_LABELS[reg.estado_pago] || reg.estado_pago}
                                            </span>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className={geliaCardClass('overflow-hidden')}>
                        <div className="p-6 border-b theme-border flex justify-between items-center">
                            <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Últimas incidencias</h2>
                            {puedeIncidencias && (
                                <Link href={route('rh.incidencias.index')} className="text-[10px] font-black uppercase" style={{ color: 'var(--color-primario)' }}>
                                    Ver todas
                                </Link>
                            )}
                        </div>
                        {ultimasIncidencias.length === 0 ? (
                            <div className="p-10 text-center">
                                <AlertTriangle className="w-10 h-10 mx-auto mb-3 opacity-40 theme-text-muted" />
                                <p className="text-sm theme-text-muted italic m-0">Sin incidencias registradas aún.</p>
                            </div>
                        ) : (
                            <div className="divide-y theme-border">
                                {ultimasIncidencias.map((reg) => (
                                    <Link
                                        key={reg.id}
                                        href={route('rh.deducciones.show', reg.id)}
                                        className="flex flex-wrap items-center justify-between gap-3 p-4 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors"
                                    >
                                        <div className="min-w-0">
                                            <p className="text-xs font-mono font-bold theme-text-main m-0">{reg.folio}</p>
                                            <p className="text-sm font-bold theme-text-main m-0 mt-0.5">
                                                {nombreCompletoColaborador(reg.colaborador)}
                                            </p>
                                            <p className="text-[10px] theme-text-muted m-0">{reg.regla_nombre_snapshot || reg.regla_incidencia?.nombre}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-bold theme-text-main m-0">{formatoDeduccionEntera(reg.total_deduccion)}</p>
                                            <p className="text-[10px] theme-text-muted m-0">{reg.fecha_ocurrencia?.slice?.(0, 10) || reg.fecha_ocurrencia}</p>
                                            <span className={`inline-block mt-1 px-2 py-0.5 rounded-lg text-[9px] font-black uppercase border ${ESTADO_DEDUCCION_BADGE[reg.estado_deduccion] || ''}`}>
                                                {ESTADO_DEDUCCION_LABELS[reg.estado_deduccion] || reg.estado_deduccion}
                                            </span>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
