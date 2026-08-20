import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import {
    ChevronLeft, ChevronRight, Download, ExternalLink, RotateCw, X, ZoomIn, ZoomOut,
} from 'lucide-react';
import {
    THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, BTN_PRIMARY, BTN_SECONDARY, deferModalAction,
    formatearFechaHoraAuditoria,
} from './pedidosBmaStyles';
import useDispositivoCampo from '../../Activos/Partials/useDispositivoCampo';
import VisorPdfPaginas from './VisorPdfPaginas';

const esPdf = (doc) => {
    const nombre = String(doc?.nombre_original || doc?.url || '').toLowerCase();
    const mime = String(doc?.mime_type || doc?.mime || '').toLowerCase();
    if (nombre.endsWith('.pdf') || mime.includes('pdf')) return true;
    if (doc?.tipo === 'pdf_pedido' && !mime.startsWith('image/')) return true;
    return false;
};

const esImagen = (doc) => {
    if (esPdf(doc)) return false;
    const mime = String(doc?.mime_type || doc?.mime || '');
    const nombre = String(doc?.nombre_original || doc?.url || '').toLowerCase();
    return mime.startsWith('image/') || /\.(jpe?g|png|webp|gif)$/.test(nombre);
};

/**
 * Visor de segundo nivel (portal). No cierra el modal del pedido.
 */
