import React, { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Download, FileText, X, ZoomIn, ZoomOut } from 'lucide-react';
import {
    THEME_BTN_ICON,
    THEME_BTN_SECONDARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from '@/utils/geliaTheme';

const ZOOM_MIN = 0.5;
const ZOOM_MAX = 3;
const ZOOM_STEP = 0.25;

/** @returns {'imagen'|'pdf'} */
export function inferirTipoArchivoVisor({ mimeType, nombre, url } = {}) {
    const mime = (mimeType || '').toLowerCase();
    if (mime.startsWith('image/')) return 'imagen';
    if (mime === 'application/pdf') return 'pdf';
    const ref = `${nombre || ''} ${url || ''}`.toLowerCase();
    if (/\.(jpe?g|png|gif|webp|bmp|svg)(\?|$|#)/.test(ref)) return 'imagen';
    return 'pdf';
}

export function payloadArchivoRemision(doc) {
    if (!doc?.url) return null;
    return {
        url: doc.url,
        mimeType: doc.mime_type,
        titulo: 'Remisión',
        subtitulo: doc.nombre || doc.folio || 'Documento',
    };
}

export function payloadArchivoVoucher(ex) {
    if (!ex?.evidencia?.url) return null;
    return {
        url: ex.evidencia.url,
        mimeType: ex.evidencia.mime_type,
        titulo: 'Comprobante de pago',
        subtitulo: `Exhibición #${ex.numero_exhibicion} · ${ex.evidencia.nombre || 'Archivo'}`,
    };
}

/**
 * Modal flotante reutilizable para previsualizar imágenes o PDF dentro de la app.
 */
export default function ModalVisorArchivo({
    abierto,
    onCerrar,
    url,
    mimeType,
    titulo = 'Documento',
    subtitulo,
    descargarUrl,
}) {
    const [zoom, setZoom] = useState(1);
    const cerrar = useCallback(() => onCerrar?.(), [onCerrar]);
    const tipo = inferirTipoArchivoVisor({ mimeType, nombre: subtitulo, url });
    const hrefDescarga = descargarUrl || url;
    const esImagen = tipo === 'imagen';

    const acercar = useCallback(() => {
        setZoom((z) => Math.min(z + ZOOM_STEP, ZOOM_MAX));
    }, []);

    const alejar = useCallback(() => {
        setZoom((z) => Math.max(z - ZOOM_STEP, ZOOM_MIN));
    }, []);

    useEffect(() => {
        if (!abierto) return undefined;
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = prev;
        };
    }, [abierto]);

    useEffect(() => {
        if (!abierto) return undefined;
        const onKey = (e) => {
            if (e.key === 'Escape') cerrar();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [abierto, cerrar]);

    useEffect(() => {
        if (abierto) setZoom(1);
    }, [abierto, url]);

    const onWheelImagen = useCallback((e) => {
        if (!esImagen) return;
        e.preventDefault();
        const delta = e.deltaY < 0 ? ZOOM_STEP : -ZOOM_STEP;
        setZoom((z) => Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, z + delta)));
    }, [esImagen]);

    if (!abierto || !url || typeof document === 'undefined') return null;

    return createPortal(
        <div
            className={THEME_MODAL_OVERLAY}
            role="dialog"
            aria-modal="true"
            aria-label={titulo}
            onClick={cerrar}
        >
            <div
                className={`${THEME_MODAL_SHELL} w-full max-w-4xl flex flex-col modal-pop theme-text-main overflow-hidden`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center gap-3 p-4 border-b theme-border shrink-0">
                    <div
                        className="p-2 rounded-xl shrink-0 theme-element border theme-border"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 12%, transparent)' }}
                    >
                        <FileText className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div className="min-w-0 flex-1">
                        <h2 className="text-sm font-semibold theme-text-main m-0 truncate">{titulo}</h2>
                        {subtitulo && (
                            <p className="text-xs theme-text-muted m-0 mt-0.5 truncate">{subtitulo}</p>
                        )}
                    </div>
                    {esImagen && (
                        <div className="hidden sm:flex items-center gap-1 shrink-0">
                            <button
                                type="button"
                                onClick={alejar}
                                className={`${THEME_BTN_SECONDARY} h-9 px-2.5`}
                                aria-label="Alejar"
                                disabled={zoom <= ZOOM_MIN}
                            >
                                <ZoomOut className="w-4 h-4 shrink-0" />
                            </button>
                            <span className="text-xs font-semibold theme-text-muted tabular-nums min-w-[3rem] text-center">
                                {Math.round(zoom * 100)}%
                            </span>
                            <button
                                type="button"
                                onClick={acercar}
                                className={`${THEME_BTN_SECONDARY} h-9 px-2.5`}
                                aria-label="Acercar"
                                disabled={zoom >= ZOOM_MAX}
                            >
                                <ZoomIn className="w-4 h-4 shrink-0" />
                            </button>
                        </div>
                    )}
                    {hrefDescarga && (
                        <a
                            href={hrefDescarga}
                            download
                            target="_blank"
                            rel="noreferrer"
                            className={`${THEME_BTN_SECONDARY} hidden sm:inline-flex h-9 px-3 shrink-0`}
                            onClick={(e) => e.stopPropagation()}
                        >
                            <Download className="w-4 h-4 shrink-0" />
                            Descargar
                        </a>
                    )}
                    <button type="button" onClick={cerrar} className={`${THEME_BTN_ICON} shrink-0`} aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <div
                    className="flex-1 min-h-0 overflow-auto custom-scrollbar p-4 md:p-5 bg-[color-mix(in_srgb,var(--theme-element-bg)_40%,transparent)]"
                    onWheel={esImagen ? onWheelImagen : undefined}
                >
                    {esImagen ? (
                        <img
                            src={url}
                            alt={subtitulo || titulo}
                            className="max-w-full max-h-[70vh] mx-auto rounded-lg object-contain border theme-border theme-surface-solid shadow-sm transition-transform origin-center"
                            style={{ transform: `scale(${zoom})` }}
                            draggable={false}
                        />
                    ) : (
                        <iframe
                            src={url}
                            title={subtitulo || titulo}
                            className="w-full h-[70vh] rounded-lg border theme-border theme-surface-solid"
                        />
                    )}
                </div>
                {esImagen && (
                    <div className="sm:hidden flex items-center justify-center gap-2 p-3 border-t theme-border shrink-0">
                        <button type="button" onClick={alejar} className={`${THEME_BTN_SECONDARY} h-9 px-3`} aria-label="Alejar" disabled={zoom <= ZOOM_MIN}>
                            <ZoomOut className="w-4 h-4" />
                        </button>
                        <span className="text-xs font-semibold theme-text-muted tabular-nums min-w-[3rem] text-center">
                            {Math.round(zoom * 100)}%
                        </span>
                        <button type="button" onClick={acercar} className={`${THEME_BTN_SECONDARY} h-9 px-3`} aria-label="Acercar" disabled={zoom >= ZOOM_MAX}>
                            <ZoomIn className="w-4 h-4" />
                        </button>
                    </div>
                )}
            </div>
        </div>,
        document.body,
    );
}
