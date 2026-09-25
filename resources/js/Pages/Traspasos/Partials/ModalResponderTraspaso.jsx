import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { router, useForm } from '@inertiajs/react';
import { X, CheckCircle2, AlertOctagon, Upload, Send } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import {
    BTN_CERRAR_MODAL,
    MODAL_BODY,
    MODAL_HEADER,
    TEXTO_ERROR,
} from './traspasosStyles';
import { compressImageToWebp } from '../../../utils/compressImage';
import RevisionFisicaTraspasoPanel, { inicializarRevisionesDesdeTraspaso } from './RevisionFisicaTraspasoPanel';
import { requiereComentario, requiereEvidencia } from './revisionFisicaTraspasoUtils';

export default function ModalResponderTraspaso({ onClose, traspaso, estadoId, modo = 'responder', onExito }) {
    const esAprobacion = modo === 'responder';
    const esError = modo === 'reportar';
    const [preview, setPreview] = useState(null);
    const [revisiones, setRevisiones] = useState([]);
    const [enviando, setEnviando] = useState(false);

    const almacenBusquedaId = traspaso?.almacen_origen?.id || traspaso?.almacen_origen_id;

    const { data, setData, post, processing, errors } = useForm({
        catalogo_estado_solicitud_id: estadoId,
        motivo: '',
        folio_traspaso: '',
        evidencia_respuesta: null,
        _method: 'put',
    });

    useEffect(() => {
        if (esAprobacion && traspaso?.productos?.length) {
            setRevisiones(inicializarRevisionesDesdeTraspaso(traspaso));
        }
    }, [esAprobacion, traspaso?.id]);

    const aplicarEvidencia = async (file) => {
        if (!file) return;
        if (file.type.startsWith('image/')) {
            try {
                const compressed = await compressImageToWebp(file, { maxBytes: 5120 * 1024 });
                setData('evidencia_respuesta', compressed);
                setPreview(URL.createObjectURL(compressed));
            } catch {
                setData('evidencia_respuesta', file);
                setPreview(URL.createObjectURL(file));
            }
        } else {
            setData('evidencia_respuesta', file);
            setPreview(null);
        }
    };

    const handlePaste = async (e) => {
        const items = e.clipboardData?.items;
        if (!items) return;
        for (const item of items) {
            if (item.type.indexOf('image') !== -1) {
                e.preventDefault();
                await aplicarEvidencia(item.getAsFile());
                break;
            }
        }
    };

    const cerrar = () => {
        if (preview) URL.revokeObjectURL(preview);
        onClose();
    };

    const enviarAprobacionConRevisiones = () => {
        for (let i = 0; i < revisiones.length; i += 1) {
            const r = revisiones[i];
            if (requiereComentario(r.estado_fisico) && !String(r.comentario || '').trim()) {
                alert(`La pieza «${r.descripcion_producto}» requiere comentario.`);
                return;
            }
            if (requiereEvidencia(r.estado_fisico) && !(r.evidencias || []).length) {
                alert(`La pieza «${r.descripcion_producto}» requiere evidencia fotográfica.`);
                return;
            }
        }

        const form = new FormData();
        form.append('_method', 'put');
        form.append('catalogo_estado_solicitud_id', String(estadoId));
        form.append('folio_traspaso', data.folio_traspaso);
        if (data.motivo) form.append('motivo', data.motivo);
        if (data.evidencia_respuesta) form.append('evidencia_respuesta', data.evidencia_respuesta);

        revisiones.forEach((r, i) => {
            form.append(`revisiones[${i}][solicitud_traspaso_producto_id]`, String(r.solicitud_traspaso_producto_id));
            if (r.producto_id) form.append(`revisiones[${i}][producto_id]`, String(r.producto_id));
            form.append(`revisiones[${i}][descripcion_producto]`, r.descripcion_producto || '');
            form.append(`revisiones[${i}][sku]`, r.sku || '');
            form.append(`revisiones[${i}][estado_fisico]`, r.estado_fisico || 'bueno');
            if (r.comentario) form.append(`revisiones[${i}][comentario]`, r.comentario);
            form.append(`revisiones[${i}][unica_pieza]`, r.unica_pieza ? '1' : '0');
            form.append(`revisiones[${i}][mejor_ejemplar]`, r.mejor_ejemplar ? '1' : '0');
            (r.evidencias || []).forEach((file, j) => {
                form.append(`revisiones[${i}][evidencias][${j}]`, file);
            });
        });

        setEnviando(true);
        router.post(route('traspasos.actualizar_estado', traspaso.id), form, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                if (preview) URL.revokeObjectURL(preview);
                onExito?.();
                cerrar();
            },
            onFinish: () => setEnviando(false),
        });
    };

    const enviar = (e) => {
        e.preventDefault();
        if (esAprobacion) {
            if (!data.folio_traspaso?.trim()) {
                alert('Indique el folio del traspaso.');
                return;
            }
            if (!data.evidencia_respuesta) {
                alert('Adjunte la captura del traspaso.');
                return;
            }
            if (!revisiones.length) {
                alert('Debe cargar la revisión de productos.');
                return;
            }
            enviarAprobacionConRevisiones();
            return;
        }
        post(route('traspasos.actualizar_estado', traspaso.id), {
            forceFormData: true,
            onSuccess: () => {
                if (preview) URL.revokeObjectURL(preview);
                onExito?.();
                cerrar();
            },
        });
    };

    const busy = processing || enviando;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={cerrar}>
            <div
                onPaste={handlePaste}
                className={`${THEME_MODAL_SHELL} max-w-3xl w-full flex flex-col text-left`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className={MODAL_HEADER}>
                    <div className="flex items-center gap-3 min-w-0">
                        {esError
                            ? <AlertOctagon className="w-7 h-7 shrink-0 theme-text-peligro" />
                            : <CheckCircle2 className="w-7 h-7 shrink-0 theme-text-primario" />}
                        <div className="min-w-0">
                            <h2 className="text-lg font-black italic theme-text-main uppercase m-0 leading-tight">
                                {esError ? 'Reportar Error_' : 'Responder Traspaso_'}
                            </h2>
                            <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">{traspaso.folio}</p>
                            {traspaso.sucursal_solicitante?.nombre && (
                                <p className="text-[10px] font-bold theme-text-main mt-1 m-0">
                                    Sucursal: {traspaso.sucursal_solicitante.nombre}
                                </p>
                            )}
                        </div>
                    </div>
                    <button type="button" onClick={cerrar} className={BTN_CERRAR_MODAL} aria-label="Cerrar"><X className="w-5 h-5" /></button>
                </div>

                <form onSubmit={enviar} className={MODAL_BODY}>
                    {esAprobacion && (
                        <>
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Folio del traspaso</label>
                                <input
                                    value={data.folio_traspaso}
                                    onChange={(e) => setData('folio_traspaso', e.target.value)}
                                    className="theme-input w-full px-4 py-3 font-bold font-mono"
                                    placeholder="Folio generado"
                                    required
                                />
                                {errors.folio_traspaso && <p className={TEXTO_ERROR}>{errors.folio_traspaso}</p>}
                            </div>
                            <RevisionFisicaTraspasoPanel
                                traspaso={traspaso}
                                almacenBusquedaId={almacenBusquedaId}
                                revisiones={revisiones}
                                setRevisiones={setRevisiones}
                            />
                            {errors.revisiones && <p className={TEXTO_ERROR}>{errors.revisiones}</p>}
                        </>
                    )}

                    <div className="space-y-2">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                            {esError ? 'Motivo (obligatorio)' : 'Observaciones'}
                        </label>
                        <textarea
                            value={data.motivo}
                            onChange={(e) => setData('motivo', e.target.value)}
                            className="theme-input w-full px-4 py-3 font-bold min-h-[90px]"
                            required={esError}
                        />
                        {errors.motivo && <p className={TEXTO_ERROR}>{errors.motivo}</p>}
                    </div>

                    {esAprobacion && (
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                                Captura del traspaso (Ctrl+V o archivo)
                            </label>
                            <label className="flex flex-col items-center justify-center gap-2 p-6 rounded-xl border-2 border-dashed theme-border cursor-pointer hover:border-[var(--color-primario)] transition-colors">
                                <Upload className="w-6 h-6 theme-text-muted" />
                                <span className="text-[10px] font-black uppercase theme-text-muted">Pegar imagen o seleccionar</span>
                                <input type="file" accept="image/*,.pdf" className="hidden" onChange={(e) => aplicarEvidencia(e.target.files?.[0])} />
                            </label>
                            {preview && (
                                <img src={preview} alt="Captura" className="max-h-48 rounded-xl border theme-border object-contain mx-auto" />
                            )}
                            {data.evidencia_respuesta && !preview && (
                                <p className="text-xs font-bold theme-text-main m-0">{data.evidencia_respuesta.name}</p>
                            )}
                            {errors.evidencia_respuesta && <p className={TEXTO_ERROR}>{errors.evidencia_respuesta}</p>}
                        </div>
                    )}

                    <button type="submit" disabled={busy} className="theme-btn-primary w-full !py-3">
                        <Send className="w-4 h-4" /> {busy ? 'Guardando…' : 'Confirmar'}
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}
