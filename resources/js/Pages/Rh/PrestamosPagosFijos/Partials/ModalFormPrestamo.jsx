import React, { useEffect, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { Wallet, User, FileText, Calendar, X, Save } from 'lucide-react';
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
import { formatoMoneda, nombreCompletoColaborador } from '../../../../utils/formatoMoneda';
import { MODALIDAD_LABELS } from './prestamosStyles';

const FORM_INICIAL = {
    rh_colaborador_id: '',
    concepto: '',
    monto_cuota: '',
    num_pagos_total: '',
    modalidad: 'recurrente',
    observaciones: '',
    fecha_ejecucion_programada: '',
    fecha_inicio: new Date().toISOString().slice(0, 10),
};

export default function ModalFormPrestamo({
    abierto,
    onCerrar,
    registro = null,
    colaboradores = [],
    configuracion = {},
    puedeEditar = true,
}) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({ ...FORM_INICIAL });

    const esUnicaVez = data.modalidad === 'unica_vez';
    const esRecurrente = data.modalidad === 'recurrente';

    const previewTexto = useMemo(() => {
        const monto = Number(data.monto_cuota) || 0;
        const dias = configuracion?.dias_periodo_pago || 30;

        if (esUnicaVez) {
            const fecha = data.fecha_ejecucion_programada || 'el próximo corte de nómina';
            return `Se descontará ${formatoMoneda(monto)} una sola vez en ${fecha}.`;
        }

        const pagos = data.num_pagos_total ? `${data.num_pagos_total} pagos` : 'pagos indefinidos';
        return `Se descontarán ${formatoMoneda(monto)} cada ${dias} días (${pagos}).`;
    }, [data, configuracion, esUnicaVez]);

    useEffect(() => {
        if (!abierto) return;

        if (registro) {
            setData({
                rh_colaborador_id: registro.rh_colaborador_id || '',
                concepto: registro.concepto || '',
                monto_cuota: registro.monto_cuota ?? '',
                num_pagos_total: registro.num_pagos_total ?? '',
                modalidad: registro.modalidad || 'recurrente',
                observaciones: registro.observaciones || '',
                fecha_ejecucion_programada: registro.fecha_ejecucion_programada?.slice?.(0, 10) || '',
                fecha_inicio: registro.fecha_inicio?.slice?.(0, 10) || registro.fecha_inicio || '',
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
            ? route('rh.prestamos.update', registro.id)
            : route('rh.prestamos.store');

        accion(ruta, {
            preserveScroll: true,
            transform: (formData) => ({
                ...formData,
                num_pagos_total: esRecurrente ? (formData.num_pagos_total || null) : 1,
                fecha_ejecucion_programada: esUnicaVez ? (formData.fecha_ejecucion_programada || null) : null,
            }),
            onSuccess: () => {
                onCerrar();
                reset();
            },
        });
    };

    if (!abierto) return null;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6 overflow-y-auto`} onClick={onCerrar}>
            <GeliaLoader isVisible={processing} message="Guardando convenio_" />
            <div className={`${THEME_MODAL_SHELL} max-w-3xl modal-pop text-left`} onClick={(e) => e.stopPropagation()}>
                <div className="p-6 md:p-8 border-b theme-border flex justify-between items-center">
                    <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-3 m-0">
                        <Wallet className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        {registro ? 'Editar convenio' : 'Nuevo préstamo / pago fijo'}
                    </h2>
                    <button type="button" onClick={onCerrar} className={THEME_BTN_ICON}><X className="w-6 h-6" /></button>
                </div>

                <form onSubmit={handleSubmit} className="p-6 md:p-8 space-y-6">
                    <section className="space-y-4">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-2 m-0">
                            <User className="w-3.5 h-3.5" /> Colaborador y concepto
                        </p>
                        <div>
                            <label className={THEME_LABEL}>Colaborador solicitante</label>
                            <select value={data.rh_colaborador_id} onChange={(e) => setData('rh_colaborador_id', e.target.value)} className={THEME_SELECT} required>
                                <option value="">Seleccionar colaborador</option>
                                {colaboradores.map((c) => (
                                    <option key={c.id} value={c.id}>{nombreCompletoColaborador(c)}</option>
                                ))}
                            </select>
                            {errors.rh_colaborador_id && <p className="text-red-500 text-xs mt-1">{errors.rh_colaborador_id}</p>}
                        </div>
                        <div>
                            <label className={THEME_LABEL}>Concepto de la deuda</label>
                            <textarea value={data.concepto} onChange={(e) => setData('concepto', e.target.value)} rows={3} className={THEME_TEXTAREA} required />
                            {errors.concepto && <p className="text-red-500 text-xs mt-1">{errors.concepto}</p>}
                        </div>
                    </section>

                    <section className="space-y-4">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-2 m-0">
                            <Calendar className="w-3.5 h-3.5" /> Modalidad y montos
                        </p>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className={THEME_LABEL}>Modalidad</label>
                                <select value={data.modalidad} onChange={(e) => setData('modalidad', e.target.value)} className={THEME_SELECT}>
                                    {Object.entries(MODALIDAD_LABELS).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className={THEME_LABEL}>Monto por periodo</label>
                                <input type="number" min="0.01" step="0.01" value={data.monto_cuota} onChange={(e) => setData('monto_cuota', e.target.value)} className={THEME_INPUT} required />
                                {errors.monto_cuota && <p className="text-red-500 text-xs mt-1">{errors.monto_cuota}</p>}
                            </div>
                            {esRecurrente && (
                                <>
                                    <div>
                                        <label className={THEME_LABEL}>Total de pagos (vacío = indefinido)</label>
                                        <input type="number" min="1" value={data.num_pagos_total} onChange={(e) => setData('num_pagos_total', e.target.value)} className={THEME_INPUT} />
                                    </div>
                                    <div>
                                        <label className={THEME_LABEL}>Fecha de inicio</label>
                                        <input type="date" value={data.fecha_inicio} onChange={(e) => setData('fecha_inicio', e.target.value)} className={THEME_INPUT} />
                                    </div>
                                </>
                            )}
                            {esUnicaVez && (
                                <div className="md:col-span-2">
                                    <label className={THEME_LABEL}>Fecha de ejecución programada (vacío = corte actual)</label>
                                    <input type="date" value={data.fecha_ejecucion_programada} onChange={(e) => setData('fecha_ejecucion_programada', e.target.value)} className={THEME_INPUT} />
                                </div>
                            )}
                        </div>
                        <div className="p-4 rounded-2xl border theme-border bg-black/[0.02] dark:bg-white/[0.02]">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Vista previa</p>
                            <p className="text-sm font-bold theme-text-main m-0">{previewTexto}</p>
                        </div>
                    </section>

                    <section>
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-2 m-0 mb-3">
                            <FileText className="w-3.5 h-3.5" /> Observaciones y acuerdos
                        </p>
                        <textarea value={data.observaciones} onChange={(e) => setData('observaciones', e.target.value)} rows={4} className={THEME_TEXTAREA} placeholder="Notas para contabilidad..." />
                    </section>

                    <div className="flex justify-end gap-3 pt-4 border-t theme-border">
                        <button type="button" onClick={onCerrar} className={THEME_BTN_SECONDARY}>Cancelar</button>
                        <button type="submit" disabled={processing} className={THEME_BTN_PRIMARY}>
                            <Save className="w-4 h-4" /> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body,
    );
}
