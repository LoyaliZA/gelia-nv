import React, { useEffect, useMemo, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { Save, ChevronLeft, ChevronRight, CheckCircle2 } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import DynamicActivoFields from './DynamicActivoFields';
import GaleriaFotosActivo from './GaleriaFotosActivo';
import ActivosModalShell from './ActivosModalShell';
import useDispositivoCampo from './useDispositivoCampo';
import { validarAtributosActivo } from './validarAtributosActivo';
import {
    INPUT_CLASS, SELECT_CLASS, TEXTAREA_CLASS, LABEL_CLASS,
    BTN_PRIMARY_CLASS, BTN_SECONDARY_CLASS, BTN_TOUCH_CLASS,
} from './activosFormStyles';

const STORAGE_CONTINUO = 'activos_registro_continuo';
const PASOS = [
    { id: 1, label: 'Tipo' },
    { id: 2, label: 'Datos' },
    { id: 3, label: 'Fotos' },
    { id: 4, label: 'Atributos' },
];

export default function ModalFormActivo({ abierto, onCerrar, tipos = [], departamentos = [], activo = null }) {
    const esEdicion = !!activo;
    const { esMovil } = useDispositivoCampo();
    const { flash } = usePage().props;
    const [paso, setPaso] = useState(1);
    const [registroContinuo, setRegistroContinuo] = useState(() => {
        if (typeof window === 'undefined') return false;
        return localStorage.getItem(STORAGE_CONTINUO) === '1';
    });
    const [ultimoFolio, setUltimoFolio] = useState(null);
    const [erroresLocales, setErroresLocales] = useState({});

    const { data, setData, post, put, processing, errors, reset, clearErrors, transform } = useForm({
        catalogo_tipo_activo_id: '',
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
    });

    const tipoSeleccionado = useMemo(
        () => tipos.find((t) => String(t.id) === String(data.catalogo_tipo_activo_id)),
        [tipos, data.catalogo_tipo_activo_id]
    );

    const usarWizard = esMovil && !esEdicion;
    const camposTipo = tipoSeleccionado?.esquema_atributos?.fields || [];
    const erroresCombinados = { ...errors, ...erroresLocales };

    const resetCamposVariables = (tipoId, deptId) => {
        setData({
            catalogo_tipo_activo_id: tipoId,
            departamento_id: deptId,
            area_id: '',
            nombre: '',
            descripcion: '',
            atributos: {},
            fecha_adquisicion: '',
            fecha_vencimiento: '',
            valor: '',
            fotos: [],
            registro_continuo: registroContinuo,
        });
        setPaso(2);
        clearErrors();
        setErroresLocales({});
    };

    useEffect(() => {
        if (!abierto) return;

        if (activo) {
            setData({
                catalogo_tipo_activo_id: activo.catalogo_tipo_activo_id || '',
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
            });
        } else {
            reset();
            setPaso(1);
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

    if (!abierto) return null;

    const tieneFotos = (data.fotos || []).length > 0;

    const validarPaso = (pasoActual = paso) => {
        const locales = {};

        if (pasoActual === 1 || !usarWizard) {
            if (!data.catalogo_tipo_activo_id) locales.catalogo_tipo_activo_id = 'Selecciona un tipo.';
            if (!data.departamento_id) locales.departamento_id = 'Selecciona un departamento.';
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
        if (validarPaso(paso)) setPaso((p) => Math.min(p + 1, PASOS.length));
    };

    const retrocederPaso = () => setPaso((p) => Math.max(p - 1, 1));

    const submit = (e) => {
        e.preventDefault();
        if (!validarPaso(usarWizard ? PASOS.length : 2)) return;

        const accion = esEdicion ? put : post;
        const ruta = esEdicion ? route('activos.update', activo.id) : route('activos.store');

        transform((formData) => ({
            ...formData,
            atributos: typeof formData.atributos === 'object' ? JSON.stringify(formData.atributos) : formData.atributos,
            registro_continuo: !esEdicion && registroContinuo,
        }));

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
    };

    const renderPaso1 = () => (
        <div className="grid grid-cols-1 gap-4">
            <div>
                <label className={LABEL_CLASS}>Tipo *</label>
                <select value={data.catalogo_tipo_activo_id} onChange={(e) => setData('catalogo_tipo_activo_id', e.target.value)} disabled={esEdicion} className={SELECT_CLASS}>
                    <option value="">Seleccionar...</option>
                    {tipos.map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                </select>
                {erroresCombinados.catalogo_tipo_activo_id && <p className="text-red-500 text-xs mt-1">{erroresCombinados.catalogo_tipo_activo_id}</p>}
            </div>
            <div>
                <label className={LABEL_CLASS}>Departamento *</label>
                <select value={data.departamento_id} onChange={(e) => setData('departamento_id', e.target.value)} className={SELECT_CLASS}>
                    <option value="">Seleccionar...</option>
                    {departamentos.map((d) => <option key={d.id} value={d.id}>{d.nombre}</option>)}
                </select>
                {erroresCombinados.departamento_id && <p className="text-red-500 text-xs mt-1">{erroresCombinados.departamento_id}</p>}
            </div>
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
            <p className="text-sm theme-text-muted italic">Selecciona un tipo de activo en el paso 1.</p>
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

    const renderWizard = () => (
        <>
            <div className="flex gap-2 mb-4">
                {PASOS.map((p) => (
                    <div
                        key={p.id}
                        className={`flex-1 h-1.5 rounded-full ${paso >= p.id ? '' : 'bg-black/10 dark:bg-white/10'}`}
                        style={paso >= p.id ? { backgroundColor: 'var(--color-primario)' } : undefined}
                    />
                ))}
            </div>
            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-4">
                Paso {paso} de {PASOS.length} — {PASOS[paso - 1]?.label}
            </p>
            {paso === 1 && renderPaso1()}
            {paso === 2 && renderPaso2()}
            {paso === 3 && renderPaso3()}
            {paso === 4 && renderPaso4()}
        </>
    );

    return (
        <ActivosModalShell
            title={esEdicion ? 'Editar activo' : 'Registrar activo'}
            onClose={onCerrar}
            size={esMovil && !esEdicion ? 'max-w-full sm:max-w-3xl' : 'max-w-3xl'}
            loader={<GeliaLoader isVisible={processing} message="Guardando_" />}
        >
            <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
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
                            <span className="text-sm theme-text-main">Registro continuo (mantener tipo y departamento)</span>
                        </label>
                    )}
                </div>

                <div className="gelia-modal-footer p-5 md:p-6 flex flex-col-reverse sm:flex-row justify-end gap-3">
                    {usarWizard && paso > 1 && (
                        <button type="button" onClick={retrocederPaso} className={`${BTN_SECONDARY_CLASS} w-full sm:w-auto min-h-[44px]`}>
                            <ChevronLeft className="w-4 h-4" /> Anterior
                        </button>
                    )}
                    <button type="button" onClick={onCerrar} className={`${BTN_SECONDARY_CLASS} w-full sm:w-auto min-h-[44px]`}>
                        Cancelar
                    </button>
                    {usarWizard && paso < PASOS.length ? (
                        <button type="button" onClick={avanzarPaso} className={`${BTN_TOUCH_CLASS} w-full sm:w-auto justify-center`}>
                            Siguiente <ChevronRight className="w-4 h-4" />
                        </button>
                    ) : (
                        <button type="submit" disabled={processing} className={`${BTN_TOUCH_CLASS} w-full sm:w-auto justify-center`}>
                            <Save className="w-4 h-4 shrink-0" /> Guardar
                        </button>
                    )}
                </div>
            </form>
        </ActivosModalShell>
    );
}
