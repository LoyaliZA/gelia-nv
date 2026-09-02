import React, { useMemo, useState } from 'react';
import { Camera, ImagePlus, Loader2, Undo2 } from 'lucide-react';
import ModalConfirmarAccion from '../../../ControlPedidos/Partials/ModalConfirmarAccion';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import { BTN_SECONDARY, THEME_INPUT } from './resguardosStyles';
import {
    resumenImpactoDevolucion,
    validarFormularioDevolucion,
} from './devolucionCorreccionResguardoUtils';

export default function FormularioDevolucionResguardo({
    resguardo,
    catalogos = {},
    enviando = false,
    progreso = 0,
    error = null,
    onEnviar,
    onCancelar,
}) {
    const [motivo, setMotivo] = useState('');
    const [evidencias, setEvidencias] = useState([]);
    const [pasoRevision, setPasoRevision] = useState(false);
    const [confirmar, setConfirmar] = useState(false);
    const [erroresLocales, setErroresLocales] = useState({});

    const impacto = useMemo(() => resumenImpactoDevolucion(resguardo), [resguardo]);

    const previews = useMemo(() => evidencias.map((archivo) => ({
        archivo,
        url: URL.createObjectURL(archivo),
    })), [evidencias]);

    const etiquetaEstadoActual = catalogos.estados?.[impacto.estadoActual] || impacto.estadoActual;
    const etiquetaEstadoNuevo = catalogos.estados?.[impacto.estadoNuevo] || impacto.estadoNuevo;

    const agregarEvidencias = (archivos) => {
        const imagenes = Array.from(archivos || []).filter((f) => f.type.startsWith('image/'));
        if (imagenes.length === 0) return;
        setEvidencias((prev) => [...prev, ...imagenes]);
        setErroresLocales((prev) => ({ ...prev, evidencias: undefined }));
    };

    const quitarEvidencia = (indice) => {
        setEvidencias((prev) => prev.filter((_, i) => i !== indice));
    };

    const solicitarRevision = (e) => {
        e.preventDefault();
        const errores = validarFormularioDevolucion({ motivo });
        if (Object.keys(errores).length > 0) {
            setErroresLocales(errores);
            return;
        }
        setErroresLocales({});
        setPasoRevision(true);
    };

    const solicitarConfirmacion = () => {
        setConfirmar(true);
    };

    const confirmarEnvio = async () => {
        setConfirmar(false);
        const resultado = await onEnviar({ motivo, evidencias });

        if (resultado?.validacion) {
            setErroresLocales(resultado.validacion);
            setPasoRevision(false);
            return;
        }

        if (resultado?.ok) {
            setMotivo('');
            setEvidencias([]);
            setPasoRevision(false);
            setErroresLocales({});
            onCancelar?.();
        }
    };

    const errorVisible = error || Object.values(erroresLocales)[0];

    return (
        <>
            <form
                onSubmit={pasoRevision ? (e) => { e.preventDefault(); solicitarConfirmacion(); } : solicitarRevision}
                className={`${geliaCardClass()} p-5 space-y-4 border border-amber-500/25`}
            >
                <div className="space-y-2">
                    <h3 className="text-sm font-black uppercase tracking-widest theme-text-main m-0 flex items-center gap-2">
                        <Undo2 className="w-4 h-4 text-amber-500 shrink-0" />
                        Confirmar devolución física
                    </h3>
                    <p className="text-sm theme-text-muted m-0">
                        Esta acción compensatoria registra la salida física de los bultos en custodia.
                        No borra ni modifica el historial previo; se agrega un evento de devolución.
                    </p>
                </div>

                {!pasoRevision ? (
                    <>
                        <label className="space-y-1.5 block">
                            <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                Motivo de la devolución
                            </span>
                            <textarea
                                value={motivo}
                                onChange={(e) => {
                                    setMotivo(e.target.value);
                                    setErroresLocales((prev) => ({ ...prev, motivo: undefined }));
                                }}
                                className={`${THEME_INPUT} min-h-[88px] resize-y`}
                                placeholder="Describe por qué los bultos salen de custodia…"
                                required
                                disabled={enviando}
                            />
                        </label>

                        <div className="space-y-2">
                            <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                Evidencia fotográfica (opcional)
                            </span>
                            <div className="flex flex-wrap gap-2">
                                <label className={`${BTN_SECONDARY} inline-flex items-center gap-2 min-h-[44px] cursor-pointer`}>
                                    <Camera className="w-4 h-4" />
                                    Cámara / galería
                                    <input
                                        type="file"
                                        accept="image/*"
                                        capture="environment"
                                        className="sr-only"
                                        multiple
                                        disabled={enviando}
                                        onChange={(e) => {
                                            agregarEvidencias(e.target.files);
                                            e.target.value = '';
                                        }}
                                    />
                                </label>
                                <label className={`${BTN_SECONDARY} inline-flex items-center gap-2 min-h-[44px] cursor-pointer`}>
                                    <ImagePlus className="w-4 h-4" />
                                    Adjuntar imagen
                                    <input
                                        type="file"
                                        accept="image/*"
                                        className="sr-only"
                                        multiple
                                        disabled={enviando}
                                        onChange={(e) => {
                                            agregarEvidencias(e.target.files);
                                            e.target.value = '';
                                        }}
                                    />
                                </label>
                            </div>
                            {previews.length > 0 && (
                                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    {previews.map((preview, indice) => (
                                        <div key={preview.url} className="relative rounded-xl border theme-border overflow-hidden">
                                            <img src={preview.url} alt="" className="w-full h-24 object-cover" />
                                            <button
                                                type="button"
                                                onClick={() => quitarEvidencia(indice)}
                                                className="absolute top-1 right-1 px-2 py-0.5 rounded-lg text-[9px] font-black uppercase bg-black/60 text-white"
                                                disabled={enviando}
                                            >
                                                Quitar
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </>
                ) : (
                    <section className="rounded-2xl border border-amber-500/30 p-4 space-y-3 bg-amber-500/5">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-main m-0">
                            Resumen de impacto operativo
                        </p>
                        <ul className="text-sm theme-text-main m-0 p-0 list-none space-y-2">
                            <li>
                                <strong>{impacto.cantidadBultos}</strong> bulto(s) en custodia pasarán a estado devuelto.
                            </li>
                            <li>
                                Estado del resguardo: <strong>{etiquetaEstadoActual}</strong> → <strong>{etiquetaEstadoNuevo}</strong>
                            </li>
                            {impacto.folios.length > 0 && (
                                <li className="text-[10px] theme-text-muted">
                                    Bultos: {impacto.folios.join(', ')}
                                </li>
                            )}
                        </ul>
                        <div className="rounded-xl border theme-border p-3 bg-black/[0.02] dark:bg-white/[0.02]">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Motivo</p>
                            <p className="text-sm theme-text-main m-0 mt-1 whitespace-pre-wrap">{motivo.trim()}</p>
                        </div>
                        {evidencias.length > 0 && (
                            <p className="text-[10px] theme-text-muted m-0">
                                {evidencias.length} imagen(es) adjunta(s).
                            </p>
                        )}
                    </section>
                )}

                {errorVisible && (
                    <p className="text-sm text-red-600 dark:text-red-300 font-semibold m-0">{errorVisible}</p>
                )}

                {enviando && (
                    <div className="space-y-2">
                        <div className="flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted">
                            <Loader2 className="w-4 h-4 animate-spin" />
                            Registrando devolución… {progreso}%
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
                    <button
                        type="button"
                        onClick={() => {
                            if (pasoRevision) {
                                setPasoRevision(false);
                            } else {
                                onCancelar?.();
                            }
                        }}
                        className={`${BTN_SECONDARY} min-h-[44px] flex-1`}
                        disabled={enviando}
                    >
                        {pasoRevision ? 'Editar datos' : 'Cancelar'}
                    </button>
                    <button
                        type="submit"
                        className={`${THEME_BTN_PRIMARY} inline-flex items-center justify-center gap-2 min-h-[44px] flex-1 text-[10px] font-black uppercase tracking-widest`}
                        disabled={enviando}
                    >
                        {enviando
                            ? <Loader2 className="w-4 h-4 animate-spin" />
                            : pasoRevision ? 'Confirmar devolución' : 'Revisar impacto'}
                    </button>
                </div>
            </form>

            <ModalConfirmarAccion
                abierto={confirmar}
                titulo="Confirmar devolución física"
                mensaje={`Se registrará la salida de ${impacto.cantidadBultos} bulto(s) y el resguardo pasará a «${etiquetaEstadoNuevo}». Esta acción compensatoria no se puede deshacer desde la interfaz.`}
                etiquetaConfirmar="Sí, confirmar devolución"
                variante="danger"
                onClose={() => setConfirmar(false)}
                onConfirm={confirmarEnvio}
            />
        </>
    );
}
