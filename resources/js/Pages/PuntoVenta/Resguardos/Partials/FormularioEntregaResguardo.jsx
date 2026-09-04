import React, { useMemo, useRef, useState } from 'react';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Camera,
    CheckCircle2,
    ImagePlus,
    Loader2,
    Trash2,
    UserCheck,
} from 'lucide-react';
import FirmaCanvas from '../../../../Components/Rh/FirmaCanvas';
import ModalConfirmarAccion from '../../../ControlPedidos/Partials/ModalConfirmarAccion';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import { BTN_SECONDARY, THEME_INPUT, formatearFechaOperativa } from './resguardosStyles';
import {
    PASOS_ENTREGA,
    indicePaso,
    puedeAvanzarPaso,
    validarPasoBultos,
    validarPasoEvidencia,
    validarPasoReceptor,
} from './entregaResguardoUtils';

export default function FormularioEntregaResguardo({
    resguardo,
    catalogos = {},
    enviando = false,
    progreso = 0,
    error = null,
    onEnviar,
    onCancelar,
}) {
    const [pasoActual, setPasoActual] = useState('localizar');
    const [relacion, setRelacion] = useState('titular');
    const [nombreQuienRetira, setNombreQuienRetira] = useState('');
    const [observaciones, setObservaciones] = useState('');
    const [evidencias, setEvidencias] = useState([]);
    const [erroresPaso, setErroresPaso] = useState({});
    const [confirmar, setConfirmar] = useState(false);
    const firmaRef = useRef(null);
    const bultosEnCustodia = useMemo(
        () => (resguardo.bultos || []).filter((bulto) => bulto.estado === 'recibido'),
        [resguardo.bultos],
    );
    const [bultoIds, setBultoIds] = useState(() => bultosEnCustodia.map((bulto) => bulto.id));

    const relaciones = catalogos.relaciones || {};
    const metodoValidacion = catalogos.metodo_validacion || 'firma';
    const indiceActual = indicePaso(pasoActual);

    const previews = useMemo(() => evidencias.map((archivo) => ({
        archivo,
        url: URL.createObjectURL(archivo),
    })), [evidencias]);

    const agregarEvidencias = (archivos) => {
        const imagenes = Array.from(archivos || []).filter((f) => f.type.startsWith('image/'));
        if (imagenes.length === 0) return;
        setEvidencias((prev) => [...prev, ...imagenes]);
    };

    const quitarEvidencia = (indice) => {
        setEvidencias((prev) => prev.filter((_, i) => i !== indice));
    };

    const validarYAvanzar = () => {
        setErroresPaso({});

        if (pasoActual === 'revisar') {
            const errores = validarPasoBultos({ bultoIds });
            if (Object.keys(errores).length > 0) {
                setErroresPaso(errores);
                return;
            }
        }

        if (pasoActual === 'receptor') {
            const errores = validarPasoReceptor({ relacion, nombreQuienRetira });
            if (Object.keys(errores).length > 0) {
                setErroresPaso(errores);
                return;
            }
        }

        if (pasoActual === 'evidencia') {
            const errores = validarPasoEvidencia({ tieneFirma: firmaRef.current?.hasStroke?.() });
            if (Object.keys(errores).length > 0) {
                setErroresPaso(errores);
                return;
            }
        }

        const siguiente = PASOS_ENTREGA[indiceActual + 1];
        if (siguiente) setPasoActual(siguiente.id);
    };

    const retroceder = () => {
        setErroresPaso({});
        const anterior = PASOS_ENTREGA[indiceActual - 1];
        if (anterior) setPasoActual(anterior.id);
    };

    const solicitarConfirmacion = () => {
        const errores = {
            ...validarPasoBultos({ bultoIds }),
            ...validarPasoReceptor({ relacion, nombreQuienRetira }),
            ...validarPasoEvidencia({ tieneFirma: firmaRef.current?.hasStroke?.() }),
        };
        if (Object.keys(errores).length > 0) {
            setErroresPaso(errores);
            return;
        }
        setConfirmar(true);
    };

    const confirmarEnvio = async () => {
        setConfirmar(false);
        const firmaDataUrl = firmaRef.current?.getDataUrl?.();
        await onEnviar({
            relacion,
            nombreQuienRetira,
            observaciones,
            firmaDataUrl,
            evidencias,
            metodoValidacion,
            bultoIds,
        });
    };

    const etiquetaRelacion = relaciones[relacion] || relacion;
    const esUltimoPaso = pasoActual === 'confirmar';
    const entregaParcial = bultoIds.length < bultosEnCustodia.length;

    return (
        <div className="space-y-6">
            <IndicadorPasos pasoActual={pasoActual} />

            {pasoActual === 'localizar' && (
                <PasoLocalizar resguardo={resguardo} />
            )}

            {pasoActual === 'revisar' && (
                <PasoRevisar
                    resguardo={resguardo}
                    catalogos={catalogos}
                    bultoIds={bultoIds}
                    onBultoIds={setBultoIds}
                    errores={erroresPaso}
                    deshabilitado={enviando}
                />
            )}

            {pasoActual === 'receptor' && (
                <PasoReceptor
                    relacion={relacion}
                    onRelacion={setRelacion}
                    nombreQuienRetira={nombreQuienRetira}
                    onNombre={setNombreQuienRetira}
                    observaciones={observaciones}
                    onObservaciones={setObservaciones}
                    relaciones={relaciones}
                    errores={erroresPaso}
                    deshabilitado={enviando}
                />
            )}

            {pasoActual === 'evidencia' && (
                <PasoEvidencia
                    firmaRef={firmaRef}
                    previews={previews}
                    onAgregar={agregarEvidencias}
                    onQuitar={quitarEvidencia}
                    errores={erroresPaso}
                    deshabilitado={enviando}
                />
            )}

            {pasoActual === 'confirmar' && (
                <PasoConfirmar
                    resguardo={resguardo}
                    relacion={relacion}
                    etiquetaRelacion={etiquetaRelacion}
                    nombreQuienRetira={nombreQuienRetira}
                    observaciones={observaciones}
                    cantidadEvidencias={evidencias.length}
                    cantidadBultos={bultoIds.length}
                    entregaParcial={entregaParcial}
                />
            )}

            {error && (
                <div className={`${geliaCardClass()} p-4 border border-red-500/30`}>
                    <p className="text-sm font-semibold text-red-600 dark:text-red-300 m-0">{error}</p>
                </div>
            )}

            {enviando && (
                <div className={`${geliaCardClass()} p-4 space-y-2`}>
                    <div className="flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted">
                        <Loader2 className="w-4 h-4 animate-spin" />
                        Registrando entrega… {progreso}%
                    </div>
                    <div className="h-2 rounded-full bg-black/10 dark:bg-white/10 overflow-hidden">
                        <div
                            className="h-full transition-all duration-300"
                            style={{ width: `${progreso}%`, backgroundColor: 'var(--color-primario)' }}
                        />
                    </div>
                </div>
            )}

            <div className="flex flex-col sm:flex-row gap-2">
                {indiceActual > 0 && !enviando && (
                    <button type="button" onClick={retroceder} className={`${BTN_SECONDARY} min-h-[48px] flex-1`}>
                        <ArrowLeft className="w-4 h-4 inline mr-2" />
                        Anterior
                    </button>
                )}
                {indiceActual === 0 && !enviando && (
                    <button type="button" onClick={onCancelar} className={`${BTN_SECONDARY} min-h-[48px] flex-1`}>
                        Cancelar
                    </button>
                )}
                {!esUltimoPaso && (
                    <button
                        type="button"
                        onClick={validarYAvanzar}
                        disabled={enviando}
                        className={`${THEME_BTN_PRIMARY} min-h-[48px] flex-1 text-[10px] font-black uppercase tracking-widest disabled:opacity-50`}
                    >
                        Siguiente
                        <ArrowRight className="w-4 h-4 inline ml-2" />
                    </button>
                )}
                {esUltimoPaso && (
                    <button
                        type="button"
                        onClick={solicitarConfirmacion}
                        disabled={enviando || !puedeAvanzarPaso('receptor', { relacion, nombreQuienRetira })}
                        className={`${THEME_BTN_PRIMARY} min-h-[48px] flex-1 text-[10px] font-black uppercase tracking-widest disabled:opacity-50`}
                    >
                        {enviando ? 'Procesando…' : 'Confirmar entrega'}
                    </button>
                )}
            </div>

            <ModalConfirmarAccion
                abierto={confirmar}
                titulo="Confirmar entrega física"
                mensaje={`Se entregarán ${bultoIds.length} bulto(s) a ${nombreQuienRetira.trim()} (${etiquetaRelacion}). ${entregaParcial ? 'Los bultos no seleccionados permanecerán en custodia.' : 'Esta acción es irreversible.'}`}
                etiquetaConfirmar="Sí, registrar entrega"
                variante="primary"
                onClose={() => setConfirmar(false)}
                onConfirm={confirmarEnvio}
            />
        </div>
    );
}

