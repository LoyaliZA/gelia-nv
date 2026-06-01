import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, AlertTriangle, Pencil, CheckCircle } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatoDeduccionEntera, formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';
import ModalFormIncidencia from './Partials/ModalFormIncidencia';
import { ESTADO_DEDUCCION_BADGE, ESTADO_DEDUCCION_LABELS } from './Partials/incidenciasStyles';

export default function Show({
    auth,
    registro,
    puedeEditar,
    puedeAplicar,
    colaboradores,
    tiposFalta,
}) {
    const [modalAbierto, setModalAbierto] = useState(false);

    const marcarAplicada = () => {
        if (!window.confirm(`¿Marcar la incidencia ${registro.folio} como aplicada en nómina? El monto quedará congelado.`)) return;
        router.post(route('rh.incidencias.aplicar', registro.id), {}, { preserveScroll: true });
    };

    return (
        <AppLayout auth={auth}>
            <Head title={`${registro.folio} | Incidencias RH`} />
            <div className="max-w-[1000px] mx-auto p-4 md:p-8 space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Link href={route('rh.incidencias.index')} className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main">
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
                                <Pencil className="w-4 h-4" /> Editar incidencia
                            </button>
                        )}
                    </div>
                </div>

                <RhSubNav />

                <header className={geliaCardClass('p-6 md:p-10')}>
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0 mb-2" style={{ color: 'var(--color-primario)' }}>Incidencia operativa</p>
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
                    <Info label="Fecha de ocurrencia" value={registro.fecha_ocurrencia?.slice?.(0, 10) || registro.fecha_ocurrencia} />
                    <Info label="Tipo de incidencia" value={registro.tipo_falta_nombre_snapshot || registro.tipo_falta?.nombre || '—'} />
                    <Info label="Departamento" value={registro.colaborador?.departamento?.nombre || '—'} />
                    <Info label="Área" value={registro.colaborador?.area?.nombre || '—'} />
                    <Info label="Fecha deducción nómina" value={registro.fecha_deduccion_nomina?.slice?.(0, 10) || 'Pendiente'} />
                    <Info label="Registrado por" value={registro.registrado_por?.name || '—'} />
                </section>

                <section className={geliaCardClass('p-6 md:p-8')}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 m-0">
                        <AlertTriangle className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Desglose de deducciones
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <Info label="Deducción salario base" value={formatoMoneda(registro.deduccion_salario_base)} />
                        <Info label="Deducción bono puntualidad" value={formatoMoneda(registro.deduccion_bono_puntualidad)} />
                        <Info label="Deducción bono productividad" value={formatoMoneda(registro.deduccion_bono_productividad)} />
                        <Info label="Total deducción (entero nómina)" value={formatoDeduccionEntera(registro.total_deduccion)} highlight />
                    </div>
                    <div className="mt-4 p-4 rounded-2xl border theme-border bg-black/[0.02] dark:bg-white/[0.02] text-[10px] space-y-1">
                        <p className="m-0 theme-text-muted"><span className="font-black uppercase">Snapshots salariales:</span> Salario diario {formatoMoneda(registro.salario_diario_snapshot)} · Bono punt. {formatoMoneda(registro.bono_puntualidad_diario_snapshot)} · Bono prod. {formatoMoneda(registro.bono_productividad_diario_snapshot)}</p>
                        <p className="m-0 theme-text-muted"><span className="font-black uppercase">Factores aplicados:</span> Puntualidad ×{registro.factor_puntualidad_snapshot} · Productividad ×{registro.factor_productividad_snapshot} · Deduce salario: {registro.aplica_deduccion_salario_snapshot ? 'Sí' : 'No'}</p>
                        {registro.estado_deduccion === 'aplicado' && (
                            <p className="m-0 theme-text-muted mt-2">Esta incidencia fue aplicada en nómina; los montos quedaron congelados y no pueden editarse.</p>
                        )}
                    </div>
                </section>

                {registro.observaciones && (
                    <section className={geliaCardClass('p-6 md:p-8')}>
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main mb-3 m-0">Observaciones</h2>
                        <p className="text-sm theme-text-main m-0 whitespace-pre-wrap">{registro.observaciones}</p>
                    </section>
                )}
            </div>

            <ModalFormIncidencia
                abierto={modalAbierto}
                onCerrar={() => setModalAbierto(false)}
                registro={registro}
                colaboradores={colaboradores}
                tiposFalta={tiposFalta}
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
