import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { X, PackageCheck, Send } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';
import {
    BTN_CERRAR_MODAL,
    MODAL_BODY,
    MODAL_HEADER,
} from '../../Partials/traspasosStyles';
import RevisionFisicaTraspasoPanel, { inicializarRevisionesDesdeTraspaso } from '../../Partials/RevisionFisicaTraspasoPanel';
import { requiereComentario, requiereEvidencia } from '../../Partials/revisionFisicaTraspasoUtils';

export default function ModalRecepcionCedis({ traspaso, onClose, onExito }) {
    const [revisiones, setRevisiones] = useState([]);
    const [enviando, setEnviando] = useState(false);

    const almacenBusquedaId = traspaso?.almacen_origen?.id || traspaso?.almacen_origen_id;

    useEffect(() => {
        if (traspaso?.productos?.length) {
            setRevisiones(inicializarRevisionesDesdeTraspaso(traspaso));
        }
    }, [traspaso?.id]);

    const enviar = (e) => {
        e.preventDefault();
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
        router.post(route('traspasos.cedis.confirmar', traspaso.id), form, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                onExito?.();
                onClose();
            },
            onFinish: () => setEnviando(false),
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-3xl w-full flex flex-col text-left`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className={MODAL_HEADER}>
                    <div>
                        <h2 className="text-lg font-black italic uppercase theme-text-main m-0 flex items-center gap-2">
                            <PackageCheck className="w-6 h-6 shrink-0 theme-text-primario" />
                            Recepción CEDIS
                        </h2>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">{traspaso.folio}</p>
                        {traspaso.sucursal_solicitante?.nombre && (
                            <p className="text-[10px] font-bold theme-text-main mt-1 m-0">
                                Sucursal solicitante: {traspaso.sucursal_solicitante.nombre}
                            </p>
                        )}
                    </div>
                    <button type="button" onClick={onClose} className={BTN_CERRAR_MODAL} aria-label="Cerrar"><X className="w-5 h-5" /></button>
                </div>
                <form onSubmit={enviar} className={`${MODAL_BODY} space-y-4`}>
                    <RevisionFisicaTraspasoPanel
                        traspaso={traspaso}
                        almacenBusquedaId={almacenBusquedaId}
                        revisiones={revisiones}
                        setRevisiones={setRevisiones}
                    />
                    <button type="submit" disabled={enviando} className="theme-btn-primary w-full !py-3">
                        <Send className="w-4 h-4" /> {enviando ? 'Confirmando…' : 'Confirmar recepción'}
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}
