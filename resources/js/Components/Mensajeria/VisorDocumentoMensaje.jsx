import React, { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { X, Download, Loader2, AlertTriangle, FileSpreadsheet, FileText } from 'lucide-react';
import axios from 'axios';
import {
    tipoPrevisualizacionAdjunto,
    adjuntoExcedeLimitePreview,
    urlAdjuntoMensajeria,
} from '@/utils/adjuntoPreview';
import { loadXlsx, loadMammoth } from '@/utils/loadPreviewLibs';

const MAX_FILAS_EXCEL = 200;
const MAX_COLUMNAS_EXCEL = 30;

async function fetchAdjuntoBlob(adjuntoId) {
    const { data } = await axios.get(urlAdjuntoMensajeria(adjuntoId, { inline: true }), {
        responseType: 'blob',
    });
    return data;
}

async function renderExcelHtml(arrayBuffer) {
    const XLSX = await loadXlsx();
    const libro = XLSX.read(arrayBuffer, { type: 'array' });
    const hoja = libro.SheetNames[0];
    if (!hoja) {
        throw new Error('El archivo no tiene hojas.');
    }

    const sheet = libro.Sheets[hoja];
    const ref = sheet['!ref'];
    if (!ref) {
        return { html: '<p class="p-4 text-sm">Hoja vacía.</p>', hoja };
    }

    const range = XLSX.utils.decode_range(ref);
    const filas = Math.min(range.e.r - range.s.r + 1, MAX_FILAS_EXCEL);
    const cols = Math.min(range.e.c - range.s.c + 1, MAX_COLUMNAS_EXCEL);
    const recorte = {
        s: { r: range.s.r, c: range.s.c },
        e: { r: range.s.r + filas - 1, c: range.s.c + cols - 1 },
    };
    sheet['!ref'] = XLSX.utils.encode_range(recorte);

    const tabla = XLSX.utils.sheet_to_html(sheet, { id: 'gelia-excel-preview' });
    const aviso = (range.e.r - range.s.r + 1 > MAX_FILAS_EXCEL || range.e.c - range.s.c + 1 > MAX_COLUMNAS_EXCEL)
        ? '<p class="text-[10px] font-bold theme-text-muted p-2 border-b theme-border">Vista parcial (primeras filas y columnas)</p>'
        : '';

    return { html: aviso + tabla, hoja };
}

export default function VisorDocumentoMensaje({ adjunto, onCerrar }) {
    const tipo = tipoPrevisualizacionAdjunto(adjunto);
    const nombre = adjunto?.nombre_original || 'Documento';
    const downloadUrl = urlAdjuntoMensajeria(adjunto.id);

    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(null);
    const [pdfUrl, setPdfUrl] = useState(null);
    const [htmlPreview, setHtmlPreview] = useState('');
    const [metaExcel, setMetaExcel] = useState(null);

    const cerrar = useCallback(() => {
        if (pdfUrl) URL.revokeObjectURL(pdfUrl);
        onCerrar?.();
    }, [onCerrar, pdfUrl]);

    useEffect(() => {
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = prev; };
    }, []);

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') cerrar();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [cerrar]);

    useEffect(() => {
        if (!adjunto?.id || !tipo) return undefined;

        let cancelado = false;

        const cargar = async () => {
            setCargando(true);
            setError(null);
            setHtmlPreview('');
            setMetaExcel(null);

            if (adjuntoExcedeLimitePreview(adjunto)) {
                setError('El archivo es muy grande para previsualizarlo en el navegador. Descárgalo para abrirlo.');
                setCargando(false);
                return;
            }

            try {
                const blob = await fetchAdjuntoBlob(adjunto.id);
                if (cancelado) return;

                if (tipo === 'pdf') {
                    setPdfUrl((prev) => {
                        if (prev) URL.revokeObjectURL(prev);
                        return URL.createObjectURL(blob);
                    });
                } else if (tipo === 'excel') {
                    const buffer = await blob.arrayBuffer();
                    const { html, hoja } = await renderExcelHtml(buffer);
                    if (!cancelado) {
                        setHtmlPreview(html);
                        setMetaExcel(hoja);
                    }
                } else if (tipo === 'word') {
                    const buffer = await blob.arrayBuffer();
                    const esDocx = (adjunto.nombre_original || '').toLowerCase().endsWith('.docx')
                        || adjunto.mime?.includes('wordprocessingml');

                    if (!esDocx) {
                        setError('Los archivos .doc antiguos requieren descarga. Usa formato .docx para vista previa.');
                        return;
                    }

                    const mammoth = await loadMammoth();
                    const resultado = await mammoth.convertToHtml({ arrayBuffer: buffer });
                    if (!cancelado) {
                        setHtmlPreview(resultado.value || '<p class="text-sm p-4">Documento vacío.</p>');
                    }
                }
            } catch {
                if (!cancelado) {
                    setError('No se pudo generar la vista previa. Intenta descargar el archivo.');
                }
            } finally {
                if (!cancelado) setCargando(false);
            }
        };

        cargar();

        return () => {
            cancelado = true;
        };
    }, [adjunto?.id, adjunto?.mime, adjunto?.nombre_original, adjunto?.tamano, tipo]);

    useEffect(() => () => {
        if (pdfUrl) URL.revokeObjectURL(pdfUrl);
    }, [pdfUrl]);

    if (typeof document === 'undefined' || !adjunto || !tipo) return null;

    const IconoTipo = tipo === 'excel' ? FileSpreadsheet : FileText;

    return createPortal(
        <div
            className="gelia-modal-overlay gelia-visor-documento z-[10050] flex flex-col p-3 sm:p-6"
            onClick={cerrar}
            role="dialog"
            aria-modal="true"
            aria-label={`Vista previa: ${nombre}`}
        >
            <div
                className="gelia-modal-shell gelia-visor-documento-shell w-full max-w-5xl max-h-[92dvh] flex flex-col modal-pop theme-text-main overflow-hidden"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center gap-3 p-4 border-b theme-border shrink-0">
                    <div
                        className="p-2 rounded-xl shrink-0"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 12%, transparent)' }}
                    >
                        <IconoTipo className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div className="min-w-0 flex-1">
                        <h2 className="text-sm font-black uppercase italic theme-text-main m-0 truncate">
                            Vista previa_
                        </h2>
                        <p className="text-[10px] font-bold theme-text-muted m-0 mt-0.5 truncate">{nombre}</p>
                    </div>
                    <a
                        href={downloadUrl}
                        download={nombre}
                        className="hidden sm:flex items-center gap-1.5 px-3 py-2 rounded-xl theme-element border theme-border text-[10px] font-black uppercase tracking-wider hover:border-[var(--color-primario)] transition-colors shrink-0"
                    >
                        <Download className="w-4 h-4" />
                        Descargar
                    </a>
                    <button
                        type="button"
                        onClick={cerrar}
                        className="p-2 rounded-full theme-element border theme-border theme-text-muted hover:theme-text-main outline-none shrink-0"
                        aria-label="Cerrar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="flex-1 min-h-0 overflow-auto custom-scrollbar bg-black/[0.02] dark:bg-white/[0.02]">
                    {cargando && (
                        <div className="flex flex-col items-center justify-center py-20 gap-3">
                            <Loader2 className="w-8 h-8 animate-spin opacity-50" />
                            <p className="text-xs font-bold theme-text-muted uppercase tracking-widest m-0">
                                Generando vista previa…
                            </p>
                        </div>
                    )}

                    {!cargando && error && (
                        <div className="flex flex-col items-center justify-center py-16 px-6 text-center gap-3">
                            <AlertTriangle className="w-10 h-10 text-amber-500" />
                            <p className="text-sm theme-text-main m-0 max-w-md">{error}</p>
                            <a
                                href={downloadUrl}
                                download={nombre}
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider text-white"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Download className="w-4 h-4" />
                                Descargar archivo
                            </a>
                        </div>
                    )}

                    {!cargando && !error && tipo === 'pdf' && pdfUrl && (
                        <iframe
                            src={pdfUrl}
                            title={nombre}
                            className="w-full h-[min(75dvh,720px)] border-0 bg-white"
                        />
                    )}

                    {!cargando && !error && htmlPreview && (
                        <div className="gelia-visor-documento-contenido p-4 sm:p-6">
                            {metaExcel && (
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-3 m-0">
                                    Hoja: {metaExcel}
                                </p>
                            )}
                            <div
                                className="gelia-visor-documento-html theme-surface border theme-border rounded-2xl overflow-auto max-h-[70dvh] p-4 text-sm"
                                dangerouslySetInnerHTML={{ __html: htmlPreview }}
                            />
                        </div>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}