export default function ModalVistaPreviaDocumento({
    abierto,
    documento = null,
    documentos = null,
    indice = 0,
    onClose,
    onChangeIndice = null,
}) {
    const lista = useMemo(() => {
        if (Array.isArray(documentos) && documentos.length) {
            return documentos.filter((d) => d?.url);
        }
        return documento?.url ? [documento] : [];
    }, [documento, documentos]);

    const [idx, setIdx] = useState(0);
    const [zoom, setZoom] = useState(1);
    const [rotacion, setRotacion] = useState(0);
    const { esCampo, esMovil } = useDispositivoCampo();

    const ir = useCallback((delta) => {
        setIdx((prev) => {
            const len = lista.length || 1;
            const next = (prev + delta + len) % len;
            setZoom(1);
            setRotacion(0);
            if (typeof onChangeIndice === 'function') onChangeIndice(next);
            return next;
        });
    }, [lista.length, onChangeIndice]);

    useEffect(() => {
        if (!abierto) return undefined;
        document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = ''; };
    }, [abierto]);

    useEffect(() => {
        if (!abierto) return;
        const next = Math.min(Math.max(Number(indice) || 0, 0), Math.max(lista.length - 1, 0));
        setIdx(next);
        setZoom(1);
        setRotacion(0);
    }, [abierto, indice, lista.length, documento?.id, documento?.url]);

    useEffect(() => {
        if (!abierto) return undefined;
        const onKey = (e) => {
            if (e.key === 'Escape') {
                e.stopPropagation();
                deferModalAction(onClose);
                return;
            }
            if (lista.length > 1 && e.key === 'ArrowLeft') {
                e.preventDefault();
                ir(-1);
            }
            if (lista.length > 1 && e.key === 'ArrowRight') {
                e.preventDefault();
                ir(1);
            }
        };
        window.addEventListener('keydown', onKey, true);
        return () => window.removeEventListener('keydown', onKey, true);
    }, [abierto, lista.length, ir, onClose]);

    if (!abierto || lista.length === 0) return null;

    const actual = lista[Math.min(idx, lista.length - 1)];
    const pdf = esPdf(actual);
    const imagen = esImagen(actual);
    const titulo = actual.nombre_original || actual.tipo || 'Vista previa';
    const pdfEnPaginas = pdf && (esCampo || esMovil);

    const cerrar = (e) => {
        e?.stopPropagation?.();
        deferModalAction(onClose);
    };

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`}
            style={{ zIndex: 'calc(var(--gelia-z-modal) + 20)' }}
            onClick={cerrar}
            role="dialog"
            aria-modal="true"
            aria-label="Visor de evidencia"
        >
            <div
                className={`${THEME_MODAL_SHELL} max-w-5xl w-full flex flex-col`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="min-w-0">
                        <h2 className="text-lg font-black italic uppercase theme-text-main m-0 truncate">{titulo}</h2>
                        <div className="flex flex-wrap gap-x-3 gap-y-1 mt-1 text-[10px] font-bold theme-text-muted uppercase">
                            {actual.tipo && <span>Tipo: {String(actual.tipo).replace(/_/g, ' ')}</span>}
                            {(actual.autor?.name || actual.subido_por?.name || actual.uploaded_by?.name) && (
                                <span>Por: {actual.autor?.name || actual.subido_por?.name || actual.uploaded_by?.name}</span>
                            )}
                            {(actual.created_at || actual.fecha) && (
                                <span>{formatearFechaHoraAuditoria(actual.created_at || actual.fecha)}</span>
                            )}
                            {lista.length > 1 && (
                                <span>{idx + 1} / {lista.length}</span>
                            )}
                        </div>
                        {(actual.comentario || actual.comment) && (
                            <p className="text-xs theme-text-main font-bold mt-2 m-0">
                                {actual.comentario || actual.comment}
                            </p>
                        )}
                    </div>
                    <button type="button" onClick={cerrar} className="p-2 min-h-[44px] min-w-[44px] rounded-xl theme-element border theme-border theme-text-main outline-none shrink-0 inline-flex items-center justify-center" aria-label="Cerrar vista">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <div className="sm:hidden px-4 pt-3 shrink-0">
                    <button type="button" onClick={cerrar} className={`${BTN_PRIMARY} w-full min-h-[44px] inline-flex items-center justify-center gap-2`}>
                        <X className="w-4 h-4" /> Cerrar vista
                    </button>
                </div>
                <div className="gelia-modal-body flex-1 min-h-0 p-0 flex items-center justify-center theme-element relative overflow-auto">
                    {lista.length > 1 && (
                        <>
                            <button
                                type="button"
                                onClick={() => ir(-1)}
                                className="absolute left-2 z-10 p-2 min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-full theme-element border theme-border outline-none"
                                aria-label="Anterior"
                            >
                                <ChevronLeft className="w-5 h-5" />
                            </button>
                            <button
                                type="button"
                                onClick={() => ir(1)}
                                className="absolute right-2 z-10 p-2 min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-full theme-element border theme-border outline-none"
                                aria-label="Siguiente"
                            >
                                <ChevronRight className="w-5 h-5" />
                            </button>
                        </>
                    )}
                    {pdf ? (
                        pdfEnPaginas ? (
                            <VisorPdfPaginas url={actual.url} titulo={titulo} className="w-full self-stretch" />
                        ) : (
                            <iframe
                                src={actual.url}
                                title={titulo}
                                className="w-full border-0"
                                style={{ height: 'min(70vh, calc(100dvh - 14rem))' }}
                            />
                        )
                    ) : imagen ? (
                        <img
                            src={actual.url}
                            alt={titulo}
                            className="max-w-full object-contain p-4 transition-transform origin-center"
                            style={{
                                maxHeight: 'min(70vh, calc(100dvh - 14rem))',
                                transform: `scale(${zoom}) rotate(${rotacion}deg)`,
                            }}
                        />
                    ) : (
                        <div className="p-8 text-center space-y-3">
                            <p className="text-sm font-bold theme-text-muted m-0">
                                Este archivo no se puede previsualizar aquí.
                            </p>
                            <a
                                href={actual.url}
                                download={actual.nombre_original || undefined}
                                className={`${BTN_PRIMARY} inline-flex items-center gap-2 min-h-[44px]`}
                            >
                                <Download className="w-4 h-4" />
                                Descargar archivo
                            </a>
                        </div>
                    )}
                </div>
                <div className="gelia-modal-footer p-4 md:p-6 pb-[max(1rem,env(safe-area-inset-bottom))] flex flex-col sm:flex-row-reverse sm:flex-wrap justify-end gap-3 border-t theme-border">
                    {imagen && (
                        <div className="flex flex-wrap gap-2 mr-auto">
                            <button type="button" className={`${BTN_SECONDARY} min-h-[44px] px-3`} onClick={() => setZoom((z) => Math.min(z + 0.25, 3))} aria-label="Acercar">
                                <ZoomIn className="w-4 h-4" />
                            </button>
                            <button type="button" className={`${BTN_SECONDARY} min-h-[44px] px-3`} onClick={() => setZoom((z) => Math.max(z - 0.25, 0.5))} aria-label="Alejar">
                                <ZoomOut className="w-4 h-4" />
                            </button>
                            <button type="button" className={`${BTN_SECONDARY} min-h-[44px] px-3`} onClick={() => setRotacion((r) => (r + 90) % 360)} aria-label="Girar">
                                <RotateCw className="w-4 h-4" />
                            </button>
                        </div>
                    )}
                    <a
                        href={actual.url}
                        download={actual.nombre_original || undefined}
                        className={`${BTN_PRIMARY} w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center gap-2`}
                    >
                        <Download className="w-4 h-4 shrink-0" />
                        Descargar
                    </a>
                    <a
                        href={actual.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={`${BTN_SECONDARY} w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center gap-2`}
                    >
                        <ExternalLink className="w-4 h-4 shrink-0" />
                        Abrir en pestaña
                    </a>
                    <button type="button" onClick={cerrar} className={`${BTN_SECONDARY} w-full sm:w-auto min-h-[44px]`}>
                        Cerrar
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}

export function MiniaturaDocumento({ documento, onVer, className = 'block w-20 h-20 rounded-xl overflow-hidden border theme-border theme-element cursor-pointer' }) {
    const pdf = esPdf(documento);

    return (
        <button
            type="button"
            onClick={() => onVer(documento)}
            className={`${className} group outline-none hover:border-[var(--color-primario)] transition-colors`}
            title={documento.nombre_original || 'Ver documento'}
        >
            {pdf ? (
                <div className="w-full h-full flex flex-col items-center justify-center gap-0.5 theme-element text-[9px] font-black uppercase theme-text-muted group-hover:scale-105 transition-transform duration-200">
                    <span>PDF</span>
                </div>
            ) : (
                <img
                    src={documento.url}
                    alt={documento.nombre_original}
                    className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-200"
                />
            )}
        </button>
    );
}
