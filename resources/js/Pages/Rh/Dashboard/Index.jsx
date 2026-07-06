import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Clock, Plus, AlertTriangle, BookOpen, Users, FileDown } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoDeduccionEntera, formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import RhPageHeader from '../Partials/RhPageHeader';
import ModalAyudaFactores from '../Partials/ModalAyudaFactores';
import {
    RH_ESTADO_DEDUCCION_BADGE,
    RH_ESTADO_DEDUCCION_LABELS,
    RH_ESTADO_PAGO_BADGE,
    RH_ESTADO_PAGO_LABELS,
} from '../rhModuleStyles';

const PANEL_HEADER_CLASS = 'p-6 md:p-8 border-b theme-border flex flex-wrap justify-between items-center gap-4';
const PANEL_ROW_CLASS =
    'flex flex-wrap items-center justify-between gap-4 p-5 md:p-6 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors';

export default function Index({
    auth,
    metricas,
    configuracion,
    ultimosRegistros = [],
    ultimasIncidencias = [],
    puedeHorasExtra,
    puedeIncidencias,
    puedeCrearHe,
    puedeCrearIncidencia,
}) {
    const kpis = [
        { label: 'Colaboradores activos', value: metricas.colaboradores_activos, accent: true },
        { label: 'Registros HE hoy', value: metricas.registros_hoy, color: '#3b82f6' },
        { label: 'Deducciones hoy', value: metricas.deducciones_hoy ?? metricas.incidencias_hoy, color: '#8b5cf6' },
        { label: 'Deducciones pendientes', value: formatoDeduccionEntera(metricas.monto_deduccion_pendiente), color: '#ef4444' },
    ];

    const tieneAccionesRapidas = puedeCrearHe || puedeCrearIncidencia;

    return (
        <AppLayout auth={auth}>
            <Head title="Recursos Humanos" />
            <GeliaPageShell className="space-y-8 relative">
                <RhPageHeader
                    title="Recursos"
                    titleHighlight="Humanos"
                    description={`Periodo de pago: ${configuracion.dias_periodo_pago} días${metricas.programadas_proximas > 0 ? ` · ${metricas.programadas_proximas} pagos programados próximos 7 días` : ''}`}
                    icon={Users}
                    showGuia={false}
                />

                <RhSubNav />

                <section aria-label="Indicadores" className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 md:gap-8">
                    {kpis.map(({ label, value, color, accent }) => (
                        <div key={label} className={geliaCardClass('p-6 md:p-8 lg:p-10 min-h-[7.5rem] flex flex-col justify-center')}>
                            <p className="gelia-rh-field-label ml-0 mb-3 md:mb-4 m-0">
                                {label}
                            </p>
                            <p
                                className="text-3xl md:text-4xl font-black italic m-0 leading-none"
                                style={accent ? { color: 'var(--color-primario)' } : { color: color || 'inherit' }}
                            >
                                {value}
                            </p>
                        </div>
                    ))}
                </section>

                <div className="grid grid-cols-1 xl:grid-cols-12 gap-6 md:gap-8 items-start">
                    <div className="xl:col-span-8 space-y-6 md:space-y-8 min-w-0">
                        <div className={geliaCardClass('overflow-hidden')}>
                            <div className={PANEL_HEADER_CLASS}>
                                <h2 className="text-sm md:text-base font-black uppercase tracking-widest theme-text-main m-0">
                                    Últimos registros HE
                                </h2>
                                {puedeHorasExtra && (
                                    <Link
                                        href={route('rh.horas_extra.index')}
                                        className="text-[10px] font-black uppercase shrink-0"
                                        style={{ color: 'var(--color-primario)' }}
                                    >
                                        Ver todos
                                    </Link>
                                )}
                            </div>
                            {ultimosRegistros.length === 0 ? (
                                <div className="p-10 md:p-12 text-center">
                                    <Clock className="w-10 h-10 mx-auto mb-4 opacity-40 theme-text-muted" />
                                    <p className="text-sm theme-text-muted italic m-0">Sin registros de horas extra aún.</p>
                                </div>
                            ) : (
                                <div className="divide-y theme-border">
                                    {ultimosRegistros.map((reg) => (
                                        <Link
                                            key={reg.id}
                                            href={route('rh.horas_extra.show', reg.id)}
                                            className={PANEL_ROW_CLASS}
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="text-xs font-mono font-bold theme-text-main m-0">{reg.folio}</p>
                                                <p className="text-sm md:text-base font-bold theme-text-main m-0 mt-1">
                                                    {nombreCompletoColaborador(reg.colaborador)}
                                                </p>
                                                <p className="text-[10px] theme-text-muted m-0 mt-1">{reg.fecha_turno}</p>
                                            </div>
                                            <div className="text-right shrink-0">
                                                <p className="text-sm md:text-base font-bold theme-text-main m-0">{formatoMoneda(reg.total_economico)}</p>
                                                <p className="text-[10px] theme-text-muted m-0 mt-0.5">{reg.horas_extra_a_pagar} hr(s) extra</p>
                                                <span
                                                    className={`inline-block mt-2 px-2.5 py-1 rounded-lg text-[9px] font-black uppercase border ${RH_ESTADO_PAGO_BADGE[reg.estado_pago] || ''}`}
                                                >
                                                    {RH_ESTADO_PAGO_LABELS[reg.estado_pago] || reg.estado_pago}
                                                </span>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className={geliaCardClass('overflow-hidden')}>
                            <div className={PANEL_HEADER_CLASS}>
                                <h2 className="text-sm md:text-base font-black uppercase tracking-widest theme-text-main m-0">
                                    Últimas incidencias
                                </h2>
                                {puedeIncidencias && (
                                    <div className="flex flex-wrap gap-3 shrink-0">
                                        <Link
                                            href={route('rh.deducciones.incidencias.index')}
                                            className="text-[10px] font-black uppercase"
                                            style={{ color: 'var(--color-primario)' }}
                                        >
                                            Ver todas
                                        </Link>
                                        <Link
                                            href={route('rh.deducciones.pagos_pendientes.index')}
                                            className="text-[10px] font-black uppercase theme-text-muted hover:theme-text-main"
                                        >
                                            Ver pendientes
                                        </Link>
                                    </div>
                                )}
                            </div>
                            {ultimasIncidencias.length === 0 ? (
                                <div className="p-10 md:p-12 text-center">
                                    <AlertTriangle className="w-10 h-10 mx-auto mb-4 opacity-40 theme-text-muted" />
                                    <p className="text-sm theme-text-muted italic m-0">Sin incidencias registradas aún.</p>
                                </div>
                            ) : (
                                <div className="divide-y theme-border">
                                    {ultimasIncidencias.map((reg) => (
                                        <Link
                                            key={reg.id}
                                            href={route('rh.deducciones.show', reg.id)}
                                            className={PANEL_ROW_CLASS}
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="text-xs font-mono font-bold theme-text-main m-0">{reg.folio}</p>
                                                <p className="text-sm md:text-base font-bold theme-text-main m-0 mt-1">
                                                    {nombreCompletoColaborador(reg.colaborador)}
                                                </p>
                                                <p className="text-[10px] theme-text-muted m-0 mt-1 line-clamp-2">
                                                    {reg.regla_nombre_snapshot || reg.regla_incidencia?.nombre}
                                                </p>
                                            </div>
                                            <div className="text-right shrink-0">
                                                <p className="text-sm md:text-base font-bold theme-text-main m-0">
                                                    {formatoDeduccionEntera(reg.total_deduccion)}
                                                </p>
                                                <p className="text-[10px] theme-text-muted m-0 mt-0.5">
                                                    {reg.fecha_ocurrencia?.slice?.(0, 10) || reg.fecha_ocurrencia}
                                                </p>
                                                <span
                                                    className={`inline-block mt-2 px-2.5 py-1 rounded-lg text-[9px] font-black uppercase border ${RH_ESTADO_DEDUCCION_BADGE[reg.estado_deduccion] || ''}`}
                                                >
                                                    {RH_ESTADO_DEDUCCION_LABELS[reg.estado_deduccion] || reg.estado_deduccion}
                                                </span>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    <aside className="xl:col-span-4 min-w-0 xl:sticky xl:top-6">
                        <div className={geliaCardClass('p-6 md:p-8 space-y-6')}>
                            <div className="space-y-2">
                                <div className="flex items-center gap-3">
                                    <span
                                        className="h-1 w-8 rounded-full shrink-0"
                                        style={{ backgroundColor: 'var(--color-primario)' }}
                                        aria-hidden
                                    />
                                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                                        Acciones rápidas
                                    </h2>
                                </div>
                                <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest m-0 pl-11">
                                    Captura y consulta inmediata
                                </p>
                            </div>

                            <div className="flex flex-col gap-4">
                                {puedeCrearHe && (
                                    <Link
                                        href={route('rh.horas_extra.index')}
                                        className="flex items-center justify-center gap-3 w-full py-4 px-5 rounded-2xl text-[10px] font-black uppercase tracking-widest text-white shadow-md hover:shadow-lg transition-shadow"
                                        style={{ backgroundColor: 'var(--color-primario)' }}
                                    >
                                        <Plus className="w-4 h-4 shrink-0" />
                                        Nuevo registro de horas extra
                                    </Link>
                                )}
                                {puedeCrearIncidencia && (
                                    <Link
                                        href={route('rh.deducciones.index')}
                                        className="flex items-center justify-center gap-3 w-full py-4 px-5 rounded-2xl text-[10px] font-black uppercase tracking-widest theme-element theme-border border-2 hover:border-[var(--color-primario)] transition-colors"
                                    >
                                        <Plus className="w-4 h-4 shrink-0" />
                                        Nueva incidencia
                                    </Link>
                                )}
                                <ModalAyudaFactores variant="card" />
                                {!tieneAccionesRapidas && (
                                    <p className="text-[10px] font-bold theme-text-muted italic text-center m-0 py-2">
                                        No tienes permisos de captura directa. Usa la navegación superior para consultar módulos.
                                    </p>
                                )}
                            </div>

                            <div className={`${geliaCardClass('p-4 md:p-5')} border-dashed mb-4`}>
                                <div className="flex items-start gap-3">
                                    <BookOpen className="w-4 h-4 shrink-0 mt-0.5" style={{ color: 'var(--color-primario)' }} />
                                    <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest leading-relaxed m-0">
                                        Los accesos a colaboradores, deducciones y configuración están en la barra superior del módulo.
                                    </p>
                                </div>
                            </div>
                            
                            <a
                                href={route('rh.descargar_manual')}
                                target="_blank"
                                rel="noreferrer"
                                className="flex items-center justify-center gap-3 w-full py-4 px-5 rounded-2xl text-[10px] font-black uppercase tracking-widest theme-element theme-border border-2 hover:border-[var(--color-primario)] transition-colors"
                            >
                                <FileDown className="w-4 h-4 shrink-0" />
                                Descargar Manual RH (PDF)
                            </a>
                        </div>
                    </aside>
                </div>
            </GeliaPageShell>
        </AppLayout>
    );
}
