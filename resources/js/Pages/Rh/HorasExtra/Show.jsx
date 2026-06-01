import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Clock, Pencil } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoMoneda, formatearHora, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import ModalFormHorasExtra from './Partials/ModalFormHorasExtra';
import { ESTADO_PAGO_BADGE, ESTADO_PAGO_LABELS } from './Partials/horasExtraStyles';

export default function Show({
    auth,
    registro,
    configuracion,
    puedeEditar,
    colaboradores,
    supervisores,
}) {
    const [modalAbierto, setModalAbierto] = useState(false);

    return (
        <AppLayout auth={auth}>
            <Head title={`${registro.folio} | Horas Extra`} />
            <div className="max-w-[1000px] mx-auto p-4 md:p-8 space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Link href={route('rh.horas_extra.index')} className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main">
                        <ArrowLeft className="w-4 h-4" /> Volver al listado
                    </Link>
                    {puedeEditar && (
                        <button type="button" onClick={() => setModalAbierto(true)} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Pencil className="w-4 h-4" /> Editar registro
                        </button>
                    )}
                </div>

                <RhSubNav />

                <header className={geliaCardClass('p-6 md:p-10')}>
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0 mb-2" style={{ color: 'var(--color-primario)' }}>Registro Horas Extra</p>
                            <h1 className="text-2xl md:text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">{registro.folio}</h1>
                            <p className="text-sm font-bold theme-text-main mt-2 m-0">{nombreCompletoColaborador(registro.colaborador)}</p>
                            <p className="text-xs font-mono theme-text-muted m-0 mt-1">{registro.uuid}</p>
                        </div>
                        <span className={`px-3 py-1.5 rounded-xl text-[10px] font-black uppercase border ${ESTADO_PAGO_BADGE[registro.estado_pago]}`}>
                            {ESTADO_PAGO_LABELS[registro.estado_pago]}
                        </span>
                    </div>
                </header>

                <section className={geliaCardClass('p-6 md:p-8 grid grid-cols-1 md:grid-cols-2 gap-4')}>
                    <Info label="Fecha turno" value={registro.fecha_turno?.slice?.(0, 10) || registro.fecha_turno} />
                    <Info label="Horario" value={`${formatearHora(registro.hora_entrada)} – ${formatearHora(registro.hora_salida)}${registro.salida_dia_siguiente ? ' (+1 día)' : ''}`} />
                    <Info label="Área (snapshot)" value={registro.area_snapshot || registro.colaborador?.area?.nombre || '—'} />
                    <Info label="Supervisor" value={registro.supervisor ? `${registro.supervisor.name} ${registro.supervisor.apellido_paterno || ''}` : '—'} />
                    <Info label="Fecha programada pago" value={registro.fecha_programada_pago?.slice?.(0, 10) || 'Pendiente'} />
                    <Info label="Registrado por" value={registro.registrado_por?.name || '—'} />
                </section>

                <section className={geliaCardClass('p-6 md:p-8')}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 m-0">
                        <Clock className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Desglose de cálculo
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <Info label="Total horas laboradas" value={`${registro.total_horas_laboradas} h`} />
                        <Info label="Horario normal (snapshot)" value={`${registro.horas_normales_snapshot} h`} />
                        <Info label="Tiempo extra crudo" value={`${registro.tiempo_extra_crudo} h (${registro.tiempo_extra_minutos} min)`} />
                        <Info label="Horas extra a pagar" value={`${registro.horas_extra_a_pagar} h`} highlight />
                        <Info label="Salario/hora (snapshot)" value={formatoMoneda(registro.salario_por_hora_snapshot)} />
                        <Info label="Multiplicador" value={`×${registro.multiplicador_snapshot}`} />
                        <Info label="Total económico" value={formatoMoneda(registro.total_economico)} highlight />
                    </div>
                    <p className="text-[9px] theme-text-muted uppercase tracking-widest mt-4 m-0">
                        Fórmula: {registro.horas_extra_a_pagar} h × {registro.multiplicador_snapshot} × {formatoMoneda(registro.salario_por_hora_snapshot)}
                    </p>
                </section>

                <section className={geliaCardClass('p-6 md:p-8')}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main mb-3 m-0">Motivo</h2>
                    <p className="text-sm theme-text-main m-0 whitespace-pre-wrap">{registro.motivo}</p>
                </section>
            </div>

            <ModalFormHorasExtra
                abierto={modalAbierto}
                onCerrar={() => setModalAbierto(false)}
                registro={registro}
                colaboradores={colaboradores}
                supervisores={supervisores}
                configuracion={configuracion}
            />
        </AppLayout>
    );
}

function Info({ label, value, highlight = false }) {
    return (
        <div className={`p-4 rounded-2xl border theme-border ${highlight ? 'bg-black/[0.02] dark:bg-white/[0.02]' : 'theme-element'}`}>
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">{label}</p>
            <p className={`m-0 ${highlight ? 'text-lg font-bold' : 'text-sm font-bold'} theme-text-main`}>{value}</p>
        </div>
    );
}
