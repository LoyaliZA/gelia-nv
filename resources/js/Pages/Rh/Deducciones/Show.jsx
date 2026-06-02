import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, AlertTriangle, Pencil, CheckCircle, FileText } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import ModalFormDeduccion from './Partials/ModalFormDeduccion';
import { ESTADO_DEDUCCION_BADGE, ESTADO_DEDUCCION_LABELS, ORIGEN_DEDUCCION_LABELS } from './Partials/deduccionesStyles';

export default function Show({
    auth,
    registro,
    puedeEditar,
    puedeAplicar,
    colaboradores,
    reglasIncidencia,
    usuarioActual,
}) {
    const [modalAbierto, setModalAbierto] = useState(false);

    const marcarAplicada = () => {
        if (!window.confirm(`¿Marcar la deducción ${registro.folio} como aplicada?`)) return;
        router.post(route('rh.deducciones.aplicar', registro.id), {}, { preserveScroll: true });
    };

    return (
        <AppLayout auth={auth}>
            <Head title={`${registro.folio} | Deducciones RH`} />
            <GeliaPageShell className="max-w-[1000px] space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Link href={route('rh.deducciones.index')} className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main">
                        <ArrowLeft className="w-4 h-4" /> Volver al listado
                    </Link>
                    <div className="flex flex-wrap gap-2">
                        {puedeAplicar && (
                            <button type="button" onClick={marcarAplicada} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase theme-element theme-border border flex items-center gap-2">
                                <CheckCircle className="w-4 h-4 text-emerald-500" /> Marcar aplicada
                            </button>
                        )}
                        {puedeEditar && (
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
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0 mb-2" style={{ color: 'var(--color-primario)' }}>Expediente digital</p>
                            <h1 className="text-2xl md:text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">{registro.folio}</h1>
                            <p className="text-sm font-bold theme-text-main mt-2 m-0">{nombreCompletoColaborador(registro.colaborador)}</p>
                            <p className="text-xs font-mono theme-text-muted m-0 mt-1">{registro.uuid}</p>
                        </div>
                        <span className={`px-3 py-1.5 rounded-xl text-[10px] font-black uppercase border ${ESTADO_DEDUCCION_BADGE[registro.estado_deduccion]}`}>
                            {ESTADO_DEDUCCION_LABELS[registro.estado_deduccion]}
                        </span>
                    </div>
                </header>

                <section className={geliaCardClass('p-6 md:p-8 grid grid-cols-1 md:grid-cols-2 gap-4')}>
                    <Info label="Fecha del evento" value={registro.fecha_ocurrencia?.slice?.(0, 10)} />
                    <Info label="Concepto" value={registro.regla_nombre_snapshot || registro.regla_incidencia?.nombre || '—'} />
                    {registro.prestamo_pago_fijo && (
                        <Info label="Convenio origen" value={
                            <Link href={route('rh.prestamos.show', registro.prestamo_pago_fijo.id)} className="font-bold" style={{ color: 'var(--color-primario)' }}>
                                {registro.prestamo_pago_fijo.folio} (cuota #{registro.numero_cuota})
                            </Link>
                        } />
                    )}
                    <Info label="Departamento" value={registro.departamento_snapshot || registro.colaborador?.departamento?.nombre || '—'} />
                    <Info label="Área" value={registro.area_snapshot || registro.colaborador?.area?.nombre || '—'} />
                    <Info label="Origen deducción" value={ORIGEN_DEDUCCION_LABELS[registro.origen_deduccion] || registro.origen_deduccion} />
                    <Info label="Reportado por" value={registro.registrado_por?.name || '—'} />
                    <Info label="SKU producto" value={registro.producto_sku_snapshot || '—'} />
                    <Info label="Factor multiplicador" value={`×${registro.factor_multiplicador ?? 1}`} />
                    <Info label="Fecha aplicación" value={registro.fecha_aplicacion_deduccion?.slice?.(0, 10) || 'Pendiente'} />
                    <Info label="Fecha deducción nómina" value={registro.fecha_deduccion_nomina?.slice?.(0, 10) || '—'} />
                </section>

                <section className={geliaCardClass('p-6 md:p-8')}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 m-0">
                        <AlertTriangle className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Cálculo de deducción
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <Info label="Monto base" value={formatoMoneda(registro.monto_deduccion_base)} />
                        <Info label="Total parcial" value={formatoMoneda(registro.total_parcial)} />
                        <Info label="Monto total final" value={formatoMoneda(registro.monto_total_final)} highlight />
                        <Info label="Deducción salario" value={formatoMoneda(registro.deduccion_salario_base)} />
                        <Info label="Bono puntualidad" value={formatoMoneda(registro.deduccion_bono_puntualidad)} />
                        <Info label="Bono productividad" value={formatoMoneda(registro.deduccion_bono_productividad)} />
                    </div>
                </section>

                {registro.comision_auditor && (
                    <section className={geliaCardClass('p-6 md:p-8')}>
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main mb-3 m-0">Comisión auditora generada</h2>
                        <p className="text-sm m-0">{registro.comision_auditor.usuario?.name}: {formatoMoneda(registro.comision_auditor.monto)} ({registro.comision_auditor.estado})</p>
                    </section>
                )}

                {registro.descripcion_detallada && (
                    <section className={geliaCardClass('p-6 md:p-8')}>
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main mb-3 m-0 flex items-center gap-2">
                            <FileText className="w-4 h-4" /> Descripción detallada
                        </h2>
                        <p className="text-sm theme-text-main m-0 whitespace-pre-wrap">{registro.descripcion_detallada}</p>
                    </section>
                )}

                {(registro.firma_gerente_path || registro.firma_colaborador_path) && (
                    <section className={geliaCardClass('p-6 md:p-8 grid grid-cols-1 md:grid-cols-2 gap-4')}>
                        {registro.firma_gerente_path && (
                            <div>
                                <p className="text-[9px] font-black uppercase theme-text-muted mb-2">Firma gerente</p>
                                <img src={`/storage/${registro.firma_gerente_path}`} alt="Firma gerente" className="max-h-32 rounded-xl border theme-border bg-white" />
                            </div>
                        )}
                        {registro.firma_colaborador_path && (
                            <div>
                                <p className="text-[9px] font-black uppercase theme-text-muted mb-2">Firma colaborador</p>
                                <img src={`/storage/${registro.firma_colaborador_path}`} alt="Firma colaborador" className="max-h-32 rounded-xl border theme-border bg-white" />
                            </div>
                        )}
                    </section>
                )}
            </GeliaPageShell>

            <ModalFormDeduccion
                abierto={modalAbierto}
                onCerrar={() => setModalAbierto(false)}
                registro={registro}
                colaboradores={colaboradores}
                reglasIncidencia={reglasIncidencia}
                usuarioActual={usuarioActual}
            />
        </AppLayout>
    );
}

function Info({ label, value, highlight = false }) {
    return (
        <div className={`p-4 rounded-2xl border theme-border ${highlight ? 'bg-black/[0.02] dark:bg-white/[0.02]' : 'theme-element'}`}>
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">{label}</p>
            <div className={`${highlight ? 'text-lg font-bold' : 'text-sm font-bold'} theme-text-main`}>{value}</div>
        </div>
    );
}
