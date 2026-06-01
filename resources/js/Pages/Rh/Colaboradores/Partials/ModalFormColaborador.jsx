import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import {
    User, Building2, Briefcase, DollarSign, Link2, X, Save, Calculator, RefreshCw,
} from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_INPUT,
    THEME_SELECT,
    THEME_LABEL,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_BTN_ICON,
} from '../../../../utils/geliaTheme';
import { calcularSalariosPreview, formatoMoneda, formatoDecimal } from '../../../../utils/formatoMoneda';

const FORM_INICIAL = {
    user_id: '',
    departamento_id: '',
    area_id: '',
    nombre: '',
    apellido_paterno: '',
    apellido_materno: '',
    catalogo_puesto_id: '',
    salario_base: '',
    bono_productividad: '0',
    bono_puntualidad: '0',
    horas_laboradas_oficiales: '8',
    activo: true,
    bonos: [],
};

export default function ModalFormColaborador({
    abierto,
    onCerrar,
    colaborador = null,
    departamentos = [],
    puestos = [],
    usuarios = [],
    configuracion = {},
    puedeVincular = false,
}) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({ ...FORM_INICIAL });

    const areasDisponibles = useMemo(() => {
        if (!data.departamento_id) return [];
        const depto = departamentos.find((d) => String(d.id) === String(data.departamento_id));
        return depto?.areas || [];
    }, [departamentos, data.departamento_id]);

    const preview = useMemo(() => calcularSalariosPreview(data, configuracion), [data, configuracion]);

    const bonosElegibles = useMemo(() => {
        if (!data.catalogo_puesto_id) return [];
        const puesto = puestos.find((p) => String(p.id) === String(data.catalogo_puesto_id));
        return puesto?.bonos || [];
    }, [puestos, data.catalogo_puesto_id]);

    const actualizarMontoBono = (bonoId, monto) => {
        const sid = String(bonoId);
        const actuales = [...(data.bonos || [])].filter((b) => String(b.catalogo_bono_id) !== sid);
        if (Number(monto) > 0) {
            actuales.push({ catalogo_bono_id: Number(bonoId), monto });
        }
        setData('bonos', actuales);
    };

    const montoBono = (bonoId) => {
        const item = (data.bonos || []).find((b) => String(b.catalogo_bono_id) === String(bonoId));
        return item?.monto ?? '';
    };

    useEffect(() => {
        if (!abierto) return;

        if (colaborador) {
            setData({
                user_id: colaborador.user_id || '',
                departamento_id: colaborador.departamento_id || '',
                area_id: colaborador.area_id || '',
                nombre: colaborador.nombre || '',
                apellido_paterno: colaborador.apellido_paterno || '',
                apellido_materno: colaborador.apellido_materno || '',
                catalogo_puesto_id: colaborador.catalogo_puesto_id || '',
                salario_base: colaborador.salario_base ?? '',
                bono_productividad: colaborador.bono_productividad ?? '0',
                bono_puntualidad: colaborador.bono_puntualidad ?? '0',
                horas_laboradas_oficiales: colaborador.horas_laboradas_oficiales ?? '8',
                activo: colaborador.activo ?? true,
                bonos: (colaborador.bonos || []).map((b) => ({
                    catalogo_bono_id: b.id,
                    monto: b.pivot?.monto ?? b.monto ?? 0,
                })),
            });
        } else {
            reset();
            clearErrors();
        }
    }, [abierto, colaborador]);

    const sincronizarUsuario = async () => {
        if (!data.user_id) return;

        try {
            const resp = await fetch(route('rh.colaboradores.sincronizar_usuario', data.user_id));
            if (!resp.ok) return;
            const json = await resp.json();
            setData((prev) => ({
                ...prev,
                nombre: json.nombre || prev.nombre,
                apellido_paterno: json.apellido_paterno || prev.apellido_paterno,
                apellido_materno: json.apellido_materno || prev.apellido_materno,
            }));
        } catch {
            // ignore
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = colaborador ? put : post;
        const ruta = colaborador
            ? route('rh.colaboradores.update', colaborador.id)
            : route('rh.colaboradores.store');

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
            <GeliaLoader isVisible={processing} message="Guardando colaborador_" />
            <div className={`${THEME_MODAL_SHELL} max-w-4xl modal-pop text-left`} onClick={(e) => e.stopPropagation()}>
                <div className="p-6 md:p-8 border-b theme-border flex justify-between items-center shrink-0">
                    <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-3 m-0">
                        <User className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        {colaborador ? 'Editar Perfil Laboral' : 'Alta de Colaborador RH'}
                    </h2>
                    <button type="button" onClick={onCerrar} className={THEME_BTN_ICON}>
                        <X className="w-6 h-6" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="gelia-modal-body p-6 md:p-8 custom-scrollbar space-y-8">
                    {colaborador && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 rounded-2xl theme-element border theme-border">
                            <div>
                                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Folio</p>
                                <p className="text-sm font-mono font-bold theme-text-main m-0">{colaborador.folio}</p>
                            </div>
                            <div>
                                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">UUID</p>
                                <p className="text-xs font-mono theme-text-muted m-0 break-all">{colaborador.uuid}</p>
                            </div>
                        </div>
                    )}

                    <section>
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                            <User className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Identificación
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <Campo label="Nombre(s) *" error={errors.nombre}>
                                <input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} required className={THEME_INPUT} />
                            </Campo>
                            <Campo label="Ap. Paterno" error={errors.apellido_paterno}>
                                <input value={data.apellido_paterno} onChange={(e) => setData('apellido_paterno', e.target.value)} className={THEME_INPUT} />
                            </Campo>
                            <Campo label="Ap. Materno" error={errors.apellido_materno}>
                                <input value={data.apellido_materno} onChange={(e) => setData('apellido_materno', e.target.value)} className={THEME_INPUT} />
                            </Campo>
                        </div>
                    </section>

                    <section>
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                            <Building2 className="w-4 h-4 text-purple-500" /> Organización
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <Campo label="Departamento *" error={errors.departamento_id}>
                                <select
                                    value={data.departamento_id}
                                    onChange={(e) => setData({ ...data, departamento_id: e.target.value, area_id: '' })}
                                    required
                                    className={THEME_SELECT}
                                >
                                    <option value="">Selecciona...</option>
                                    {departamentos.map((d) => (
                                        <option key={d.id} value={d.id}>{d.nombre}</option>
                                    ))}
                                </select>
                            </Campo>
                            <Campo label="Área" error={errors.area_id}>
                                <select
                                    value={data.area_id}
                                    onChange={(e) => setData('area_id', e.target.value)}
                                    className={THEME_SELECT}
                                    disabled={!data.departamento_id}
                                >
                                    <option value="">Sin área específica</option>
                                    {areasDisponibles.map((a) => (
                                        <option key={a.id} value={a.id}>{a.nombre}</option>
                                    ))}
                                </select>
                            </Campo>
                            <Campo label="Puesto / Cargo *" error={errors.catalogo_puesto_id}>
                                <select
                                    value={data.catalogo_puesto_id}
                                    onChange={(e) => setData({ ...data, catalogo_puesto_id: e.target.value, bonos: [] })}
                                    required
                                    className={THEME_SELECT}
                                >
                                    <option value="">Selecciona...</option>
                                    {puestos.map((p) => (
                                        <option key={p.id} value={p.id}>{p.nombre}</option>
                                    ))}
                                </select>
                            </Campo>
                        </div>
                    </section>

                    <section>
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                            <DollarSign className="w-4 h-4 text-emerald-500" /> Compensación
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <Campo label="Salario Base *" error={errors.salario_base}>
                                <input type="number" min="0" step="0.01" value={data.salario_base} onChange={(e) => setData('salario_base', e.target.value)} required className={THEME_INPUT} />
                            </Campo>
                            <Campo label="Bono Productividad" error={errors.bono_productividad}>
                                <input type="number" min="0" step="0.01" value={data.bono_productividad} onChange={(e) => setData('bono_productividad', e.target.value)} className={THEME_INPUT} />
                            </Campo>
                            <Campo label="Bono Puntualidad" error={errors.bono_puntualidad}>
                                <input type="number" min="0" step="0.01" value={data.bono_puntualidad} onChange={(e) => setData('bono_puntualidad', e.target.value)} className={THEME_INPUT} />
                            </Campo>
                            <Campo label="Horas Laboradas Oficiales *" error={errors.horas_laboradas_oficiales}>
                                <input type="number" min="0.5" max="24" step="0.25" value={data.horas_laboradas_oficiales} onChange={(e) => setData('horas_laboradas_oficiales', e.target.value)} required className={THEME_INPUT} />
                            </Campo>
                        </div>

                        {bonosElegibles.length > 0 && (
                            <div className="mt-4 p-4 rounded-2xl border theme-border">
                                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-3">Bonos adicionales del puesto</p>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {bonosElegibles.map((bono) => (
                                        <Campo key={bono.id} label={bono.nombre} error={errors[`bonos.${bono.id}`]}>
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={montoBono(bono.id)}
                                                onChange={(e) => actualizarMontoBono(bono.id, e.target.value)}
                                                placeholder="0.00"
                                                className={THEME_INPUT}
                                            />
                                        </Campo>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="mt-4 p-4 rounded-2xl border theme-border bg-black/[0.02] dark:bg-white/[0.02]">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-3 flex items-center gap-2">
                                <Calculator className="w-3.5 h-3.5" /> Valores calculados (periodo: {configuracion.dias_periodo_pago || 30} días)
                            </p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                                <CalcItem label="Salario Diario" value={formatoMoneda(preview.salario_diario)} />
                                <CalcItem label="Bono Prod. Diario" value={formatoMoneda(preview.bono_productividad_diario)} />
                                <CalcItem label="Bono Punt. Diario" value={formatoMoneda(preview.bono_puntualidad_diario)} />
                                <CalcItem label="Salario por Hora" value={formatoMoneda(preview.salario_por_hora)} />
                                <CalcItem label="Salario por Minuto" value={formatoDecimal(preview.salario_por_minuto, configuracion.decimales_salario_minuto || 8)} />
                            </div>
                        </div>
                    </section>

                    {puedeVincular && (
                        <section>
                            <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                                <Link2 className="w-4 h-4 text-blue-500" /> Vínculo de Cuenta (opcional)
                            </h3>
                            <div className="flex flex-col sm:flex-row gap-3">
                                <select value={data.user_id} onChange={(e) => setData('user_id', e.target.value)} className={`${THEME_SELECT} flex-1`}>
                                    <option value="">Sin cuenta vinculada</option>
                                    {usuarios.map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.name} {u.apellido_paterno || ''} — {u.email}
                                        </option>
                                    ))}
                                </select>
                                <button
                                    type="button"
                                    onClick={sincronizarUsuario}
                                    disabled={!data.user_id}
                                    className={`${THEME_BTN_SECONDARY} border theme-border flex items-center gap-2 disabled:opacity-40`}
                                >
                                    <RefreshCw className="w-4 h-4" /> Sincronizar datos
                                </button>
                            </div>
                            {errors.user_id && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.user_id}</p>}
                        </section>
                    )}

                    <label className="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" checked={!!data.activo} onChange={(e) => setData('activo', e.target.checked)} className="rounded" />
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-main">Colaborador activo</span>
                    </label>

                    <div className="flex justify-end gap-3 pt-4 border-t theme-border">
                        <button type="button" onClick={onCerrar} className={THEME_BTN_SECONDARY}>
                            Cancelar
                        </button>
                        <button type="submit" disabled={processing} className={THEME_BTN_PRIMARY}>
                            <Save className="w-4 h-4" /> {colaborador ? 'Guardar cambios' : 'Registrar colaborador'}
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

function CalcItem({ label, value }) {
    return (
        <div>
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-0.5">{label}</p>
            <p className="text-sm font-bold theme-text-main m-0">{value}</p>
        </div>
    );
}
