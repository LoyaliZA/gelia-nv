import React, { useEffect, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { AlertTriangle, User, FileText, DollarSign, X, Save, Calculator } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';
import {
    calcularIncidenciaPreview, formatoDeduccionEntera, formatoMoneda, nombreCompletoColaborador,
} from '../../../../utils/formatoMoneda';
import { ESTADO_DEDUCCION_LABELS } from './incidenciasStyles';

const FORM_INICIAL = {
    fecha_ocurrencia: new Date().toISOString().slice(0, 10),
    rh_colaborador_id: '',
    catalogo_tipo_falta_id: '',
    observaciones: '',
    fecha_deduccion_nomina: '',
};

export default function ModalFormIncidencia({
    abierto,
    onCerrar,
    registro = null,
    colaboradores = [],
    tiposFalta = [],
    puedeEditar = true,
}) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({ ...FORM_INICIAL });

    const colaboradorSel = useMemo(
        () => colaboradores.find((c) => String(c.id) === String(data.rh_colaborador_id)),
        [colaboradores, data.rh_colaborador_id],
    );

    const tiposDisponibles = useMemo(() => {
        if (!registro?.tipo_falta) return tiposFalta;
        const existe = tiposFalta.some((t) => String(t.id) === String(registro.catalogo_tipo_falta_id));
        return existe ? tiposFalta : [...tiposFalta, registro.tipo_falta];
    }, [tiposFalta, registro]);

    const tipoSel = useMemo(
        () => tiposDisponibles.find((t) => String(t.id) === String(data.catalogo_tipo_falta_id)),
        [tiposDisponibles, data.catalogo_tipo_falta_id],
    );

    const preview = useMemo(
        () => calcularIncidenciaPreview(data, colaboradorSel, tipoSel),
        [data, colaboradorSel, tipoSel],
    );

    useEffect(() => {
        if (!abierto) return;

        if (registro) {
            setData({
                fecha_ocurrencia: registro.fecha_ocurrencia?.slice?.(0, 10) || registro.fecha_ocurrencia || '',
                rh_colaborador_id: registro.rh_colaborador_id || '',
                catalogo_tipo_falta_id: registro.catalogo_tipo_falta_id || '',
                observaciones: registro.observaciones || '',
                fecha_deduccion_nomina: registro.fecha_deduccion_nomina?.slice?.(0, 10) || registro.fecha_deduccion_nomina || '',
            });
        } else {
            reset();
            clearErrors();
        }
    }, [abierto, registro]);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!puedeEditar && registro) return;

        const accion = registro ? put : post;
        const ruta = registro
            ? route('rh.incidencias.update', registro.id)
            : route('rh.incidencias.store');

        accion(ruta, {
            preserveScroll: true,
            onSuccess: () => {
                onCerrar();
                reset();
            },
        });
    };

    if (!abierto) return null;

    const inputClass = 'w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none';

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6 overflow-y-auto`} onClick={onCerrar}>
            <GeliaLoader isVisible={processing} message="Guardando incidencia_" />
            <div className={`${THEME_MODAL_SHELL} max-w-4xl modal-pop text-left`} onClick={(e) => e.stopPropagation()}>
                <div className="p-6 md:p-8 border-b theme-border flex justify-between items-center">
                    <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-3 m-0">
                        <AlertTriangle className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        {registro ? 'Editar Incidencia' : 'Nueva Incidencia'}
                    </h2>
                    <button type="button" onClick={onCerrar} className="theme-text-muted p-2 rounded-full"><X className="w-6 h-6" /></button>
                </div>

                <form onSubmit={handleSubmit} className="gelia-modal-body p-6 md:p-8 custom-scrollbar space-y-8">
                    {registro && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 rounded-2xl theme-element border theme-border">
                            <div>
                                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Folio</p>
                                <p className="text-sm font-mono font-bold m-0">{registro.folio}</p>
                            </div>
                            <div>
                                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">UUID</p>
                                <p className="text-xs font-mono theme-text-muted m-0 break-all">{registro.uuid}</p>
                            </div>
                        </div>
                    )}

                    <section>
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                            <User className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Colaborador y fecha
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Campo label="Fecha de ocurrencia *" error={errors.fecha_ocurrencia}>
                                <input type="date" value={data.fecha_ocurrencia} onChange={(e) => setData('fecha_ocurrencia', e.target.value)} required className={inputClass} />
                            </Campo>
                            <Campo label="Colaborador *" error={errors.rh_colaborador_id}>
                                <select value={data.rh_colaborador_id} onChange={(e) => setData('rh_colaborador_id', e.target.value)} required className={inputClass}>
                                    <option value="">Selecciona colaborador...</option>
                                    {colaboradores.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {nombreCompletoColaborador(c)} — {c.folio}
                                        </option>
                                    ))}
                                </select>
                            </Campo>
                        </div>
                        {colaboradorSel && (
                            <div className="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 rounded-xl border theme-border bg-black/[0.02] dark:bg-white/[0.02] text-[10px]">
                                <div><span className="theme-text-muted uppercase font-black">Salario diario:</span> {formatoMoneda(colaboradorSel.salario_diario)}</div>
                                <div><span className="theme-text-muted uppercase font-black">Bono punt. diario:</span> {formatoMoneda(colaboradorSel.bono_puntualidad_diario)}</div>
                                <div><span className="theme-text-muted uppercase font-black">Bono prod. diario:</span> {formatoMoneda(colaboradorSel.bono_productividad_diario)}</div>
                            </div>
                        )}
                    </section>

                    <section>
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                            <AlertTriangle className="w-4 h-4 text-amber-500" /> Tipo de incidencia
                        </h3>
                        <select value={data.catalogo_tipo_falta_id} onChange={(e) => setData('catalogo_tipo_falta_id', e.target.value)} required className={inputClass}>
                            <option value="">Selecciona tipo de falta o retardo...</option>
                            {tiposDisponibles.map((t) => (
                                <option key={t.id} value={t.id}>{t.nombre}</option>
                            ))}
                        </select>
                        {errors.catalogo_tipo_falta_id && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.catalogo_tipo_falta_id}</p>}
                        {tipoSel && (
                            <div className="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 rounded-xl border theme-border bg-black/[0.02] dark:bg-white/[0.02] text-[10px]">
                                <div><span className="theme-text-muted uppercase font-black">Factor punt.:</span> ×{Number(tipoSel.factor_penalizacion_puntualidad).toFixed(2)}</div>
                                <div><span className="theme-text-muted uppercase font-black">Factor prod.:</span> ×{Number(tipoSel.factor_penalizacion_productividad).toFixed(2)}</div>
                                <div><span className="theme-text-muted uppercase font-black">Deduce salario:</span> {tipoSel.aplica_deduccion_salario_base ? 'Sí' : 'No'}</div>
                            </div>
                        )}
                    </section>

                    <section className="p-4 rounded-2xl border theme-border bg-black/[0.02] dark:bg-white/[0.02]">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-3 flex items-center gap-2">
                            <Calculator className="w-3.5 h-3.5" /> Cálculo automático de deducciones
                        </p>
                        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                            <CalcItem label="Salario base" value={formatoMoneda(preview.deduccion_salario_base)} />
                            <CalcItem label="Bono puntualidad" value={formatoMoneda(preview.deduccion_bono_puntualidad)} />
                            <CalcItem label="Bono productividad" value={formatoMoneda(preview.deduccion_bono_productividad)} />
                            <CalcItem label="Total deducción" value={formatoDeduccionEntera(preview.total_deduccion)} highlight />
                        </div>
                        <p className="text-[9px] theme-text-muted mt-2 m-0">
                            Total redondeado a entero · Estado: {ESTADO_DEDUCCION_LABELS[preview.estado_deduccion] || preview.estado_deduccion}
                        </p>
                    </section>

                    <section>
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                            <FileText className="w-4 h-4 text-blue-500" /> Observaciones
                        </h3>
                        <textarea
                            value={data.observaciones}
                            onChange={(e) => setData('observaciones', e.target.value)}
                            rows={3}
                            className={inputClass}
                            placeholder="Justificación o comentarios sobre la incidencia..."
                        />
                        {errors.observaciones && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.observaciones}</p>}
                    </section>

                    <section>
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-3 flex items-center gap-2">
                            <DollarSign className="w-4 h-4 text-emerald-500" /> Fecha de deducción en nómina
                        </h3>
                        <input type="date" value={data.fecha_deduccion_nomina} onChange={(e) => setData('fecha_deduccion_nomina', e.target.value)} className={inputClass} />
                        {errors.fecha_deduccion_nomina && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.fecha_deduccion_nomina}</p>}
                        {!data.fecha_deduccion_nomina && (
                            <span className="inline-block mt-2 px-2 py-1 rounded-lg text-[9px] font-black uppercase bg-amber-500/10 text-amber-600">Pendiente de aplicar en nómina</span>
                        )}
                    </section>

                    <div className="flex justify-end gap-3 pt-4 border-t theme-border">
                        <button type="button" onClick={onCerrar} className="px-6 py-3 rounded-2xl text-[10px] font-black uppercase theme-element theme-border border">Cancelar</button>
                        <button type="submit" disabled={processing} className="px-6 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Save className="w-4 h-4" /> {registro ? 'Guardar cambios' : 'Registrar incidencia'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body,
    );
}

function Campo({ label, error, children }) {
    return (
        <div className="space-y-1.5">
            <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">{label}</label>
            {children}
            {error && <p className="text-red-500 text-[10px] font-bold ml-2">{error}</p>}
        </div>
    );
}

function CalcItem({ label, value, highlight = false }) {
    return (
        <div>
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-0.5">{label}</p>
            <p className={`m-0 ${highlight ? 'text-base font-bold' : 'text-sm font-bold'} theme-text-main`}>{value}</p>
        </div>
    );
}