function IndicadorPasos({ pasoActual }) {
    const indice = indicePaso(pasoActual);

    return (
        <div className={`${geliaCardClass()} p-4`}>
            <ol className="flex flex-wrap gap-2 m-0 p-0 list-none">
                {PASOS_ENTREGA.map((paso, indicePasoItem) => {
                    const activo = paso.id === pasoActual;
                    const completado = indicePasoItem < indice;
                    return (
                        <li
                            key={paso.id}
                            className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-widest ${
                                activo
                                    ? 'bg-[var(--color-primario)]/15 text-[var(--color-primario)]'
                                    : completado
                                        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                        : 'theme-text-muted bg-black/[0.03] dark:bg-white/[0.03]'
                            }`}
                        >
                            {completado ? <CheckCircle2 className="w-3.5 h-3.5" /> : null}
                            {paso.etiqueta}
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}

function PasoLocalizar({ resguardo }) {
    return (
        <div className={`${geliaCardClass()} p-5 space-y-4`}>
            <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Confirmar resguardo</h2>
            <p className="text-sm theme-text-muted m-0">
                Verifica que este sea el pedido correcto antes de continuar. Los datos provienen del snapshot operativo y no pueden modificarse aquí.
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <CampoSoloLectura label="Folio" value={resguardo.snapshot_folio || `#${resguardo.id}`} />
                <CampoSoloLectura label="Cliente" value={resguardo.referencia_cliente} />
                <CampoSoloLectura label="Bultos en custodia" value={resguardo.cantidad_bultos_en_custodia ?? bultosEnCustodia.length} />
                <CampoSoloLectura label="Sucursal" value={resguardo.sucursal?.nombre} />
                {resguardo.pedido?.folio && (
                    <CampoSoloLectura label="Pedido" value={resguardo.pedido.folio} />
                )}
                {resguardo.pedido?.folio_remision && (
                    <CampoSoloLectura label="Remisión" value={resguardo.pedido.folio_remision} />
                )}
            </div>
        </div>
    );
}

function PasoRevisar({ resguardo, catalogos, bultoIds, onBultoIds, errores, deshabilitado }) {
    const toggleBulto = (id) => {
        if (deshabilitado) return;
        if (bultoIds.includes(id)) {
            onBultoIds(bultoIds.filter((actual) => actual !== id));
            return;
        }
        onBultoIds([...bultoIds, id]);
    };

    return (
        <div className="space-y-4">
            <div className={`${geliaCardClass()} p-5 space-y-4`}>
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Bultos a entregar</h2>
                <p className="text-sm theme-text-muted m-0">
                    Marca los bultos que se entregan ahora. Los no seleccionados permanecen en custodia.
                </p>
                <div className="space-y-2">
                    {(resguardo.bultos || []).map((bulto) => {
                        const enCustodia = bulto.estado === 'recibido';
                        const seleccionado = bultoIds.includes(bulto.id);
                        return (
                            <label
                                key={bulto.id}
                                className={`rounded-2xl border p-3 flex flex-wrap justify-between gap-2 min-h-[48px] ${
                                    enCustodia ? 'cursor-pointer' : 'opacity-60'
                                } ${seleccionado ? 'border-[var(--color-primario)] bg-[var(--color-primario)]/10' : 'theme-border'}`}
                            >
                                <div className="flex items-start gap-3 min-w-0">
                                    <input
                                        type="checkbox"
                                        className="mt-1 shrink-0 h-5 w-5"
                                        checked={seleccionado}
                                        disabled={deshabilitado || !enCustodia}
                                        onChange={() => toggleBulto(bulto.id)}
                                    />
                                    <div className="min-w-0">
                                        <p className="text-sm font-black theme-text-main m-0">{bulto.folio || `#${bulto.id}`}</p>
                                        <p className="text-[10px] theme-text-muted m-0 uppercase">{bulto.tipo} · {bulto.estado}</p>
                                    </div>
                                </div>
                                <p className="text-[10px] theme-text-muted m-0 self-center">
                                    Recibido: {formatearFechaOperativa(bulto.recepcion_at)}
                                </p>
                            </label>
                        );
                    })}
                </div>
                {errores.bulto_ids && (
                    <p className="text-xs font-bold text-red-600 dark:text-red-300 m-0">{errores.bulto_ids}</p>
                )}
            </div>

            {(resguardo.incidencias || []).length > 0 && (
                <div className={`${geliaCardClass()} p-5 space-y-3 border border-amber-500/30`}>
                    <div className="flex items-center gap-2">
                        <AlertTriangle className="w-5 h-5 text-amber-500 shrink-0" />
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Incidencias registradas</h2>
                    </div>
                    {resguardo.incidencias.map((incidencia) => (
                        <div key={incidencia.id} className="rounded-2xl border theme-border p-3 space-y-1">
                            <p className="text-sm font-black theme-text-main m-0">
                                {incidencia.tipo_etiqueta || catalogos.tipos_incidencia?.[incidencia.tipo]}
                                <span className="text-[10px] font-bold theme-text-muted ml-2 uppercase">({incidencia.estado})</span>
                            </p>
                            {incidencia.descripcion && (
                                <p className="text-sm theme-text-muted m-0">{incidencia.descripcion}</p>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

export function PasoReceptor({
    relacion,
    onRelacion,
    nombreQuienRetira,
    onNombre,
    observaciones,
    onObservaciones,
    relaciones,
    errores,
    deshabilitado,
}) {
    return (
        <div className={`${geliaCardClass()} p-5 space-y-4`}>
            <div className="flex items-center gap-2">
                <UserCheck className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Quien retira</h2>
            </div>
            <p className="text-sm theme-text-muted m-0">
                Indica si retira el titular del pedido o un tercero autorizado. Solo se solicita nombre y firma; no se capturan identificaciones oficiales.
            </p>

            <fieldset className="space-y-2 border-0 p-0 m-0">
                <legend className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2">Relación</legend>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    {Object.entries(relaciones).map(([valor, etiqueta]) => (
                        <label
                            key={valor}
                            className={`flex items-center gap-3 rounded-2xl border p-4 cursor-pointer min-h-[48px] ${
                                relacion === valor ? 'border-[var(--color-primario)] bg-[var(--color-primario)]/10' : 'theme-border'
                            }`}
                        >
                            <input
                                type="radio"
                                name="relacion"
                                value={valor}
                                checked={relacion === valor}
                                onChange={() => onRelacion(valor)}
                                disabled={deshabilitado}
                                className="shrink-0"
                            />
                            <span className="text-sm font-bold theme-text-main">{etiqueta}</span>
                        </label>
                    ))}
                </div>
                {errores.relacion && (
                    <p className="text-xs font-bold text-red-600 dark:text-red-300 m-0">{errores.relacion}</p>
                )}
            </fieldset>

            <label className="space-y-1.5 block">
                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Nombre de quien retira</span>
                <input
                    type="text"
                    value={nombreQuienRetira}
                    onChange={(e) => onNombre(e.target.value)}
                    className={THEME_INPUT}
                    maxLength={255}
                    required
                    disabled={deshabilitado}
                    autoComplete="name"
                    placeholder={relacion === 'tercero' ? 'Nombre del tercero autorizado' : 'Nombre del titular'}
                />
                {errores.nombre_quien_retira && (
                    <p className="text-xs font-bold text-red-600 dark:text-red-300 m-0">{errores.nombre_quien_retira}</p>
                )}
            </label>

            <label className="space-y-1.5 block">
                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Observaciones (opcional)</span>
                <textarea
                    value={observaciones}
                    onChange={(e) => onObservaciones(e.target.value)}
                    className={THEME_INPUT}
                    rows={3}
                    maxLength={1000}
                    disabled={deshabilitado}
                    placeholder="Notas operativas visibles en el registro de entrega"
                />
            </label>
        </div>
    );
}

export function PasoEvidencia({ firmaRef, previews, onAgregar, onQuitar, errores, deshabilitado }) {
    return (
        <div className="space-y-4">
            <div className={`${geliaCardClass()} p-5 space-y-4`}>
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Firma del receptor</h2>
                <p className="text-sm theme-text-muted m-0">La firma es obligatoria para validar la entrega.</p>
                <FirmaCanvas ref={firmaRef} label="Firma de quien retira" height={200} />
                {errores.firma && (
                    <p className="text-xs font-bold text-red-600 dark:text-red-300 m-0">{errores.firma}</p>
                )}
            </div>

            <div className={`${geliaCardClass()} p-5 space-y-4`}>
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Evidencia fotográfica (opcional)</h2>
                <div className="flex flex-wrap gap-2">
                    <label className={`${BTN_SECONDARY} inline-flex items-center gap-2 cursor-pointer min-h-[44px]`}>
                        <Camera className="w-4 h-4" />
                        Tomar foto
                        <input
                            type="file"
                            accept="image/*"
                            capture="environment"
                            className="sr-only"
                            disabled={deshabilitado}
                            onChange={(e) => {
                                onAgregar(e.target.files);
                                e.target.value = '';
                            }}
                        />
                    </label>
                    <label className={`${BTN_SECONDARY} inline-flex items-center gap-2 cursor-pointer min-h-[44px]`}>
                        <ImagePlus className="w-4 h-4" />
                        Galería
                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            className="sr-only"
                            disabled={deshabilitado}
                            onChange={(e) => {
                                onAgregar(e.target.files);
                                e.target.value = '';
                            }}
                        />
                    </label>
                </div>
                {previews.length > 0 && (
                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        {previews.map((item, indice) => (
                            <div key={`${item.archivo.name}-${indice}`} className="relative rounded-2xl overflow-hidden border theme-border">
                                <img src={item.url} alt={`Evidencia ${indice + 1}`} className="w-full h-28 object-cover" />
                                <button
                                    type="button"
                                    onClick={() => onQuitar(indice)}
                                    className="absolute top-2 right-2 p-2 rounded-xl bg-black/60 text-white min-h-[44px] min-w-[44px]"
                                    aria-label="Quitar evidencia"
                                    disabled={deshabilitado}
                                >
                                    <Trash2 className="w-4 h-4 mx-auto" />
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

function PasoConfirmar({
    resguardo,
    relacion,
    etiquetaRelacion,
    nombreQuienRetira,
    observaciones,
    cantidadEvidencias,
    cantidadBultos,
    entregaParcial,
}) {
    return (
        <div className={`${geliaCardClass()} p-5 space-y-4 border-2 border-[var(--color-primario)]/30`}>
            <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Resumen antes de confirmar</h2>
            <p className="text-sm theme-text-muted m-0">
                {entregaParcial
                    ? 'Al confirmar, solo los bultos seleccionados se entregan. El resguardo permanece en custodia.'
                    : 'Revisa los datos. Al confirmar, el resguardo pasará a estado Entregado y no podrá entregarse de nuevo.'}
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <CampoSoloLectura label="Folio" value={resguardo.snapshot_folio || `#${resguardo.id}`} />
                <CampoSoloLectura label="Cliente" value={resguardo.referencia_cliente} />
                <CampoSoloLectura label="Bultos a entregar" value={cantidadBultos} />
                <CampoSoloLectura label="Relación" value={etiquetaRelacion} />
                <CampoSoloLectura label="Quien retira" value={nombreQuienRetira.trim()} />
                <CampoSoloLectura label="Validación" value="Firma capturada" />
                {observaciones?.trim() && (
                    <CampoSoloLectura label="Observaciones" value={observaciones.trim()} className="sm:col-span-2" />
                )}
                {cantidadEvidencias > 0 && (
                    <CampoSoloLectura
                        label="Evidencias"
                        value={`${cantidadEvidencias} foto(s) adjunta(s)`}
                        className="sm:col-span-2"
                    />
                )}
            </div>
            {relacion === 'tercero' && (
                <p className="text-[10px] font-bold text-amber-700 dark:text-amber-300 m-0 flex items-start gap-2">
                    <AlertTriangle className="w-4 h-4 shrink-0" />
                    Entrega a tercero autorizado. Verifica identidad conforme al procedimiento de sucursal.
                </p>
            )}
        </div>
    );
}

export function CampoSoloLectura({ label, value, className = '' }) {
    return (
        <div className={`rounded-2xl border theme-border p-3 bg-black/[0.02] dark:bg-white/[0.02] ${className}`}>
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
            <p className="text-sm font-black theme-text-main m-0 mt-1">{value ?? '—'}</p>
        </div>
    );
}
