import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { TimerReset, User, FileText, Calendar, X, Save, CheckCircle2 } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_INPUT,
    THEME_TEXTAREA,
    THEME_LABEL,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_BTN_ICON,
} from '../../../../utils/geliaTheme';
import { nombreCompletoColaborador } from '../../../../utils/formatoMoneda';

// ─── Modo: 'crear' | 'editar' | 'saldar'
export default function ModalFormBancoTiempo({
    abierto,
    onCerrar,
    registro = null,
    modo = 'crear',          // 'crear' | 'editar' | 'saldar'
    colaboradores = [],
    puedeEditar = true,
}) {
    // Formulario creación/edición
    const formEdicion = useForm({
        rh_colaborador_id: '',
        horas_pendientes: '',
        origen_deuda: '',
        fecha_acuerdo: new Date().toISOString().slice(0, 10),
    });

    // Formulario saldo
    const formSaldo = useForm({
        fecha_devolucion: new Date().toISOString().slice(0, 10),
    });

    useEffect(() => {
        if (!abierto) return;

        if (modo === 'editar' && registro) {
            formEdicion.setData({
                rh_colaborador_id: registro.rh_colaborador_id || '',
                horas_pendientes: registro.horas_pendientes ?? '',
                origen_deuda: registro.origen_deuda || '',
                fecha_acuerdo: registro.fecha_acuerdo?.slice?.(0, 10) || registro.fecha_acuerdo || '',
            });
        } else if (modo === 'saldar') {
            formSaldo.setData({ fecha_devolucion: new Date().toISOString().slice(0, 10) });
        } else {
            formEdicion.reset();
            formEdicion.clearErrors();
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [abierto, modo, registro?.id]);

    const handleSubmitEdicion = (e) => {
        e.preventDefault();
        if (!puedeEditar && modo === 'editar') return;

        const ruta = modo === 'editar'
            ? route('rh.banco_tiempo.update', registro.id)
            : route('rh.banco_tiempo.store');

        const accion = modo === 'editar' ? formEdicion.put : formEdicion.post;

        accion(ruta, {
            preserveScroll: true,
            onSuccess: () => { onCerrar(); formEdicion.reset(); },
        });
    };

    const handleSubmitSaldo = (e) => {
        e.preventDefault();
        formSaldo.post(route('rh.banco_tiempo.saldar', registro.id), {
            preserveScroll: true,
            onSuccess: () => { onCerrar(); formSaldo.reset(); },
        });
    };

    if (!abierto) return null;

    const esSaldar = modo === 'saldar';
    const esEdicion = modo === 'editar';

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6 overflow-y-auto`} onClick={onCerrar}>
            <GeliaLoader isVisible={formEdicion.processing || formSaldo.processing} message={esSaldar ? 'Saldando deuda_' : 'Guardando registro_'} />
            <div className={`${THEME_MODAL_SHELL} max-w-2xl modal-pop text-left`} onClick={(e) => e.stopPropagation()}>

                {/* ── Header */}
                <div className="p-6 md:p-8 border-b theme-border flex justify-between items-center">
                    <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-3 m-0">
                        {esSaldar
                            ? <><CheckCircle2 className="w-6 h-6 text-emerald-500" /> Saldar deuda de tiempo</>
                            : <><TimerReset className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                                {esEdicion ? 'Editar registro' : 'Nueva deuda de tiempo'}
                              </>
                        }
                    </h2>
                    <button type="button" onClick={onCerrar} className={THEME_BTN_ICON}><X className="w-6 h-6" /></button>
                </div>

                {/* ─── Formulario: Saldar */}
                {esSaldar && registro && (
                    <form onSubmit={handleSubmitSaldo} className="p-6 md:p-8 space-y-6">
                        {/* Resumen del registro */}
                        <div className="p-4 rounded-2xl border theme-border bg-black/[0.02] dark:bg-white/[0.02] space-y-3">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Resumen de la deuda a saldar</p>
                            <div className="grid grid-cols-2 gap-3 text-xs">
                                <div>
                                    <span className="theme-text-muted font-bold block">Folio</span>
                                    <span className="font-black font-mono">{registro.folio}</span>
                                </div>
                                <div>
                                    <span className="theme-text-muted font-bold block">Horas pendientes</span>
                                    <span className="font-black text-amber-600">{registro.horas_pendientes} hrs</span>
                                </div>
                                <div className="col-span-2">
                                    <span className="theme-text-muted font-bold block">Origen del acuerdo</span>
                                    <span className="font-medium theme-text-main">{registro.origen_deuda}</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label className={THEME_LABEL}>Fecha de devolución efectiva</label>
                            <input
                                type="date"
                                value={formSaldo.data.fecha_devolucion}
                                onChange={(e) => formSaldo.setData('fecha_devolucion', e.target.value)}
                                className={THEME_INPUT}
                                required
                            />
                            <p className="text-[10px] theme-text-muted mt-1">
                                Día en que el colaborador terminó de reponer el tiempo adeudado.
                            </p>
                            {formSaldo.errors.fecha_devolucion && <p className="text-red-500 text-xs mt-1">{formSaldo.errors.fecha_devolucion}</p>}
                        </div>

                        <div className="flex justify-end gap-3 pt-4 border-t theme-border">
                            <button type="button" onClick={onCerrar} className={THEME_BTN_SECONDARY}>Cancelar</button>
                            <button type="submit" disabled={formSaldo.processing} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center gap-2 bg-emerald-500 hover:bg-emerald-600 transition-colors">
                                <CheckCircle2 className="w-4 h-4" /> Confirmar Saldo
                            </button>
                        </div>
                    </form>
                )}

                {/* ─── Formulario: Crear / Editar */}
                {!esSaldar && (
                    <form onSubmit={handleSubmitEdicion} className="p-6 md:p-8 space-y-6">
                        <section className="space-y-4">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-2 m-0">
                                <User className="w-3.5 h-3.5" /> Colaborador y fecha del acuerdo
                            </p>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="md:col-span-2">
                                    <label className={THEME_LABEL}>Colaborador</label>
                                    <select
                                        value={formEdicion.data.rh_colaborador_id}
                                        onChange={(e) => formEdicion.setData('rh_colaborador_id', e.target.value)}
                                        className={THEME_INPUT}
                                        required
                                        disabled={esEdicion}
                                    >
                                        <option value="">Seleccionar colaborador...</option>
                                        {colaboradores.map((c) => (
                                            <option key={c.id} value={c.id}>{nombreCompletoColaborador(c)}</option>
                                        ))}
                                    </select>
                                    {formEdicion.errors.rh_colaborador_id && <p className="text-red-500 text-xs mt-1">{formEdicion.errors.rh_colaborador_id}</p>}
                                </div>
                                <div>
                                    <label className={THEME_LABEL}>Horas pendientes a devolver</label>
                                    <input
                                        type="number"
                                        min="0.25"
                                        max="999.99"
                                        step="0.25"
                                        value={formEdicion.data.horas_pendientes}
                                        onChange={(e) => formEdicion.setData('horas_pendientes', e.target.value)}
                                        className={THEME_INPUT}
                                        placeholder="Ej: 2.00"
                                        required
                                    />
                                    <p className="text-[10px] theme-text-muted mt-1">En incrementos de 0.25 (= 15 minutos)</p>
                                    {formEdicion.errors.horas_pendientes && <p className="text-red-500 text-xs mt-1">{formEdicion.errors.horas_pendientes}</p>}
                                </div>
                                <div>
                                    <label className={THEME_LABEL}>Fecha del acuerdo / registro</label>
                                    <input
                                        type="date"
                                        value={formEdicion.data.fecha_acuerdo}
                                        onChange={(e) => formEdicion.setData('fecha_acuerdo', e.target.value)}
                                        className={THEME_INPUT}
                                        required
                                    />
                                    {formEdicion.errors.fecha_acuerdo && <p className="text-red-500 text-xs mt-1">{formEdicion.errors.fecha_acuerdo}</p>}
                                </div>
                            </div>
                        </section>

                        <section className="space-y-3">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-2 m-0">
                                <FileText className="w-3.5 h-3.5" /> Origen y justificación de la deuda
                            </p>
                            <textarea
                                value={formEdicion.data.origen_deuda}
                                onChange={(e) => formEdicion.setData('origen_deuda', e.target.value)}
                                rows={5}
                                className={THEME_TEXTAREA}
                                placeholder="Ej: El colaborador solicitó salir 2 horas temprano por urgencia personal y acordó con gerencia devolverlas el lunes ingresando antes de su turno habitual..."
                                required
                                minLength={10}
                            />
                            {formEdicion.errors.origen_deuda && <p className="text-red-500 text-xs mt-1">{formEdicion.errors.origen_deuda}</p>}
                        </section>

                        <div className="flex justify-end gap-3 pt-4 border-t theme-border">
                            <button type="button" onClick={onCerrar} className={THEME_BTN_SECONDARY}>Cancelar</button>
                            <button type="submit" disabled={formEdicion.processing} className={THEME_BTN_PRIMARY}>
                                <Save className="w-4 h-4" /> Guardar
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </div>,
        document.body,
    );
}
