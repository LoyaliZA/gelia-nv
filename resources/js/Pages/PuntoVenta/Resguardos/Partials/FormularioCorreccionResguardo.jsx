import React, { useMemo, useState } from 'react';
import { Camera, ImagePlus, Loader2, PenLine } from 'lucide-react';
import ModalConfirmarAccion from '../../../ControlPedidos/Partials/ModalConfirmarAccion';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import { BTN_SECONDARY, THEME_INPUT, THEME_SELECT, formatearFechaOperativa } from './resguardosStyles';
import {
    TIPO_CORRECCION_ANOTACION,
    TIPO_CORRECCION_SNAPSHOT,
    eventosReferenciaDisponibles,
    resumenImpactoCorreccion,
    validarFormularioCorreccion,
} from './devolucionCorreccionResguardoUtils';

export default function FormularioCorreccionResguardo({
    resguardo,
    timeline = [],
    catalogos = {},
    enviando = false,
    progreso = 0,
    error = null,
    onEnviar,
    onCancelar,
}) {
    const tiposCorreccion = catalogos.tipos_correccion || {};
    const tiposDisponibles = Object.entries(tiposCorreccion).map(([valor, etiqueta]) => ({ valor, etiqueta }));
    const eventosReferencia = useMemo(() => eventosReferenciaDisponibles(timeline), [timeline]);

    const [tipoCorreccion, setTipoCorreccion] = useState(tiposDisponibles[0]?.valor || TIPO_CORRECCION_SNAPSHOT);
    const [motivo, setMotivo] = useState('');
    const [snapshotFolio, setSnapshotFolio] = useState('');
    const [snapshotClienteNombre, setSnapshotClienteNombre] = useState('');
    const [eventoReferenciaId, setEventoReferenciaId] = useState('');
    const [evidencias, setEvidencias] = useState([]);
    const [pasoRevision, setPasoRevision] = useState(false);
    const [confirmar, setConfirmar] = useState(false);
    const [erroresLocales, setErroresLocales] = useState({});

    const eventoReferencia = eventosReferencia.find((evento) => String(evento.id) === String(eventoReferenciaId));

    const impacto = useMemo(() => resumenImpactoCorreccion({
        tipoCorreccion,
        resguardo,
        snapshotFolio,
        snapshotClienteNombre,
        eventoReferencia,
    }), [tipoCorreccion, resguardo, snapshotFolio, snapshotClienteNombre, eventoReferencia]);

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

    const datosFormulario = () => ({
        tipoCorreccion,
        motivo,
        snapshotFolio,
        snapshotClienteNombre,
        eventoReferenciaId: eventoReferenciaId ? Number(eventoReferenciaId) : null,
        evidencias,
        resguardo,
    });

    const solicitarRevision = (e) => {
        e.preventDefault();
        const errores = validarFormularioCorreccion(datosFormulario());
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
        const resultado = await onEnviar(datosFormulario());

        if (resultado?.validacion) {
            setErroresLocales(resultado.validacion);
            setPasoRevision(false);
            return;
        }

        if (resultado?.ok) {
            setMotivo('');
            setSnapshotFolio('');
            setSnapshotClienteNombre('');
            setEventoReferenciaId('');
            setEvidencias([]);
            setPasoRevision(false);
            setErroresLocales({});
            onCancelar?.();
        }
    };

    const errorVisible = error || Object.values(erroresLocales)[0];

    if (tiposDisponibles.length === 0) {
        return null;
    }

    return (
        <>
            <form
                onSubmit={pasoRevision ? (e) => { e.preventDefault(); solicitarConfirmacion(); } : solicitarRevision}
                className={`${geliaCardClass()} p-5 space-y-4 border border-purple-500/25`}
            >
                <div className="space-y-2">
                    <h3 className="text-sm font-black uppercase tracking-widest theme-text-main m-0 flex items-center gap-2">
                        <PenLine className="w-4 h-4 text-purple-500 shrink-0" />
                        Corrección administrativa
                    </h3>
                    <p className="text-sm theme-text-muted m-0">
                        Las correcciones son acciones compensatorias auditadas. No editan eventos históricos;
                        registran un ajuste o anotación con motivo y evidencia opcional.
                    </p>
                </div>

                {!pasoRevision ? (
                    <>
                        <label className="space-y-1.5 block">
                            <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Tipo</span>
                            <select
                                value={tipoCorreccion}
                                onChange={(e) => {
                                    setTipoCorreccion(e.target.value);
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
                        </label>

                        {tipoCorreccion === TIPO_CORRECCION_SNAPSHOT && (
                            <div className="space-y-3">
                                <CampoSoloLectura
                                    label="Folio actual (referencia histórica)"
                                    value={resguardo?.snapshot_folio}
                                />
                                <label className="space-y-1.5 block">
                                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                        Folio corregido
                                    </span>
                                    <input
                                        type="text"
                                        value={snapshotFolio}
                                        onChange={(e) => {
                                            setSnapshotFolio(e.target.value);
                                            setErroresLocales((prev) => ({ ...prev, correccion: undefined }));
                                        }}
                                        className={THEME_INPUT}
                                        placeholder="Solo si difiere del actual"
                                        disabled={enviando}
                                    />
                                </label>
                                <CampoSoloLectura
                                    label="Cliente actual (referencia histórica)"
                                    value={resguardo?.snapshot_cliente_nombre || resguardo?.referencia_cliente}
                                />
                                <label className="space-y-1.5 block">
                                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                        Cliente corregido
                                    </span>
                                    <input
                                        type="text"
                                        value={snapshotClienteNombre}
                                        onChange={(e) => {
                                            setSnapshotClienteNombre(e.target.value);
                                            setErroresLocales((prev) => ({ ...prev, correccion: undefined }));
                                        }}
                                        className={THEME_INPUT}
                                        placeholder="Solo si difiere del actual"
                                        disabled={enviando}
                                    />
                                </label>
                            </div>
                        )}

                        {tipoCorreccion === TIPO_CORRECCION_ANOTACION && (
                            <label className="space-y-1.5 block">
                                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                    Evento de referencia
                                </span>
                                <select
                                    value={eventoReferenciaId}
                                    onChange={(e) => {
                                        setEventoReferenciaId(e.target.value);
                                        setErroresLocales((prev) => ({ ...prev, evento_referencia_id: undefined }));
                                    }}
                                    className={THEME_SELECT}
                                    required
                                    disabled={enviando || eventosReferencia.length === 0}
                                >
                                    <option value="">Selecciona un evento…</option>
                                    {eventosReferencia.map((evento) => (
                                        <option key={evento.id} value={evento.id}>
                                            {evento.etiqueta}
                                            {evento.ocurridoAt ? ` · ${formatearFechaOperativa(evento.ocurridoAt)}` : ''}
                                        </option>
                                    ))}
                                </select>
                                {eventosReferencia.length === 0 && (
                                    <p className="text-[10px] theme-text-muted m-0">
                                        No hay eventos en la línea de tiempo para referenciar.
                                    </p>
                                )}
                            </label>
                        )}

                        <label className="space-y-1.5 block">
                            <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                Motivo de la corrección
                            </span>
                            <textarea
                                value={motivo}
                                onChange={(e) => {
                                    setMotivo(e.target.value);
                                    setErroresLocales((prev) => ({ ...prev, motivo: undefined }));
                                }}
                                className={`${THEME_INPUT} min-h-[88px] resize-y`}
                                placeholder="Explica el ajuste administrativo…"
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
                    <section className="rounded-2xl border border-purple-500/30 p-4 space-y-3 bg-purple-500/5">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-main m-0">
                            Resumen de impacto operativo
                        </p>
                        <p className="text-sm theme-text-main m-0">{impacto.descripcion}</p>

                        {impacto.tipo === 'snapshot' && impacto.cambios?.length > 0 && (
                            <ul className="text-sm theme-text-main m-0 p-0 list-none space-y-2">
                                {impacto.cambios.map((cambio) => (
                                    <li key={cambio.campo}>
                                        <strong>{cambio.campo}:</strong> {cambio.anterior} → {cambio.nuevo}
                                    </li>
                                ))}
                            </ul>
                        )}

                        {impacto.tipo === 'anotacion' && impacto.evento && (
                            <p className="text-sm theme-text-muted m-0">
                                Evento referenciado: <strong>{impacto.evento.etiqueta}</strong>
                                {impacto.evento.ocurridoAt ? ` (${formatearFechaOperativa(impacto.evento.ocurridoAt)})` : ''}
                            </p>
                        )}

                        <div className="rounded-xl border theme-border p-3 bg-black/[0.02] dark:bg-white/[0.02]">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Motivo</p>
                            <p className="text-sm theme-text-main m-0 mt-1 whitespace-pre-wrap">{motivo.trim()}</p>
                        </div>
                    </section>
                )}

                {errorVisible && (
                    <p className="text-sm text-red-600 dark:text-red-300 font-semibold m-0">{errorVisible}</p>
                )}

                {enviando && (
                    <div className="space-y-2">
                        <div className="flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted">
                            <Loader2 className="w-4 h-4 animate-spin" />
                            Registrando corrección… {progreso}%
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
                            : pasoRevision ? 'Confirmar corrección' : 'Revisar impacto'}
                    </button>
                </div>
            </form>

            <ModalConfirmarAccion
                abierto={confirmar}
                titulo="Confirmar corrección administrativa"
                mensaje="Se registrará un evento compensatorio en la bitácora. Los registros históricos previos no se modificarán."
                etiquetaConfirmar="Sí, aplicar corrección"
                variante="danger"
                onClose={() => setConfirmar(false)}
                onConfirm={confirmarEnvio}
            />
        </>
    );
}

function CampoSoloLectura({ label, value }) {
    return (
        <div className="rounded-2xl border theme-border p-3 bg-black/[0.02] dark:bg-white/[0.02]">
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
            <p className="text-sm font-black theme-text-main m-0 mt-1">{value ?? '—'}</p>
        </div>
    );
}
