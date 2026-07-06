import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft, User, Building2, Briefcase, DollarSign, Link2, Pencil, Copy, Check, AlertTriangle, Wallet,
} from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoDeduccionEntera, formatoMoneda, formatoDecimal, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import ModalFormColaborador from './Partials/ModalFormColaborador';

export default function Show({
    auth,
    colaborador,
    incidencias = [],
    prestamosActivos = [],
    puedeVerIncidencias,
    puedeVerPrestamos,
    departamentos,
    puestos,
    turnos = [],
    usuarios,
    gerentes = [],
    configuracion,
    puedeEditar,
    puedeVincular,
}) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [uuidCopiado, setUuidCopiado] = useState(false);

    const copiarUuid = async () => {
        try {
            await navigator.clipboard.writeText(colaborador.uuid);
            setUuidCopiado(true);
            setTimeout(() => setUuidCopiado(false), 2000);
        } catch {
            // ignore
        }
    };

    return (
        <AppLayout auth={auth}>
            <Head title={`${colaborador.folio} | RH Colaborador`} />
            <GeliaPageShell className="max-w-[1000px] space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Link href={route('rh.colaboradores.index')} className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main">
                        <ArrowLeft className="w-4 h-4" /> Volver al listado
                    </Link>
                    {puedeEditar && (
                        <button
                            type="button"
                            onClick={() => setModalAbierto(true)}
                            className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center gap-2"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            <Pencil className="w-4 h-4" /> Editar perfil
                        </button>
                    )}
                </div>

                <RhSubNav />

                <header className={geliaCardClass('p-6 md:p-10')}>
                    <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0 mb-2" style={{ color: 'var(--color-primario)' }}>Perfil Laboral</p>
                    <h1 className="text-2xl md:text-4xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                        {nombreCompletoColaborador(colaborador)}
                    </h1>
                    <div className="flex flex-wrap gap-4 mt-4">
                        <div>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Folio</p>
                            <p className="text-sm font-mono font-bold theme-text-main m-0">{colaborador.folio}</p>
                        </div>
                        <div>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">UUID</p>
                            <button type="button" onClick={copiarUuid} className="text-xs font-mono theme-text-main flex items-center gap-2">
                                {colaborador.uuid}
                                {uuidCopiado ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                            </button>
                        </div>
                        <div>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Estado</p>
                            <span className={`text-[10px] font-black uppercase ${colaborador.activo ? 'text-emerald-500' : 'text-red-500'}`}>
                                {colaborador.activo ? 'Activo' : 'Inactivo'}
                            </span>
                        </div>
                    </div>
                </header>

                <section className={geliaCardClass('p-6 md:p-8 space-y-4')}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2 m-0">
                        <Building2 className="w-4 h-4 text-purple-500" /> Organización
                    </h2>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <InfoItem label="Departamento" value={colaborador.departamento?.nombre} />
                        <InfoItem label="Área" value={colaborador.area?.nombre || '—'} />
                        <InfoItem label="Puesto / Cargo" value={colaborador.puesto?.nombre} icon={Briefcase} />
                    </div>
                </section>

                <section className={geliaCardClass('p-6 md:p-8 space-y-4')}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2 m-0">
                        <DollarSign className="w-4 h-4 text-emerald-500" /> Compensación
                    </h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <InfoItem label="Salario Base" value={formatoMoneda(colaborador.salario_base)} />
                        <InfoItem label="Bono Productividad" value={formatoMoneda(colaborador.bono_productividad)} />
                        <InfoItem label="Bono Puntualidad" value={formatoMoneda(colaborador.bono_puntualidad)} />
                        <InfoItem label="Horas Laboradas Oficiales" value={`${colaborador.horas_laboradas_oficiales} h`} />
                    </div>
                    <div className="pt-4 border-t theme-border grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <InfoItem label="Salario del Periodo" value={formatoMoneda(colaborador.salario_diario * configuracion.dias_periodo_pago)} highlight />
                        <InfoItem label="Salario Mensual" value={formatoMoneda(colaborador.salario_diario * 30)} highlight />
                        <InfoItem label="Salario Diario" value={formatoMoneda(colaborador.salario_diario)} highlight />
                        <InfoItem label="Bono Prod. Diario" value={formatoMoneda(colaborador.bono_productividad_diario)} highlight />
                        <InfoItem label="Bono Punt. Diario" value={formatoMoneda(colaborador.bono_puntualidad_diario)} highlight />
                        <InfoItem label="Salario por Hora" value={formatoMoneda(colaborador.salario_por_hora)} highlight />
                        <InfoItem label="Salario por Minuto" value={formatoDecimal(colaborador.salario_por_minuto, configuracion.decimales_salario_minuto || 8)} highlight />
                    </div>
                    <p className="text-[9px] font-bold theme-text-muted uppercase tracking-widest m-0">
                        Cálculos basados en periodo de {configuracion.dias_periodo_pago} días
                    </p>
                </section>

                {puedeVerIncidencias && (
                    <section className={geliaCardClass('overflow-hidden')}>
                        <div className="p-6 border-b theme-border flex justify-between items-center">
                            <h2 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2 m-0">
                                <AlertTriangle className="w-4 h-4 text-amber-500" /> Incidencias recientes
                            </h2>
                            <Link
                                href={route('rh.deducciones.index', { rh_colaborador_id: colaborador.id })}
                                className="text-[10px] font-black uppercase"
                                style={{ color: 'var(--color-primario)' }}
                            >
                                Ver todas
                            </Link>
                        </div>
                        {incidencias.length === 0 ? (
                            <p className="p-6 text-sm theme-text-muted italic m-0">Sin incidencias registradas.</p>
                        ) : (
                            <div className="divide-y theme-border">
                                {incidencias.map((inc) => (
                                    <Link
                                        key={inc.id}
                                        href={route('rh.deducciones.show', inc.id)}
                                        className="flex flex-wrap justify-between gap-3 p-4 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]"
                                    >
                                        <div>
                                            <p className="text-xs font-mono font-bold theme-text-main m-0">{inc.folio}</p>
                                            <p className="text-[10px] theme-text-muted m-0 mt-0.5">{inc.regla_nombre_snapshot || inc.regla_incidencia?.nombre}</p>
                                            <p className="text-[10px] theme-text-muted m-0">{inc.fecha_ocurrencia?.slice?.(0, 10)}</p>
                                        </div>
                                        <p className="text-sm font-bold theme-text-main m-0">{formatoDeduccionEntera(inc.total_deduccion)}</p>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </section>
                )}

                {puedeVerPrestamos && (
                    <section className={geliaCardClass('overflow-hidden')}>
                        <div className="p-6 border-b theme-border flex justify-between items-center">
                            <h2 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2 m-0">
                                <Wallet className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Convenios activos
                            </h2>
                            <Link href={route('rh.prestamos.index', { rh_colaborador_id: colaborador.id })} className="text-[10px] font-black uppercase" style={{ color: 'var(--color-primario)' }}>
                                Ver todos
                            </Link>
                        </div>
                        {prestamosActivos.length === 0 ? (
                            <p className="p-6 text-sm theme-text-muted italic m-0">Sin convenios activos o pausados.</p>
                        ) : (
                            <div className="divide-y theme-border">
                                {prestamosActivos.map((pre) => (
                                    <Link
                                        key={pre.id}
                                        href={route('rh.prestamos.show', pre.id)}
                                        className="flex flex-wrap justify-between gap-3 p-4 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]"
                                    >
                                        <div>
                                            <p className="text-xs font-mono font-bold theme-text-main m-0">{pre.folio}</p>
                                            <p className="text-[10px] theme-text-muted m-0 mt-0.5">{pre.concepto}</p>
                                            <p className="text-[10px] theme-text-muted m-0 capitalize">{pre.estado} · {pre.modalidad?.replace('_', ' ')}</p>
                                        </div>
                                        <p className="text-sm font-bold theme-text-main m-0">{formatoMoneda(pre.monto_cuota)} / periodo</p>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </section>
                )}

                <section className={geliaCardClass('p-6 md:p-8 space-y-4')}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2 m-0">
                        <Link2 className="w-4 h-4 text-blue-500" /> Cuenta del Sistema
                    </h2>
                    {colaborador.usuario ? (
                        <div>
                            <p className="text-sm font-bold theme-text-main m-0">{colaborador.usuario.name} {colaborador.usuario.apellido_paterno || ''}</p>
                            <p className="text-xs theme-text-muted m-0 mt-1">{colaborador.usuario.email}</p>
                        </div>
                    ) : (
                        <p className="text-sm theme-text-muted italic m-0">Este colaborador no tiene cuenta vinculada en el sistema.</p>
                    )}
                </section>
            </GeliaPageShell>

            <ModalFormColaborador
                abierto={modalAbierto}
                onCerrar={() => setModalAbierto(false)}
                colaborador={colaborador}
                departamentos={departamentos}
                puestos={puestos}
                turnos={turnos}
                usuarios={usuarios}
                gerentes={gerentes}
                configuracion={configuracion}
                puedeVincular={puedeVincular}
            />
        </AppLayout>
    );
}

function InfoItem({ label, value, highlight = false, icon: Icon = User }) {
    return (
        <div className={`p-4 rounded-2xl border theme-border ${highlight ? 'bg-black/[0.02] dark:bg-white/[0.02]' : 'theme-element'}`}>
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1 flex items-center gap-1">
                {Icon !== User && <Icon className="w-3 h-3" />} {label}
            </p>
            <p className={`m-0 ${highlight ? 'text-base font-bold' : 'text-sm font-bold'} theme-text-main`}>{value || '—'}</p>
        </div>
    );
}
