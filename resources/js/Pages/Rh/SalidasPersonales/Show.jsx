import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Clock, Pencil, Camera } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import ModalFormSalidaPersonal from './Partials/ModalFormSalidaPersonal';
import { getSalidaStatusInfo } from './Partials/salidasStyles';

export default function Show({
    auth,
    registro,
    puedeEditar,
    colaboradores,
}) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const statusInfo = getSalidaStatusInfo(registro);

    return (
        <AppLayout auth={auth}>
            <Head title={`${registro.folio} | Salida Personal`} />
            <div className="max-w-[1000px] mx-auto p-4 md:p-8 space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Link href={route('rh.salidas_personales.index')} className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main">
                        <ArrowLeft className="w-4 h-4" /> Volver al listado
                    </Link>
                    {puedeEditar && (
                        <button type="button" onClick={() => setModalAbierto(true)} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Pencil className="w-4 h-4" /> {!registro.hora_regreso ? 'Registrar Regreso' : 'Editar Registro'}
                        </button>
                    )}
                </div>

                <RhSubNav />

                <header className={geliaCardClass('p-6 md:p-10')}>
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0 mb-2" style={{ color: 'var(--color-primario)' }}>Salida Personal</p>
                            <h1 className="text-2xl md:text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">{registro.folio}</h1>
                            <p className="text-sm font-bold theme-text-main mt-2 m-0">{nombreCompletoColaborador(registro.colaborador)}</p>
                            <p className="text-xs font-mono theme-text-muted m-0 mt-1">{registro.uuid}</p>
                        </div>
                        <span className={`px-3 py-1.5 rounded-xl text-[10px] font-black uppercase border ${statusInfo.badgeClass}`}>
                            {statusInfo.label}
                        </span>
                    </div>
                </header>

                <section className={geliaCardClass('p-6 md:p-8 grid grid-cols-1 md:grid-cols-2 gap-4')}>
                    <Info label="Fecha evento" value={registro.fecha_evento?.slice?.(0, 10) || registro.fecha_evento} />
                    <Info label="Motivo / Tipo de salida" value={registro.motivo || '—'} />
                    <Info label="Hora salida" value={registro.hora_salida ? registro.hora_salida.slice(0, 5) : '—'} />
                    <Info label="Hora regreso" value={registro.hora_regreso ? registro.hora_regreso.slice(0, 5) : 'Pendiente reingreso'} />
                    <Info label="Departamento" value={registro.colaborador?.departamento?.nombre || '—'} />
                    <Info label="Área" value={registro.colaborador?.area?.nombre || '—'} />
                    <Info label="Registrado por" value={registro.registrado_por?.name || '—'} />
                    <Info label="Fecha cobro nómina" value={registro.fecha_deduccion_nomina || 'Pendiente de nómina'} />
                </section>

                <section className={geliaCardClass('p-6 md:p-8')}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 m-0">
                        <Clock className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Desglose de Deducción
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <Info label="Minutos ausente" value={`${registro.minutos_ausente || 0} min`} />
                        <Info label="Salario por minuto (snapshot)" value={formatoMoneda(registro.salario_por_minuto_snapshot || 0, 4)} />
                        <Info label="Total deducido" value={formatoMoneda(registro.monto_a_deducir || 0)} highlight />
                    </div>
                    {registro.minutos_ausente > 0 && (
                        <p className="text-[9px] theme-text-muted uppercase tracking-widest mt-4 m-0">
                            Fórmula: {registro.minutos_ausente} min × {formatoMoneda(registro.salario_por_minuto_snapshot || 0, 4)} por minuto (redondeado a entero)
                        </p>
                    )}
                </section>

                <section className={geliaCardClass('p-6 md:p-8')}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 m-0">
                        <Camera className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Evidencias Fotográficas
                    </h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2">Salida registrada</p>
                            {registro.foto_salida_url ? (
                                <div className="relative group rounded-2xl overflow-hidden border theme-border h-64 bg-black/[0.04] dark:bg-white/[0.04]">
                                    <img
                                        src={registro.foto_salida_url}
                                        alt="Evidencia Salida"
                                        className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105 cursor-pointer"
                                        onClick={() => window.open(registro.foto_salida_url, '_blank')}
                                    />
                                    <div className="absolute bottom-0 inset-x-0 p-3 bg-gradient-to-t from-black/80 to-transparent text-white text-[9px] font-black uppercase tracking-wider">
                                        Clic para ampliar
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed theme-border h-64 theme-element p-6 text-center">
                                    <Camera className="w-8 h-8 opacity-40 theme-text-muted mb-2" />
                                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Sin fotografía</p>
                                </div>
                            )}
                        </div>

                        <div>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2">Reingreso registrado</p>
                            {registro.foto_regreso_url ? (
                                <div className="relative group rounded-2xl overflow-hidden border theme-border h-64 bg-black/[0.04] dark:bg-white/[0.04]">
                                    <img
                                        src={registro.foto_regreso_url}
                                        alt="Evidencia Regreso"
                                        className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105 cursor-pointer"
                                        onClick={() => window.open(registro.foto_regreso_url, '_blank')}
                                    />
                                    <div className="absolute bottom-0 inset-x-0 p-3 bg-gradient-to-t from-black/80 to-transparent text-white text-[9px] font-black uppercase tracking-wider">
                                        Clic para ampliar
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed theme-border h-64 theme-element p-6 text-center">
                                    <Clock className="w-8 h-8 opacity-40 theme-text-muted mb-2 animate-pulse" />
                                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Reingreso pendiente</p>
                                    <p className="text-[9px] theme-text-muted m-0 mt-1">El colaborador se encuentra fuera de las instalaciones.</p>
                                </div>
                            )}
                        </div>
                    </div>
                </section>
            </div>

            <ModalFormSalidaPersonal
                abierto={modalAbierto}
                onCerrar={() => setModalAbierto(false)}
                registro={registro}
                colaboradores={colaboradores}
                puedeEditar={puedeEditar}
            />
        </AppLayout>
    );
}

function Info({ label, value, highlight = false }) {
    return (
        <div className={`p-4 rounded-2xl border theme-border ${highlight ? 'bg-black/[0.02] dark:bg-white/[0.02]' : 'theme-element'}`}>
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">{label}</p>
            <p className={`m-0 ${highlight ? 'text-lg font-bold text-red-500' : 'text-sm font-bold'} theme-text-main`}>{value}</p>
        </div>
    );
}
