import React, { useMemo, useState } from 'react';
import { Camera, ImagePlus, Loader2, Plus } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import ModalConfirmarAccion from '../../../ControlPedidos/Partials/ModalConfirmarAccion';
import {
    BTN_SECONDARY,
    THEME_INPUT,
    THEME_SELECT,
} from './resguardosStyles';
import {
    crearBultoIncidenciaVacio,
    exigeBultoIncidencia,
    exigeEvidenciaIncidencia,
    tiposIncidenciaDisponibles,
} from './incidenciasResguardoUtils';

export default function FormularioIncidenciaResguardo({
    catalogos = {},
    permisos = {},
    almacenes = [],
    enviando = false,
    progreso = 0,
    error = null,
    onEnviar,
    onCancelar,
}) {
    const tiposDisponibles = useMemo(
        () => tiposIncidenciaDisponibles(permisos, catalogos),
        [permisos, catalogos],
    );

    const [tipo, setTipo] = useState(tiposDisponibles[0]?.valor || '');
    const [descripcion, setDescripcion] = useState('');
    const [evidencias, setEvidencias] = useState([]);
    const [almacenId, setAlmacenId] = useState(almacenes.length === 1 ? String(almacenes[0].id) : '');
    const [bulto, setBulto] = useState(() => crearBultoIncidenciaVacio());
    const [confirmar, setConfirmar] = useState(false);
    const [erroresLocales, setErroresLocales] = useState({});

    const previews = useMemo(() => evidencias.map((archivo) => ({
        archivo,
        url: URL.createObjectURL(archivo),
    })), [evidencias]);

    const tiposBulto = catalogos.tipos_bulto || {};
    const condiciones = catalogos.condiciones_bulto || {};

    const agregarEvidencias = (archivos) => {
        const imagenes = Array.from(archivos || []).filter((f) => f.type.startsWith('image/'));
        if (imagenes.length === 0) return;
        setEvidencias((prev) => [...prev, ...imagenes]);
        setErroresLocales((prev) => ({ ...prev, evidencias: undefined }));
    };

    const quitarEvidencia = (indice) => {
        setEvidencias((prev) => prev.filter((_, i) => i !== indice));
    };

    const actualizarBulto = (campo, valor) => {
        setBulto((prev) => ({ ...prev, [campo]: valor }));
        setErroresLocales((prev) => ({ ...prev, [`bulto_${campo}`]: undefined, almacen_id: undefined }));
    };

    const solicitarConfirmacion = (e) => {
        e.preventDefault();
        setConfirmar(true);
    };

    const confirmarEnvio = async () => {
        setConfirmar(false);
        const resultado = await onEnviar({
            tipo,
            descripcion,
            evidencias,
            bulto: exigeBultoIncidencia(tipo) ? bulto : null,
            almacenId: almacenId ? Number(almacenId) : null,
        });

        if (resultado?.validacion) {
            setErroresLocales(resultado.validacion);
            return;
        }

        if (resultado?.ok) {
            setDescripcion('');
            setEvidencias([]);
            setBulto(crearBultoIncidenciaVacio());
            setErroresLocales({});
            onCancelar?.();
        }
    };

    if (tiposDisponibles.length === 0) {
        return null;
    }

    return (
        <>
            <form onSubmit={solicitarConfirmacion} className={`${geliaCardClass()} p-5 space-y-4 border border-[var(--color-primario)]/20`}>
                <h3 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                    Nuevo reporte de incidencia
                </h3>

                <label className="space-y-1.5 block">
                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Tipo</span>
                    <select
                        value={tipo}
                        onChange={(e) => {
                            setTipo(e.target.value);
                            setErroresLocales({});
                        }}
                        className={THEME_SELECT}
                        required
                        disabled={enviando}
                    >
                        {tiposDisponibles.map(({ valor, etiqueta }) => (
                            <option key={valor} value={valor}>{etiqueta}</option>
                        ))}
                    </select>
                    {erroresLocales.tipo && (
                        <p className="text-[10px] text-red-500 m-0">{erroresLocales.tipo}</p>
                    )}
                </label>

                <label className="space-y-1.5 block">
                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Descripción del reporte</span>
                    <textarea
                        value={descripcion}
                        onChange={(e) => {
                            setDescripcion(e.target.value);
                            setErroresLocales((prev) => ({ ...prev, descripcion: undefined }));
                        }}
                        className={`${THEME_INPUT} min-h-[96px] resize-y`}
                        placeholder="Describe lo observado…"
                        required
                        disabled={enviando}
                    />
                    {erroresLocales.descripcion && (
                        <p className="text-[10px] text-red-500 m-0">{erroresLocales.descripcion}</p>
                    )}
                </label>

                {exigeBultoIncidencia(tipo) && (
                    <div className="space-y-3 rounded-2xl border theme-border p-4">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                            Bulto dañado (se recibirá en custodia)
                        </p>
                        <label className="space-y-1.5 block">
                            <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Almacén</span>
                            <select
                                value={almacenId}
                                onChange={(e) => {
                                    setAlmacenId(e.target.value);
                                    setErroresLocales((prev) => ({ ...prev, almacen_id: undefined }));
                                }}
                                className={THEME_SELECT}
                                required
                                disabled={enviando}
                            >
                                <option value="">Seleccionar ubicación…</option>
                                {almacenes.map((almacen) => (
                                    <option key={almacen.id} value={almacen.id}>
                                        {almacen.codigo} — {almacen.nombre}
                                    </option>
                                ))}
                            </select>
                            {erroresLocales.almacen_id && (
                                <p className="text-[10px] text-red-500 m-0">{erroresLocales.almacen_id}</p>
                            )}
                        </label>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label className="space-y-1.5 block sm:col-span-2">
                                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Folio</span>
                                <input
                                    type="text"
                                    value={bulto.folio}
                                    onChange={(e) => actualizarBulto('folio', e.target.value)}
                                    className={THEME_INPUT}
                                    required
                                    disabled={enviando}
                                />
                                {erroresLocales.bulto_folio && (
                                    <p className="text-[10px] text-red-500 m-0">{erroresLocales.bulto_folio}</p>
                                )}
                            </label>
                            <label className="space-y-1.5 block">
                                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Tipo</span>
                                <select
                                    value={bulto.tipo}
                                    onChange={(e) => actualizarBulto('tipo', e.target.value)}
                                    className={THEME_SELECT}
                                    disabled={enviando}
                                >
                                    {Object.entries(tiposBulto).map(([valor, etiqueta]) => (
                                        <option key={valor} value={valor}>{etiqueta}</option>
                                    ))}
                                </select>
                            </label>
                            <label className="space-y-1.5 block">
                                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Condición</span>
                                <select
                                    value={bulto.condicion}
                                    onChange={(e) => actualizarBulto('condicion', e.target.value)}
                                    className={THEME_SELECT}
                                    disabled={enviando}
                                >
                                    {Object.entries(condiciones).map(([valor, etiqueta]) => (
                                        <option key={valor} value={valor}>{etiqueta}</option>
                                    ))}
                                </select>
                            </label>
                        </div>
                    </div>
                )}

                {exigeEvidenciaIncidencia(tipo) && (
                    <div className="space-y-3">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                            Evidencia fotográfica
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <label className={`${BTN_SECONDARY} inline-flex items-center gap-2 min-h-[44px] cursor-pointer`}>
                                <Camera className="w-4 h-4" />
                                Cámara
                                <input
                                    type="file"
                                    accept="image/*"
                                    capture="environment"
                                    className="sr-only"
                                    disabled={enviando}
                                    onChange={(e) => {
                                        agregarEvidencias(e.target.files);
                                        e.target.value = '';
                                    }}
                                />
                            </label>
                            <label className={`${BTN_SECONDARY} inline-flex items-center gap-2 min-h-[44px] cursor-pointer`}>
                                <ImagePlus className="w-4 h-4" />
                                Galería
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    className="sr-only"
                                    disabled={enviando}
                                    onChange={(e) => {
                                        agregarEvidencias(e.target.files);
                                        e.target.value = '';
                                    }}
                                />
                            </label>
                        </div>
                        {erroresLocales.evidencias && (
                            <p className="text-[10px] text-red-500 m-0">{erroresLocales.evidencias}</p>
                        )}
                        {previews.length > 0 && (
                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                {previews.map((preview, indice) => (
                                    <div key={preview.url} className="relative rounded-xl overflow-hidden border theme-border aspect-square">
                                        <img src={preview.url} alt="" className="w-full h-full object-cover" />
                                        <button
                                            type="button"
                                            onClick={() => quitarEvidencia(indice)}
                                            className="absolute top-1 right-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase bg-black/60 text-white"
                                            disabled={enviando}
                                        >
                                            Quitar
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {error && (
                    <p className="text-sm text-red-600 dark:text-red-300 font-semibold m-0">{error}</p>
                )}

                {enviando && progreso > 0 && (
                    <p className="text-[10px] theme-text-muted font-bold m-0">Subiendo evidencia… {progreso}%</p>
                )}

                <div className="flex flex-col sm:flex-row gap-2 justify-end">
                    <button type="button" onClick={onCancelar} className={`${BTN_SECONDARY} min-h-[44px]`} disabled={enviando}>
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        className={`${THEME_BTN_PRIMARY} inline-flex items-center justify-center gap-2 min-h-[44px] px-5 text-[10px] font-black uppercase tracking-widest`}
                        disabled={enviando}
                    >
                        {enviando ? <Loader2 className="w-4 h-4 animate-spin" /> : <Plus className="w-4 h-4" />}
                        Confirmar reporte
                    </button>
                </div>
            </form>

            <ModalConfirmarAccion
                abierto={confirmar}
                titulo="Confirmar reporte de incidencia"
                mensaje="El reporte quedará registrado y no podrá editarse. ¿Deseas continuar?"
                etiquetaConfirmar="Sí, registrar incidencia"
                onConfirmar={confirmarEnvio}
                onCancelar={() => setConfirmar(false)}
            />
        </>
    );
}
