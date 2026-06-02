import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { Clock, User, FileText, Shield, DollarSign, X, Save, Calculator } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_INPUT,
    THEME_SELECT,
    THEME_TEXTAREA,
    THEME_LABEL,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_BTN_ICON,
} from '../../../../utils/geliaTheme';
import {
    calcularHorasExtraPreview, formatoMoneda, formatearHora, nombreCompletoColaborador,
} from '../../../../utils/formatoMoneda';
import { RH_ESTADO_FLUJO_BADGE, rhBadgeClass } from '../../rhModuleStyles';

const FORM_INICIAL = {
    rh_colaborador_id: '',
    fecha_turno: new Date().toISOString().slice(0, 10),
    hora_entrada: '08:00',
    hora_salida: '17:00',
    salida_dia_siguiente: false,
    motivo: '',
    supervisor_user_id: '',
    fecha_programada_pago: '',
};

export default function ModalFormHorasExtra({
    abierto,
    onCerrar,
    registro = null,
    colaboradores = [],
    supervisores = [],
    configuracion = {},
    puedeEditar = true,
}) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({ ...FORM_INICIAL });

    const colaboradorSel = useMemo(
        () => colaboradores.find((c) => String(c.id) === String(data.rh_colaborador_id)),
        [colaboradores, data.rh_colaborador_id],
    );

    const preview = useMemo(
        () => calcularHorasExtraPreview(data, configuracion, colaboradorSel),
        [data, configuracion, colaboradorSel],
    );

    useEffect(() => {
        if (!abierto) return;

        if (registro) {
            setData({
                rh_colaborador_id: registro.rh_colaborador_id || '',
                fecha_turno: registro.fecha_turno?.slice?.(0, 10) || registro.fecha_turno || '',
                hora_entrada: formatearHora(registro.hora_entrada),
                hora_salida: formatearHora(registro.hora_salida),
                salida_dia_siguiente: !!registro.salida_dia_siguiente,
                motivo: registro.motivo || '',
                supervisor_user_id: registro.supervisor_user_id || '',
                fecha_programada_pago: registro.fecha_programada_pago?.slice?.(0, 10) || registro.fecha_programada_pago || '',
            });
        } else {
            reset();
            clearErrors();
        }
    }, [abierto, registro]);

    useEffect(() => {
        if (!abierto || registro) return;
        setData('salida_dia_siguiente', preview.salida_dia_siguiente);
    }, [preview.salida_dia_siguiente, abierto, registro]);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!puedeEditar && registro) return;

        const accion = registro ? put : post;
        const ruta = registro
            ? route('rh.horas_extra.update', registro.id)
            : route('rh.horas_extra.store');

        accion(ruta, {
            preserveScroll: true,
            onSuccess: () => {
                onCerrar();
                reset();
            },
        });
    };

    if (!abierto) return null;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6 overflow-y-auto`} onClick={onCerrar}>
            <GeliaLoader isVisible={processing} message="Guardando registro_" />
            <div className={`${THEME_MODAL_SHELL} max-w-4xl modal-pop text-left`} onClick={(e) => e.stopPropagation()}>
                <div className="p-6 md:p-8 border-b theme-border flex justify-between items-center">
                    <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-3 m-0">
                        <Clock className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        {registro ? 'Editar Horas Extra' : 'Nuevo Registro de Horas Extra'}
                    </h2>
                    <button type="button" onClick={onCerrar} className={THEME_BTN_ICON}><X className="w-6 h-6" /></button>
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
                            <User className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Colaborador
                        </h3>
                        <select
                            value={data.rh_colaborador_id}
                            onChange={(e) => setData('rh_colaborador_id', e.target.value)}
                            required
                            className={THEME_SELECT}
                        >
                            <option value="">Selecciona colaborador...</option>
                            {colaboradores.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {nombreCompletoColaborador(c)} — {c.folio}
                                </option>
                            ))}
                        </select>
                        {errors.rh_colaborador_id && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.rh_colaborador_id}</p>}
                        {colaboradorSel && (
                            <div className="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 rounded-xl border theme-border bg-black/[0.02] dark:bg-white/[0.02] text-[10px] theme-text-main">
                                <div><span className="theme-text-muted uppercase font-black">Área:</span> {colaboradorSel.area?.nombre || '—'}</div>
                                <div><span className="theme-text-muted uppercase font-black">Horas normales:</span> {colaboradorSel.horas_laboradas_oficiales} h</div>
                                <div><span className="theme-text-muted uppercase font-black">Salario/hr:</span> {formatoMoneda(colaboradorSel.salario_por_hora)}</div>
                            </div>
                        )}
                    </section>

                    <section>
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                            <Clock className="w-4 h-4 text-blue-500" /> Horario
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <Campo label="Fecha turno *" error={errors.fecha_turno}>
                                <input type="date" value={data.fecha_turno} onChange={(e) => setData('fecha_turno', e.target.value)} required className={THEME_INPUT} />
                            </Campo>
                            <Campo label="Hora entrada *" error={errors.hora_entrada}>
                                <input type="time" value={data.hora_entrada} onChange={(e) => setData('hora_entrada', e.target.value)} required className={THEME_INPUT} />
                            </Campo>
                            <Campo label="Hora salida *" error={errors.hora_salida}>
                                <input type="time" value={data.hora_salida} onChange={(e) => setData('hora_salida', e.target.value)} required className={THEME_INPUT} />
                            </Campo>
                            <div className="flex items-end pb-1">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={!!data.salida_dia_siguiente}
                                        onChange={(e) => setData('salida_dia_siguiente', e.target.checked)}
                                    />
                                    <span className="text-[10px] font-black uppercase theme-text-main">Salida día siguiente</span>
                                </label>
                            </div>
                        </div>
                    </section>

                    <section className="p-4 rounded-2xl border theme-border bg-black/[0.02] dark:bg-white/[0.02]">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-3 flex items-center gap-2">
                            <Calculator className="w-3.5 h-3.5" /> Cálculo automático
                        </p>
                        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                            <CalcItem label="Total horas" value={`${preview.total_horas_laboradas} h`} />
                            <CalcItem label="Extra crudo" value={`${preview.tiempo_extra_crudo} h (${preview.tiempo_extra_minutos} min)`} />
                            <CalcItem label="Horas a pagar" value={`${preview.horas_extra_a_pagar} h`} highlight />
                            <CalcItem label="Total económico" value={formatoMoneda(preview.total_economico)} highlight />
                        </div>
                        <p className="text-[9px] theme-text-muted mt-2 m-0">
                            Regla: mínimo {configuracion.he_minutos_minimos || 30} min · Multiplicador ×{configuracion.he_multiplicador_pago || 2}
                        </p>
                    </section>

                    <section>
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                            <FileText className="w-4 h-4 text-amber-500" /> Justificación
                        </h3>
                        <textarea
                            value={data.motivo}
                            onChange={(e) => setData('motivo', e.target.value)}
                            required
                            rows={3}
                            className={THEME_TEXTAREA}
                            placeholder="Motivo operativo de la jornada extendida..."
                        />
                        {errors.motivo && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.motivo}</p>}
                    </section>

                    <section className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-3 flex items-center gap-2">
                                <Shield className="w-4 h-4 text-purple-500" /> Supervisor *
                            </h3>
                            <select value={data.supervisor_user_id} onChange={(e) => setData('supervisor_user_id', e.target.value)} required className={THEME_SELECT}>
                                <option value="">Selecciona supervisor...</option>
                                {supervisores.map((u) => (
                                    <option key={u.id} value={u.id}>{u.name} {u.apellido_paterno || ''} — {u.email}</option>
                                ))}
                            </select>
                            {errors.supervisor_user_id && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.supervisor_user_id}</p>}
                        </div>
                        <div>
                            <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-3 flex items-center gap-2">
                                <DollarSign className="w-4 h-4 text-emerald-500" /> Fecha programada de pago
                            </h3>
                            <input type="date" value={data.fecha_programada_pago} onChange={(e) => setData('fecha_programada_pago', e.target.value)} className={THEME_INPUT} />
                            {!data.fecha_programada_pago && (
                                <span className={`inline-block mt-2 ${rhBadgeClass(RH_ESTADO_FLUJO_BADGE, 'pendiente')}`}>Pendiente de pago</span>
                            )}
                        </div>
                    </section>

                    <div className="flex justify-end gap-3 pt-4 border-t theme-border">
                        <button type="button" onClick={onCerrar} className={THEME_BTN_SECONDARY}>Cancelar</button>
                        <button type="submit" disabled={processing} className={THEME_BTN_PRIMARY}>
                            <Save className="w-4 h-4" /> {registro ? 'Guardar cambios' : 'Registrar horas extra'}
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
            <label className={THEME_LABEL}>{label}</label>
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
