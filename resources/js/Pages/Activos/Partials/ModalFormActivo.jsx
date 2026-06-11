import React, { useEffect, useMemo, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { Save, ChevronLeft, ChevronRight, CheckCircle2, UserPlus } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import DynamicActivoFields from './DynamicActivoFields';
import GaleriaFotosActivo from './GaleriaFotosActivo';
import SelectorUsuarioGelia from './SelectorUsuarioGelia';
import SelectorActivoPadre from './SelectorActivoPadre';
import ActivosModalShell from './ActivosModalShell';
import useDispositivoCampo from './useDispositivoCampo';
import { validarAtributosActivo } from './validarAtributosActivo';
import { aplicarDefaultsAtributos } from './defaultsAtributosActivo';
import {
    INPUT_CLASS, SELECT_CLASS, TEXTAREA_CLASS, LABEL_CLASS,
    BTN_PRIMARY_CLASS, BTN_SECONDARY_CLASS, BTN_TOUCH_CLASS,
} from './activosFormStyles';

const STORAGE_CONTINUO = 'activos_registro_continuo';
const STORAGE_RESPONSABLE = 'activos_registro_responsable';

const PASOS_BASE = [
    { id: 'asignar', label: 'Asignar' },
    { id: 1, label: 'Tipo' },
    { id: 2, label: 'Datos' },
    { id: 3, label: 'Fotos' },
    { id: 4, label: 'Atributos' },
];

function leerResponsablePersistido() {
    if (typeof window === 'undefined') return { user_id: '', notas: '', condiciones_entrega: '' };
    try {
        const raw = localStorage.getItem(STORAGE_RESPONSABLE);
        if (!raw) return { user_id: '', notas: '', condiciones_entrega: '' };
        return JSON.parse(raw);
    } catch {
        return { user_id: '', notas: '', condiciones_entrega: '' };
    }
}

function guardarResponsablePersistido({ user_id, notas, condiciones_entrega }) {
    if (typeof window === 'undefined') return;
    localStorage.setItem(STORAGE_RESPONSABLE, JSON.stringify({ user_id, notas, condiciones_entrega }));
}

function limpiarResponsablePersistido() {
    if (typeof window === 'undefined') return;
    localStorage.removeItem(STORAGE_RESPONSABLE);
}

export default function ModalFormActivo({ abierto, onCerrar, tipos = [], categorias = [], departamentos = [], activo = null }) {
    const esEdicion = !!activo;
    const { esMovil } = useDispositivoCampo();
    const { flash, auth } = usePage().props;

    const puedeAsignar = useMemo(() => {
        const roles = auth?.user?.roles || [];
        const isAdmin = roles.includes('Admin') || roles.includes('Super admin (admin)') || roles.includes('Super Admin');
        return auth?.user?.permissions?.includes('activos.asignar') || isAdmin;
    }, [auth]);

    const pasosWizard = useMemo(() => {
        if (esEdicion || !puedeAsignar) {
            return PASOS_BASE.filter((p) => p.id !== 'asignar');
        }
        return PASOS_BASE;
    }, [esEdicion, puedeAsignar]);

    const [paso, setPaso] = useState(pasosWizard[0]?.id === 'asignar' ? 'asignar' : 1);
    const [registroContinuo, setRegistroContinuo] = useState(() => {
        if (typeof window === 'undefined') return false;
        return localStorage.getItem(STORAGE_CONTINUO) === '1';
    });
    const [ultimoFolio, setUltimoFolio] = useState(null);
    const [erroresLocales, setErroresLocales] = useState({});

    const responsableInicial = useMemo(() => {
        if (esEdicion || !registroContinuo) {
            return { user_id: '', notas: '', condiciones_entrega: '' };
        }
        return leerResponsablePersistido();
    }, [esEdicion, registroContinuo, abierto]);

    const { data, setData, post, put, processing, errors, reset, clearErrors, transform } = useForm({
        catalogo_tipo_activo_id: '',
        catalogo_categoria_activo_id: '',
        activo_padre_id: '',
        departamento_id: '',
        area_id: '',
        nombre: '',
        descripcion: '',
        atributos: {},
        fecha_adquisicion: '',
        fecha_vencimiento: '',
        valor: '',
        fotos: [],
        registro_continuo: false,
        user_id: responsableInicial.user_id || '',
        notas: responsableInicial.notas || '',
        condiciones_entrega: responsableInicial.condiciones_entrega || '',
    });

    const tipoSeleccionado = useMemo(
        () => tipos.find((t) => String(t.id) === String(data.catalogo_tipo_activo_id)),
        [tipos, data.catalogo_tipo_activo_id]
    );

    const esAccesorio = tipoSeleccionado?.slug === 'accesorio';
    const usarWizard = esMovil && !esEdicion;
    const camposTipo = tipoSeleccionado?.esquema_atributos?.fields || [];
    const erroresCombinados = { ...errors, ...erroresLocales };

    const indicePasoActual = pasosWizard.findIndex((p) => p.id === paso);
    const pasoNumerico = indicePasoActual >= 0 ? indicePasoActual + 1 : 1;

    const resetCamposVariables = (tipoId, deptId) => {
        const responsable = registroContinuo ? leerResponsablePersistido() : { user_id: '', notas: '', condiciones_entrega: '' };

        setData({
            catalogo_tipo_activo_id: tipoId,
            catalogo_categoria_activo_id: '',
            activo_padre_id: '',
            departamento_id: deptId,
            area_id: '',
            nombre: '',
            descripcion: '',
            atributos: aplicarDefaultsAtributos(campos, {}),
            fecha_adquisicion: '',
            fecha_vencimiento: '',
            valor: '',
            fotos: [],
            registro_continuo: registroContinuo,
            user_id: responsable.user_id || '',
            notas: responsable.notas || '',
            condiciones_entrega: responsable.condiciones_entrega || '',
        });
        setPaso(pasosWizard.find((p) => p.id !== 'asignar')?.id || 1);
        clearErrors();
        setErroresLocales({});
    };

    useEffect(() => {
        if (!abierto) return;

        if (activo) {
            setData({
                catalogo_tipo_activo_id: activo.catalogo_tipo_activo_id || '',
                catalogo_categoria_activo_id: activo.catalogo_categoria_activo_id || '',
                activo_padre_id: activo.activo_padre_id || '',
                departamento_id: activo.departamento_id || '',
                area_id: activo.area_id || '',
                nombre: activo.nombre || '',
                descripcion: activo.descripcion || '',
                atributos: activo.atributos || {},
                fecha_adquisicion: activo.fecha_adquisicion?.substring?.(0, 10) || activo.fecha_adquisicion || '',
                fecha_vencimiento: activo.fecha_vencimiento?.substring?.(0, 10) || activo.fecha_vencimiento || '',
                valor: activo.valor || '',
                fotos: [],
                registro_continuo: false,
                user_id: '',
                notas: '',
                condiciones_entrega: '',
            });
        } else {
            const responsable = registroContinuo ? leerResponsablePersistido() : { user_id: '', notas: '', condiciones_entrega: '' };
            reset();
            setData((prev) => ({
                ...prev,
                user_id: responsable.user_id || '',
                notas: responsable.notas || '',
                condiciones_entrega: responsable.condiciones_entrega || '',
            }));
            setPaso(pasosWizard[0]?.id === 'asignar' ? 'asignar' : 1);
            setUltimoFolio(null);
        }
        clearErrors();
        setErroresLocales({});
    }, [abierto, activo]);

    useEffect(() => {
        if (flash?.activo_registrado?.folio) {
            setUltimoFolio(flash.activo_registrado.folio);
        }
    }, [flash?.activo_registrado?.folio]);

    useEffect(() => {
        if (registroContinuo && !esEdicion) {
            guardarResponsablePersistido({
                user_id: data.user_id,
                notas: data.notas,
                condiciones_entrega: data.condiciones_entrega,
            });
        }
    }, [data.user_id, data.notas, data.condiciones_entrega, registroContinuo, esEdicion]);

    if (!abierto) return null;

    const tieneFotos = (data.fotos || []).length > 0;

    const validarPaso = (pasoActual = paso) => {
        const locales = {};

        if (pasoActual === 'asignar') {
            return true;
        }

        if (pasoActual === 1 || !usarWizard) {
            if (!data.catalogo_tipo_activo_id) locales.catalogo_tipo_activo_id = 'Selecciona un tipo.';
            if (!data.departamento_id) locales.departamento_id = 'Selecciona un departamento.';
            if (esAccesorio && !data.activo_padre_id) {
                locales.activo_padre_id = 'Vincula el accesorio a un activo principal.';
            }
        }
        if (pasoActual === 2 || !usarWizard) {
            if (!data.nombre?.trim()) locales.nombre = 'El nombre es obligatorio.';
        }
        if (pasoActual === 4 || !usarWizard) {
            Object.assign(locales, validarAtributosActivo(camposTipo, data.atributos));
        }

        setErroresLocales(locales);
        return Object.keys(locales).length === 0;
    };

    const avanzarPaso = () => {
        if (validarPaso(paso)) {
            const idx = pasosWizard.findIndex((p) => p.id === paso);
            if (idx >= 0 && idx < pasosWizard.length - 1) {
                setPaso(pasosWizard[idx + 1].id);
            }
        }
    };

    const retrocederPaso = () => {
        const idx = pasosWizard.findIndex((p) => p.id === paso);
        if (idx > 0) {
            setPaso(pasosWizard[idx - 1].id);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        if (!validarPaso(usarWizard ? pasosWizard[pasosWizard.length - 1].id : 2)) return;

        const accion = esEdicion ? put : post;
        const ruta = esEdicion ? route('activos.update', activo.id) : route('activos.store');

        transform((formData) => {
            const atributosRaw = typeof formData.atributos === 'object'
                ? formData.atributos
                : (JSON.parse(formData.atributos || '{}') || {});

            return {
                ...formData,
                atributos: JSON.stringify(atributosRaw),
                registro_continuo: !esEdicion && registroContinuo,
            };
        });

        accion(ruta, {
            onSuccess: () => {
                if (!esEdicion && registroContinuo) {
                    resetCamposVariables(data.catalogo_tipo_activo_id, data.departamento_id);
                    return;
                }
                onCerrar();
                reset();
            },
            forceFormData: tieneFotos,
            preserveScroll: true,
        });
    };

    const toggleContinuo = () => {
        const next = !registroContinuo;
        setRegistroContinuo(next);
        localStorage.setItem(STORAGE_CONTINUO, next ? '1' : '0');
        setData('registro_continuo', next);
        if (!next) {
            limpiarResponsablePersistido();
        } else {
            guardarResponsablePersistido({
                user_id: data.user_id,
                notas: data.notas,
                condiciones_entrega: data.condiciones_entrega,
            });
        }
    };

    const renderAsignacionOpcional = () => (
        <div className="border theme-border rounded-xl p-4 space-y-3 bg-black/[0.02] dark:bg-white/[0.02]">
            <div className="flex items-center gap-2">
                <UserPlus className="w-4 h-4 shrink-0" style={{ color: 'var(--color-primario)' }} />
                <p className={`${LABEL_CLASS} m-0`}>Asignar a (opcional)</p>
            </div>
            <p className="text-xs theme-text-muted m-0">
                Puedes asignar el activo al guardar. Con registro continuo, el responsable se mantiene entre registros.
            </p>
            <div>
                <label className={LABEL_CLASS}>Usuario Gelia</label>
                <SelectorUsuarioGelia
                    value={data.user_id}
                    onChange={(id) => setData('user_id', id)}
                    departamentoId={data.departamento_id}
                    placeholder="Buscar responsable..."
                />
                {erroresCombinados.user_id && <p className="text-red-500 text-xs mt-1">{erroresCombinados.user_id}</p>}
            </div>
            {data.user_id && (
                <>
                    <div>
                        <label className={LABEL_CLASS}>Condiciones de entrega</label>
                        <textarea value={data.condiciones_entrega} onChange={(e) => setData('condiciones_entrega', e.target.value)} rows={2} className={TEXTAREA_CLASS} placeholder="Ej. Buen estado, sin observaciones" />
                    </div>
                    <div>
                        <label className={LABEL_CLASS}>Notas de asignación</label>
                        <textarea value={data.notas} onChange={(e) => setData('notas', e.target.value)} rows={2} className={TEXTAREA_CLASS} />
                    </div>
                </>
            )}
        </div>
    );

    const renderPasoAsignar = () => renderAsignacionOpcional();

    const renderPaso1 = () => (
        <div className="grid grid-cols-1 gap-4">
            {!esEdicion && puedeAsignar && !usarWizard && renderAsignacionOpcional()}
            <div>
                <label className={LABEL_CLASS}>Tipo *</label>
                <select value={data.catalogo_tipo_activo_id} onChange={(e) => {
                    const newTipoId = e.target.value;
                    const newTipo = tipos.find(t => String(t.id) === String(newTipoId));
                    const newFields = newTipo?.esquema_atributos?.fields || [];
                    setData(prev => ({
                        ...prev,
                        catalogo_tipo_activo_id: newTipoId,
                        atributos: aplicarDefaultsAtributos(newFields, {})
                    }));
                }} disabled={esEdicion} className={SELECT_CLASS}>
                    <option value="">Seleccionar...</option>
                    {tipos.map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                </select>
                {erroresCombinados.catalogo_tipo_activo_id && <p className="text-red-500 text-xs mt-1">{erroresCombinados.catalogo_tipo_activo_id}</p>}
            </div>
            {categorias.length > 0 && (
                <div>
                    <label className={LABEL_CLASS}>Categoría</label>
                    <select value={data.catalogo_categoria_activo_id} onChange={(e) => setData('catalogo_categoria_activo_id', e.target.value)} className={SELECT_CLASS}>
                        <option value="">Sin categoría</option>
                        {categorias.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                    </select>
                </div>
            )}
            <div>
                <label className={LABEL_CLASS}>Departamento *</label>
                <select value={data.departamento_id} onChange={(e) => setData('departamento_id', e.target.value)} className={SELECT_CLASS}>
                    <option value="">Seleccionar...</option>
                    {departamentos.map((d) => <option key={d.id} value={d.id}>{d.nombre}</option>)}
                </select>
                {erroresCombinados.departamento_id && <p className="text-red-500 text-xs mt-1">{erroresCombinados.departamento_id}</p>}
            </div>
            {esAccesorio && (
                <div>
                    <label className={LABEL_CLASS}>Activo principal *</label>
                    <SelectorActivoPadre
                        value={data.activo_padre_id}
                        onChange={(id) => setData('activo_padre_id', id)}
                        excluirId={activo?.id}
                        placeholder="Buscar equipo al que pertenece..."
                    />
                    {erroresCombinados.activo_padre_id && <p className="text-red-500 text-xs mt-1">{erroresCombinados.activo_padre_id}</p>}
                </div>
            )}
        </div>
    );

    const renderPaso2 = () => (
        <div className="grid grid-cols-1 gap-4">
            <div>
                <label className={LABEL_CLASS}>Nombre *</label>
                <input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className={INPUT_CLASS} />
                {erroresCombinados.nombre && <p className="text-red-500 text-xs mt-1">{erroresCombinados.nombre}</p>}
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label className={LABEL_CLASS}>Fecha adquisición</label>
                    <input type="date" value={data.fecha_adquisicion} onChange={(e) => setData('fecha_adquisicion', e.target.value)} className={INPUT_CLASS} />
                </div>
                <div>
                    <label className={LABEL_CLASS}>Valor</label>
                    <input type="number" step="0.01" value={data.valor} onChange={(e) => setData('valor', e.target.value)} className={INPUT_CLASS} />
                </div>
            </div>
            <div>
                <label className={LABEL_CLASS}>Descripción</label>
                <textarea value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} rows={2} className={TEXTAREA_CLASS} />
            </div>
        </div>
    );

    const renderPaso3 = () => (
        <GaleriaFotosActivo
            fotosExistentes={activo?.fotos || []}
            nuevasFotos={data.fotos}
            onChangeNuevas={(fotos) => setData('fotos', fotos)}
            maxFotos={5}
            activoId={activo?.id}
            modoCapturaCampo
        />
    );

    const renderPaso4 = () => (
        tipoSeleccionado ? (
            <DynamicActivoFields
                fields={camposTipo}
                values={data.atributos}
                onChange={(attrs) => setData('atributos', attrs)}
                errors={erroresCombinados}
                tipoActivoId={data.catalogo_tipo_activo_id}
            />
        ) : (
            <p className="text-sm theme-text-muted italic">Selecciona un tipo de activo en el paso correspondiente.</p>
        )
    );

    const renderFormularioCompleto = () => (
        <>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-5">
                {renderPaso1()}
                <div className="md:col-span-2">{renderPaso2()}</div>
            </div>
            {renderPaso3()}
            {tipoSeleccionado && (
                <div className="border-t theme-border pt-5">
                    <p className={`${LABEL_CLASS} mb-3`}>Atributos — {tipoSeleccionado.nombre}</p>
                    {renderPaso4()}
                </div>
            )}
        </>
    );

    const renderContenidoPaso = () => {
        if (paso === 'asignar') return renderPasoAsignar();
        if (paso === 1) return renderPaso1();
        if (paso === 2) return renderPaso2();
        if (paso === 3) return renderPaso3();
        if (paso === 4) return renderPaso4();
        return null;
    };

    const renderWizard = () => (
        <>
            <div className="flex gap-2 mb-4">
                {pasosWizard.map((p, idx) => (
                    <div
                        key={String(p.id)}
                        className={`flex-1 h-1.5 rounded-full ${idx <= indicePasoActual ? '' : 'bg-black/10 dark:bg-white/10'}`}
                        style={idx <= indicePasoActual ? { backgroundColor: 'var(--color-primario)' } : undefined}
                    />
                ))}
            </div>
            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-4">
                Paso {pasoNumerico} de {pasosWizard.length} — {pasosWizard[indicePasoActual]?.label}
            </p>
            {renderContenidoPaso()}
        </>
    );

    const ultimoPaso = pasosWizard[pasosWizard.length - 1]?.id;

    return (
        <ActivosModalShell
            title={esEdicion ? 'Editar activo' : 'Registrar activo'}
            onClose={onCerrar}
            size={esMovil && !esEdicion ? 'max-w-full sm:max-w-3xl' : 'max-w-3xl'}
            loader={<GeliaLoader isVisible={processing} message="Guardando_" />}
        >
            <form onSubmit={(e) => e.preventDefault()} className="flex flex-col flex-1 min-h-0">
                <div className="gelia-modal-body p-5 md:p-8 space-y-5">
                    {ultimoFolio && !esEdicion && (
                        <div className="flex items-center gap-2 px-4 py-3 rounded-xl border border-green-500/30 bg-green-500/10 text-green-800 dark:text-green-300 text-sm">
                            <CheckCircle2 className="w-4 h-4 shrink-0" />
                            Último guardado: <strong className="font-mono">{ultimoFolio}</strong>
                        </div>
                    )}

                    {usarWizard ? renderWizard() : renderFormularioCompleto()}

                    {!esEdicion && (
                        <label className="flex items-center gap-3 pt-2 cursor-pointer">
                            <input type="checkbox" checked={registroContinuo} onChange={toggleContinuo} className="rounded w-5 h-5" />
                            <span className="text-sm theme-text-main">Registro continuo (mantener tipo, departamento y responsable)</span>
                        </label>
                    )}
                </div>

                <div className="gelia-modal-footer p-5 md:p-6 flex flex-col-reverse sm:flex-row justify-end gap-3">
                    {usarWizard && indicePasoActual > 0 && (
                        <button type="button" onClick={retrocederPaso} className={`${BTN_SECONDARY_CLASS} w-full sm:w-auto min-h-[44px]`}>
                            <ChevronLeft className="w-4 h-4" /> Anterior
                        </button>
                    )}
                    <button type="button" onClick={onCerrar} className={`${BTN_SECONDARY_CLASS} w-full sm:w-auto min-h-[44px]`}>
                        Cancelar
                    </button>
                    {usarWizard && paso !== ultimoPaso ? (
                        <button type="button" onClick={avanzarPaso} className={`${BTN_TOUCH_CLASS} w-full sm:w-auto justify-center`}>
                            Siguiente <ChevronRight className="w-4 h-4" />
                        </button>
                    ) : (
                        <button type="button" onClick={submit} disabled={processing} className={`${BTN_TOUCH_CLASS} w-full sm:w-auto justify-center`}>
                            <Save className="w-4 h-4 shrink-0" /> Guardar
                        </button>
                    )}
                </div>
            </form>
        </ActivosModalShell>
    );
}
