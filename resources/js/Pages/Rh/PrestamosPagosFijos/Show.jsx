import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Wallet, Pencil, Pause, Play, Ban } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import ModalFormPrestamo from './Partials/ModalFormPrestamo';
import {
    ESTADO_PRESTAMO_BADGE, ESTADO_PRESTAMO_LABELS, MODALIDAD_BADGE, MODALIDAD_LABELS,
} from './Partials/prestamosStyles';
import { RH_ESTADO_DEDUCCION_BADGE, RH_ESTADO_DEDUCCION_LABELS } from '../rhModuleStyles';

export default function Show({
    auth,
    registro,
    colaboradores,
    configuracion,
    puedeEditar,
    puedeDetener,
}) {
    const [modalAbierto, setModalAbierto] = useState(false);

    const accionEstado = (accion) => {
        const mensajes = {
            pausar: '¿Pausar este convenio?',
            reanudar: '¿Reanudar este convenio?',
            cancelar: '¿Cancelar este convenio? No se generarán más cuotas.',
        };
        if (!window.confirm(mensajes[accion])) return;
        router.post(route(`rh.prestamos.${accion}`, registro.id), {}, { preserveScroll: true });
    };

    const progreso = registro.num_pagos_total
        ? `${registro.pagos_realizados} / ${registro.num_pagos_total}`
        : `${registro.pagos_realizados} / indefinido`;

    return (
        <AppLayout auth={auth}>
            <Head title={`${registro.folio} | Préstamos RH`} />
            <GeliaPageShell className="max-w-[1000px] space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Link href={route('rh.prestamos.index')} className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main">
                        <ArrowLeft className="w-4 h-4" /> Volver al listado
                    </Link>
                    <div className="flex flex-wrap gap-2">
                        {puedeDetener && registro.estado === 'activo' && (
                            <button type="button" onClick={() => accionEstado('pausar')} className="px-4 py-2 rounded-2xl text-[10px] font-black uppercase theme-element theme-border border flex items-center gap-2">
                                <Pause className="w-4 h-4" /> Pausar
                            </button>
                        )}
                        {puedeDetener && registro.estado === 'pausado' && (
                            <button type="button" onClick={() => accionEstado('reanudar')} className="px-4 py-2 rounded-2xl text-[10px] font-black uppercase theme-element theme-border border flex items-center gap-2">
                                <Play className="w-4 h-4" /> Reanudar
                            </button>
                        )}
                        {puedeDetener && !['liquidado', 'cancelado'].includes(registro.estado) && (
                            <button type="button" onClick={() => accionEstado('cancelar')} className="px-4 py-2 rounded-2xl text-[10px] font-black uppercase text-red-600 theme-border border flex items-center gap-2">
                                <Ban className="w-4 h-4" /> Cancelar
                            </button>
                        )}
                        {puedeEditar && !['liquidado', 'cancelado'].includes(registro.estado) && (
                            <button type="button" onClick={() => setModalAbierto(true)} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Pencil className="w-4 h-4" /> Editar
                            </button>
                        )}
                    </div>
                </div>

                <RhSubNav />

                <header className={geliaCardClass('p-6 md:p-10')}>
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0 mb-2" style={{ color: 'var(--color-primario)' }}>Convenio de descuento</p>
                            <h1 className="text-2xl md:text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">{registro.folio}</h1>
                            <p className="text-sm font-bold theme-text-main mt-2 m-0">{nombreCompletoColaborador(registro.colaborador)}</p>
                            <p className="text-xs font-mono theme-text-muted m-0 mt-1">{registro.uuid}</p>
                        </div>
                        <div className="flex flex-col gap-2 items-end">
                            <span className={`px-3 py-1.5 rounded-xl text-[10px] font-black uppercase border ${ESTADO_PRESTAMO_BADGE[registro.estado]}`}>
                                {ESTADO_PRESTAMO_LABELS[registro.estado]}
                            </span>
                            <span className={`px-3 py-1.5 rounded-xl text-[10px] font-black uppercase border ${MODALIDAD_BADGE[registro.modalidad]}`}>
                                {MODALIDAD_LABELS[registro.modalidad]}
                            </span>
                        </div>
                    </div>
                </header>

                <section className={geliaCardClass('p-6 md:p-8 grid grid-cols-1 md:grid-cols-2 gap-4')}>
                    <Info label="Concepto" value={registro.concepto} />
                    <Info label="Monto por periodo" value={formatoMoneda(registro.monto_cuota)} />
                    <Info label="Progreso de pagos" value={progreso} />
                    <Info label="Monto total estimado" value={registro.monto_total_estimado ? formatoMoneda(registro.monto_total_estimado) : 'Indefinido'} />
                    <Info label="Fecha inicio" value={registro.fecha_inicio?.slice?.(0, 10) || '—'} />
                    <Info label="Fecha ejecución programada" value={registro.fecha_ejecucion_programada?.slice?.(0, 10) || 'Próximo corte'} />
                    <Info label="Último periodo generado" value={registro.ultimo_periodo_generado?.slice?.(0, 10) || '—'} />
                    <Info label="Registrado por" value={registro.registrado_por?.name || '—'} />
                </section>

                {registro.observaciones && (
                    <section className={geliaCardClass('p-6 md:p-8')}>
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main mb-3 m-0">Observaciones y acuerdos</h2>
                        <p className="text-sm theme-text-main m-0 whitespace-pre-wrap">{registro.observaciones}</p>
                    </section>
                )}

                <section className={geliaCardClass('overflow-hidden')}>
                    <div className="p-6 border-b theme-border">
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2 m-0">
                            <Wallet className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Cuotas generadas
                        </h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse">
                            <thead>
                                <tr className="border-b theme-border">
                                    {['Cuota', 'Folio deducción', 'Fecha', 'Monto', 'Estado', ''].map((h) => (
                                        <th key={h} className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {(registro.deducciones || []).length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-12 text-center theme-text-muted italic text-sm">Sin cuotas generadas aún.</td>
                                    </tr>
                                ) : (
                                    registro.deducciones.map((ded) => (
                                        <tr key={ded.id} className="border-b theme-border last:border-0">
                                            <td className="px-4 py-4 text-xs font-bold">#{ded.numero_cuota}</td>
                                            <td className="px-4 py-4 text-xs font-mono">{ded.folio}</td>
                                            <td className="px-4 py-4 text-xs">{ded.fecha_ocurrencia?.slice?.(0, 10)}</td>
                                            <td className="px-4 py-4 text-xs">{formatoMoneda(ded.monto_total_final)}</td>
                                            <td className="px-4 py-4">
                                                <span className={`px-2 py-1 rounded-lg text-[9px] font-black uppercase border ${RH_ESTADO_DEDUCCION_BADGE[ded.estado_deduccion]}`}>
                                                    {RH_ESTADO_DEDUCCION_LABELS[ded.estado_deduccion]}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4">
                                                <Link href={route('rh.deducciones.show', ded.id)} className="text-[10px] font-black uppercase" style={{ color: 'var(--color-primario)' }}>Ver</Link>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </GeliaPageShell>

            <ModalFormPrestamo
                abierto={modalAbierto}
                onCerrar={() => setModalAbierto(false)}
                registro={registro}
                colaboradores={colaboradores}
                configuracion={configuracion}
                puedeEditar={puedeEditar}
            />
        </AppLayout>
    );
}

function Info({ label, value }) {
    return (
        <div>
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1">{label}</p>
            <p className="text-sm font-bold theme-text-main m-0">{value ?? '—'}</p>
        </div>
    );
}
