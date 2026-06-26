import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Clock, User, FileText, Camera, X, Save, Calculator, HelpCircle } from 'lucide-react';
import axios from 'axios';
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

const FORM_INICIAL = {
    rh_colaborador_id: '',
    fecha_evento: new Date().toISOString().slice(0, 10),
    motivo: 'Salida Personal',
    motivo_otro: '',
    hora_salida: '',
    evidencia_foto_salida: null,
    hora_regreso: '',
    evidencia_foto_regreso: null,
};

const MOTIVOS_SUGERIDOS = [
    'Salida Personal',
    'Trámite de Gobierno',
    'Cita Médica Personal',
    'Asunto Familiar',
    'Urgencia Médica',
    'Otro',
];

export default function ModalFormSalidaPersonal({
    abierto,
    onCerrar,
    registro = null,
    colaboradores = [],
    puedeEditar = true,
}) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ ...FORM_INICIAL });

    const [fotoSalidaPreview, setFotoSalidaPreview] = useState(null);
    const [fotoRegresoPreview, setFotoRegresoPreview] = useState(null);
    const [previewCalculos, setPreviewCalculos] = useState({
        minutos_ausente: 0,
        monto_a_deducir: 0,
        salario_por_minuto_snapshot: 0,
    });
    const [cargandoPreview, setCargandoPreview] = useState(false);

    const colaboradorSel = useMemo(() => {
        return colaboradores.find((c) => String(c.id) === String(data.rh_colaborador_id));
    }, [colaboradores, data.rh_colaborador_id]);

    // Cleanup previews on unmount / change
    useEffect(() => {
        return () => {
            if (fotoSalidaPreview) URL.revokeObjectURL(fotoSalidaPreview);
            if (fotoRegresoPreview) URL.revokeObjectURL(fotoRegresoPreview);
        };
    }, [fotoSalidaPreview, fotoRegresoPreview]);

    // Cargar registro al abrir para edición
    useEffect(() => {
        if (!abierto) return;

        if (registro) {
            const esOtroMotivo = !MOTIVOS_SUGERIDOS.includes(registro.motivo);
            setData({
                rh_colaborador_id: registro.rh_colaborador_id || '',
                fecha_evento: registro.fecha_evento?.slice?.(0, 10) || registro.fecha_evento || '',
                motivo: esOtroMotivo ? 'Otro' : (registro.motivo || 'Salida Personal'),
                motivo_otro: esOtroMotivo ? (registro.motivo || '') : '',
                hora_salida: registro.hora_salida ? registro.hora_salida.slice(0, 5) : '',
                evidencia_foto_salida: null,
                hora_regreso: registro.hora_regreso ? registro.hora_regreso.slice(0, 5) : '',
                evidencia_foto_regreso: null,
            });
            setFotoSalidaPreview(registro.foto_salida_url || null);
            setFotoRegresoPreview(registro.foto_regreso_url || null);
        } else {
            reset();
            clearErrors();
            setFotoSalidaPreview(null);
            setFotoRegresoPreview(null);
            setPreviewCalculos({ minutos_ausente: 0, monto_a_deducir: 0, salario_por_minuto_snapshot: 0 });
        }
    }, [abierto, registro]);

    // Obtener vista previa de cálculos
    useEffect(() => {
        if (!abierto || !data.rh_colaborador_id || !data.hora_salida) {
            setPreviewCalculos({
                minutos_ausente: 0,
                monto_a_deducir: 0,
                salario_por_minuto_snapshot: (colaboradorSel?.salario_diario > 0 ? (colaboradorSel.salario_diario / 480) : colaboradorSel?.salario_por_minuto) || 0,
            });
            return;
        }

        const controller = new AbortController();
        const fetchPreview = async () => {
            setCargandoPreview(true);
            try {
                const res = await axios.post(
                    route('rh.salidas_personales.preview_calculos'),
                    {
                        rh_colaborador_id: data.rh_colaborador_id,
                        fecha_evento: data.fecha_evento,
                        hora_salida: data.hora_salida,
                        hora_regreso: data.hora_regreso || null,
                    },
                    { signal: controller.signal }
                );
                setPreviewCalculos(res.data);
            } catch (err) {
                if (!axios.isCancel(err)) {
                    console.error('Error al previsualizar cálculos', err);
                }
            } finally {
                setCargandoPreview(false);
            }
        };

        const timer = setTimeout(fetchPreview, 300);
        return () => {
            timer && clearTimeout(timer);
            controller.abort();
        };
    }, [data.rh_colaborador_id, data.fecha_evento, data.hora_salida, data.hora_regreso, abierto, colaboradorSel]);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (registro && !puedeEditar) return;

        const motivoFinal = data.motivo === 'Otro' ? data.motivo_otro : data.motivo;

        const formData = new FormData();
        formData.append('fecha_evento', data.fecha_evento);
        formData.append('motivo', motivoFinal);
        formData.append('hora_salida', data.hora_salida);

        if (data.evidencia_foto_salida) {
            formData.append('evidencia_foto_salida', data.evidencia_foto_salida);
        }

        if (data.hora_regreso) {
            formData.append('hora_regreso', data.hora_regreso);
        }

        if (data.evidencia_foto_regreso) {
            formData.append('evidencia_foto_regreso', data.evidencia_foto_regreso);
        }

        if (registro) {
            // Spoofing PUT request
            formData.append('_method', 'PUT');
            router.post(route('rh.salidas_personales.update', registro.id), formData, {
                onSuccess: () => {
                    onCerrar();
                },
            });
        } else {
            formData.append('rh_colaborador_id', data.rh_colaborador_id);
            router.post(route('rh.salidas_personales.store'), formData, {
                onSuccess: () => {
                    onCerrar();
                },
            });
        }
    };

    if (!abierto) return null;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6 overflow-y-auto z-50`} onClick={onCerrar}>
            <GeliaLoader isVisible={processing} message="Guardando registro_" />
            <div className={`${THEME_MODAL_SHELL} max-w-4xl modal-pop text-left`} onClick={(e) => e.stopPropagation()}>
                <div className="p-6 md:p-8 border-b theme-border flex justify-between items-center">
                    <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-3 m-0">
                        <Clock className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        {registro ? 'Actualizar Salida Personal' : 'Nuevo Registro de Salida Personal'}
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

                    {/* Colaborador */}
                    <section>
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                            <User className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Colaborador
                        </h3>
                        {registro ? (
                            <div className="p-4 rounded-2xl theme-element border theme-border">
                                <p className="text-sm font-bold theme-text-main m-0">
                                    {nombreCompletoColaborador(registro.colaborador)}
                                </p>
                                <p className="text-[10px] theme-text-muted uppercase font-bold m-0 mt-1">
                                    {registro.colaborador?.departamento?.nombre || '—'} · {registro.colaborador?.area?.nombre || '—'}
                                </p>
                            </div>
                        ) : (
                            <>
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
                                        <div><span className="theme-text-muted uppercase font-black">Departamento:</span> {colaboradorSel.departamento?.nombre || '—'}</div>
                                        <div><span className="theme-text-muted uppercase font-black">Área:</span> {colaboradorSel.area?.nombre || '—'}</div>
                                        <div><span className="theme-text-muted uppercase font-black">Salario/min:</span> {formatoMoneda((colaboradorSel?.salario_diario > 0 ? (colaboradorSel.salario_diario / 480) : colaboradorSel?.salario_por_minuto) || 0, 4)}</div>
                                    </div>
                                )}
                            </>
                        )}
                    </section>

                    {/* Datos de Salida */}
                    <section className="space-y-4">
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2 border-b theme-border pb-2">
                            <Clock className="w-4 h-4 text-rose-500" /> Datos de Salida
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Campo label="Fecha evento *" error={errors.fecha_evento}>
                                <input
                                    type="date"
                                    value={data.fecha_evento}
                                    onChange={(e) => setData('fecha_evento', e.target.value)}
                                    required
                                    className={THEME_INPUT}
                                    disabled={registro && !puedeEditar}
                                />
                            </Campo>
                            <Campo label="Hora de salida (reloj checador) *" error={errors.hora_salida}>
                                <input
                                    type="time"
                                    value={data.hora_salida}
                                    onChange={(e) => setData('hora_salida', e.target.value)}
                                    required
                                    className={THEME_INPUT}
                                    disabled={registro && !puedeEditar}
                                />
                            </Campo>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Campo label="Motivo o tipo de salida *" error={errors.motivo}>
                                <select
                                    value={data.motivo}
                                    onChange={(e) => setData('motivo', e.target.value)}
                                    required
                                    className={THEME_SELECT}
                                    disabled={registro && !puedeEditar}
                                >
                                    {MOTIVOS_SUGERIDOS.map((m) => (
                                        <option key={m} value={m}>{m}</option>
                                    ))}
                                </select>
                            </Campo>
                            {data.motivo === 'Otro' && (
                                <Campo label="Especificar motivo *" error={errors.motivo_otro}>
                                    <input
                                        type="text"
                                        value={data.motivo_otro}
                                        onChange={(e) => setData('motivo_otro', e.target.value)}
                                        required
                                        placeholder="Escribe el motivo..."
                                        className={THEME_INPUT}
                                        disabled={registro && !puedeEditar}
                                    />
                                </Campo>
                            )}
                        </div>

                        {/* Evidencia Foto Salida */}
                        <Campo label={registro ? "Actualizar foto de salida (opcional)" : "Evidencia fotográfica de salida *"} error={errors.evidencia_foto_salida}>
                            <div className="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
                                <label className="flex items-center gap-2 px-4 py-3 rounded-2xl border theme-border theme-element theme-text-muted hover:theme-text-main cursor-pointer text-[10px] font-black uppercase transition-all">
                                    <Camera className="w-4 h-4" /> Seleccionar imagen
                                    <input
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) => {
                                            if (e.target.files[0]) {
                                                const file = e.target.files[0];
                                                setData('evidencia_foto_salida', file);
                                                setFotoSalidaPreview(URL.createObjectURL(file));
                                            }
                                        }}
                                        className="hidden"
                                        disabled={registro && !puedeEditar}
                                    />
                                </label>
                                {fotoSalidaPreview && (
                                    <div className="relative group rounded-xl overflow-hidden border theme-border w-24 h-24">
                                        <img src={fotoSalidaPreview} alt="Foto salida" className="w-full h-full object-cover" />
                                        {(!registro || puedeEditar) && (
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setData('evidencia_foto_salida', null);
                                                    setFotoSalidaPreview(null);
                                                }}
                                                className="absolute top-1 right-1 bg-black/75 hover:bg-black text-white p-1 rounded-full"
                                            >
                                                <X className="w-3 h-3" />
                                            </button>
                                        )}
                                    </div>
                                )}
                            </div>
                        </Campo>
                    </section>

                    {/* Datos de Regreso (Nullable inicialmente) */}
                    <section className="space-y-4">
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2 border-b theme-border pb-2">
                            <Clock className="w-4 h-4 text-emerald-500" /> Registro de Reingreso (Regreso)
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Campo label="Hora de regreso (reloj checador)" error={errors.hora_regreso}>
                                <input
                                    type="time"
                                    value={data.hora_regreso}
                                    onChange={(e) => setData('hora_regreso', e.target.value)}
                                    className={THEME_INPUT}
                                />
                            </Campo>
                        </div>

                        {/* Evidencia Foto Regreso */}
                        <Campo label={registro?.foto_regreso_url ? "Actualizar foto de regreso (opcional)" : "Evidencia fotográfica de regreso"} error={errors.evidencia_foto_regreso}>
                            <div className="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
                                <label className="flex items-center gap-2 px-4 py-3 rounded-2xl border theme-border theme-element theme-text-muted hover:theme-text-main cursor-pointer text-[10px] font-black uppercase transition-all">
                                    <Camera className="w-4 h-4" /> Seleccionar imagen
                                    <input
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) => {
                                            if (e.target.files[0]) {
                                                const file = e.target.files[0];
                                                setData('evidencia_foto_regreso', file);
                                                setFotoRegresoPreview(URL.createObjectURL(file));
                                            }
                                        }}
                                        className="hidden"
                                    />
                                </label>
                                {fotoRegresoPreview && (
                                    <div className="relative group rounded-xl overflow-hidden border theme-border w-24 h-24">
                                        <img src={fotoRegresoPreview} alt="Foto regreso" className="w-full h-full object-cover" />
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setData('evidencia_foto_regreso', null);
                                                setFotoRegresoPreview(null);
                                            }}
                                            className="absolute top-1 right-1 bg-black/75 hover:bg-black text-white p-1 rounded-full"
                                        >
                                            <X className="w-3 h-3" />
                                        </button>
                                    </div>
                                )}
                            </div>
                        </Campo>
                    </section>

                    {/* Previsualización del cálculo */}
                    {data.hora_salida && data.hora_regreso && (
                        <section className="p-4 rounded-2xl border theme-border bg-black/[0.02] dark:bg-white/[0.02] relative">
                            {cargandoPreview && (
                                <div className="absolute inset-0 bg-white/50 dark:bg-black/50 flex items-center justify-center rounded-2xl z-10">
                                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Calculando...</span>
                                </div>
                            )}
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-3 flex items-center gap-2">
                                <Calculator className="w-3.5 h-3.5" /> Deducción estimada
                            </p>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <CalcItem label="Minutos ausente" value={`${previewCalculos.minutos_ausente} min`} />
                                <CalcItem label="Salario por minuto" value={formatoMoneda(previewCalculos.salario_por_minuto_snapshot || 0, 4)} />
                                <CalcItem label="Monto a deducir" value={formatoMoneda(previewCalculos.monto_a_deducir || 0)} highlight />
                            </div>
                        </section>
                    )}

                    <div className="flex justify-end gap-3 pt-4 border-t theme-border">
                        <button type="button" onClick={onCerrar} className={THEME_BTN_SECONDARY}>Cancelar</button>
                        <button type="submit" disabled={processing} className={THEME_BTN_PRIMARY}>
                            <Save className="w-4 h-4" /> {registro ? 'Guardar cambios' : 'Registrar Salida'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}

function Campo({ label, error, children }) {
    return (
        <div className="space-y-1.5 w-full">
            <label className={THEME_LABEL}>{label}</label>
            {children}
            {error && <p className="text-red-500 text-[10px] font-bold ml-2 mt-1">{error}</p>}
        </div>
    );
}

function CalcItem({ label, value, highlight = false }) {
    return (
        <div>
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-0.5">{label}</p>
            <p className={`m-0 ${highlight ? 'text-base font-bold text-red-500' : 'text-sm font-bold'} theme-text-main`}>{value}</p>
        </div>
    );
}
