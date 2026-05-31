import React, { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import {
    X,
    Send,
    Loader2,
    FileText,
    AlertTriangle,
    Image as ImageIcon,
    Film,
} from 'lucide-react';
import { loadXlsx, loadMammoth } from '@/utils/loadPreviewLibs';
import {
    aplicarNombreArchivo,
    fileConNombre,
    formatearTamanoArchivo,
    tipoPreviewArchivoLocal,
    MAX_PREVIEW_LOCAL_BYTES,
} from '@/utils/mensajeriaArchivoLocal';

const MAX_FILAS_EXCEL = 120;
const MAX_COLUMNAS_EXCEL = 24;

async function renderExcelHtmlDesdeArrayBuffer(arrayBuffer) {
    const XLSX = await loadXlsx();
    const libro = XLSX.read(arrayBuffer, { type: 'array' });
    const hoja = libro.SheetNames[0];
    if (!hoja) throw new Error('Sin hojas');

    const sheet = libro.Sheets[hoja];
    const ref = sheet['!ref'];
    if (!ref) return { html: '<p class="p-4 text-sm m-0">Hoja vacía.</p>', hoja };

    const range = XLSX.utils.decode_range(ref);
    const filas = Math.min(range.e.r - range.s.r + 1, MAX_FILAS_EXCEL);
    const cols = Math.min(range.e.c - range.s.c + 1, MAX_COLUMNAS_EXCEL);
    sheet['!ref'] = XLSX.utils.encode_range({
        s: { r: range.s.r, c: range.s.c },
        e: { r: range.s.r + filas - 1, c: range.s.c + cols - 1 },
    });

    const tabla = XLSX.utils.sheet_to_html(sheet);
    const aviso = (range.e.r - range.s.r + 1 > MAX_FILAS_EXCEL)
        ? '<p class="text-[10px] font-bold theme-text-muted p-2 border-b theme-border m-0">Vista parcial</p>'
        : '';

    return { html: aviso + tabla, hoja };
}

function VistaPreviaLocal({ pendiente }) {
    const { file, tipo, previewUrl } = pendiente;
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const [htmlPreview, setHtmlPreview] = useState('');

    const tipoDoc = tipoPreviewArchivoLocal(file);
    const excede = (file?.size || 0) > MAX_PREVIEW_LOCAL_BYTES;

    useEffect(() => {
        setHtmlPreview('');
        setError(null);
        setCargando(false);

        if (!file || excede || !tipoDoc || tipoDoc === 'pdf') return;

        let cancelado = false;
        setCargando(true);

        const reader = new FileReader();
        reader.onload = async () => {
            try {
                if (tipoDoc === 'excel') {
                    const { html } = await renderExcelHtmlDesdeArrayBuffer(reader.result);
                    if (!cancelado) setHtmlPreview(html);
                } else if (tipoDoc === 'word') {
                    const mammoth = await loadMammoth();
                    const { value } = await mammoth.extractRawText({ arrayBuffer: reader.result });
                    if (!cancelado) {
                        setHtmlPreview(
                            `<div class="gelia-visor-documento-html p-4 text-sm whitespace-pre-wrap">${escapeHtml(value || '(vacío)')}</div>`
                        );
                    }
                }
            } catch (err) {
                if (!cancelado) setError(err?.message || 'No se pudo previsualizar');
            } finally {
                if (!cancelado) setCargando(false);
            }
        };
        reader.onerror = () => {
            if (!cancelado) {
                setError('Error al leer el archivo');
                setCargando(false);
            }
        };
        reader.readAsArrayBuffer(file);

        return () => { cancelado = true; };
    }, [file, tipoDoc, excede]);

    if (excede) {
        return (
            <div className="gelia-preparar-adjunto-preview gelia-preparar-adjunto-preview--aviso">
                <AlertTriangle className="w-8 h-8 opacity-50" />
                <p className="text-xs theme-text-muted m-0 mt-2 text-center">
                    Archivo muy grande para vista previa ({formatearTamanoArchivo(file.size)}).
                    <br />Podrás enviarlo igual.
                </p>
            </div>
        );
    }

    if (tipo === 'imagen') {
        return (
            <div className="gelia-preparar-adjunto-preview">
                <img src={previewUrl} alt="" className="max-h-full max-w-full object-contain rounded-lg" />
            </div>
        );
    }

    if (tipo === 'video') {
        return (
            <div className="gelia-preparar-adjunto-preview">
                <video src={previewUrl} controls className="max-h-full max-w-full rounded-lg" />
            </div>
        );
    }

    if (tipoDoc === 'pdf') {
        return (
            <div className="gelia-preparar-adjunto-preview">
                <iframe
                    src={previewUrl}
                    title="Vista previa PDF"
                    className="w-full h-full rounded-lg border-0 bg-white"
                />
            </div>
        );
    }

    if (cargando) {
        return (
            <div className="gelia-preparar-adjunto-preview gelia-preparar-adjunto-preview--aviso">
                <Loader2 className="w-8 h-8 animate-spin opacity-50" />
                <p className="text-xs theme-text-muted m-0 mt-2">Generando vista previa…</p>
            </div>
        );
    }

    if (error) {
        return (
            <div className="gelia-preparar-adjunto-preview gelia-preparar-adjunto-preview--aviso">
                <FileText className="w-10 h-10 opacity-40" />
                <p className="text-xs theme-text-muted m-0 mt-2">{error}</p>
            </div>
        );
    }

    if (htmlPreview) {
        return (
            <div
                className="gelia-preparar-adjunto-preview gelia-preparar-adjunto-preview--documento overflow-auto custom-scrollbar"
                dangerouslySetInnerHTML={{ __html: htmlPreview }}
            />
        );
    }

    return (
        <div className="gelia-preparar-adjunto-preview gelia-preparar-adjunto-preview--aviso">
            {tipo === 'imagen' ? <ImageIcon className="w-10 h-10 opacity-40" /> : <FileText className="w-10 h-10 opacity-40" />}
            <p className="text-sm font-bold m-0 mt-3 truncate max-w-full px-4">{file.name}</p>
            <p className="text-[11px] theme-text-muted m-0 mt-1">{formatearTamanoArchivo(file.size)}</p>
        </div>
    );
}

function escapeHtml(texto) {
    return String(texto)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

export default function ModalPrepararAdjunto({
    pendiente,
    onCerrar,
    onConfirmar,
    enviando = false,
}) {
    const [nombre, setNombre] = useState('');
    const [comentario, setComentario] = useState('');

    useEffect(() => {
        if (pendiente?.file) {
            setNombre(pendiente.file.name);
            setComentario('');
        }
    }, [pendiente?.file]);

    useEffect(() => {
        if (!pendiente) return;
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        const onKey = (e) => {
            if (e.key === 'Escape' && !enviando) onCerrar?.();
        };
        window.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = prev;
            window.removeEventListener('keydown', onKey);
        };
    }, [pendiente, onCerrar, enviando]);

    const handleEnviar = useCallback(async () => {
        if (!pendiente?.file || enviando) return;

        const nombreFinal = aplicarNombreArchivo(pendiente.file.name, nombre);
        const archivo = fileConNombre(pendiente.file, nombreFinal);
        await onConfirmar?.(archivo, pendiente.tipo, comentario.trim() || null);
    }, [pendiente, nombre, comentario, enviando, onConfirmar]);

    if (!pendiente) return null;

    const { file, tipo } = pendiente;
    const IconoTipo = tipo === 'imagen' ? ImageIcon : tipo === 'video' ? Film : FileText;

    return createPortal(
        <div
            className="gelia-preparar-adjunto-backdrop"
            role="dialog"
            aria-modal="true"
            aria-labelledby="gelia-preparar-adjunto-titulo"
            onClick={(e) => {
                if (e.target === e.currentTarget && !enviando) onCerrar?.();
            }}
        >
            <div className="gelia-preparar-adjunto-modal theme-surface border theme-border">
                <header className="gelia-preparar-adjunto-header flex items-center justify-between gap-3 px-4 py-3 border-b theme-border shrink-0">
                    <div className="flex items-center gap-2 min-w-0">
                        <IconoTipo className="w-5 h-5 shrink-0 opacity-60" />
                        <h2 id="gelia-preparar-adjunto-titulo" className="text-sm font-black uppercase m-0 truncate">
                            Revisar antes de enviar
                        </h2>
                    </div>
                    <button
                        type="button"
                        onClick={onCerrar}
                        disabled={enviando}
                        className="p-2 rounded-full theme-element border theme-border theme-text-muted hover:theme-text-main shrink-0"
                        aria-label="Cancelar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </header>

                <div className="gelia-preparar-adjunto-body flex flex-col lg:flex-row min-h-0 flex-1">
                    <VistaPreviaLocal pendiente={pendiente} />

                    <div className="gelia-preparar-adjunto-form flex flex-col gap-3 p-4 lg:w-80 shrink-0 border-t lg:border-t-0 lg:border-l theme-border">
                        <div>
                            <label htmlFor="gelia-adjunto-nombre" className="theme-label text-[10px] font-black uppercase">
                                Nombre del archivo
                            </label>
                            <input
                                id="gelia-adjunto-nombre"
                                type="text"
                                value={nombre}
                                onChange={(e) => setNombre(e.target.value)}
                                disabled={enviando}
                                className="w-full mt-1 px-3 py-2 text-sm rounded-xl theme-element theme-text-main border theme-border outline-none focus:border-[var(--color-primario)]"
                            />
                            <p className="text-[10px] theme-text-muted m-0 mt-1">
                                {formatearTamanoArchivo(file.size)}
                            </p>
                        </div>

                        <div className="flex-1 min-h-0 flex flex-col">
                            <label htmlFor="gelia-adjunto-comentario" className="theme-label text-[10px] font-black uppercase">
                                Comentario (opcional)
                            </label>
                            <textarea
                                id="gelia-adjunto-comentario"
                                value={comentario}
                                onChange={(e) => setComentario(e.target.value)}
                                disabled={enviando}
                                rows={4}
                                placeholder="Ej. Cotización cliente marzo, revisar vigencia…"
                                className="w-full mt-1 flex-1 min-h-[5rem] resize-none rounded-xl px-3 py-2 text-sm theme-element theme-text-main border theme-border outline-none focus:border-[var(--color-primario)]"
                            />
                        </div>

                        <div className="flex gap-2 pt-1">
                            <button
                                type="button"
                                onClick={onCerrar}
                                disabled={enviando}
                                className="flex-1 py-2.5 rounded-xl text-xs font-bold theme-element border theme-border theme-text-main"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={handleEnviar}
                                disabled={enviando || !nombre.trim()}
                                className="flex-1 py-2.5 rounded-xl text-xs font-black uppercase flex items-center justify-center gap-2 bg-[var(--color-primario)] text-white disabled:opacity-40"
                            >
                                {enviando ? (
                                    <Loader2 className="w-4 h-4 animate-spin" />
                                ) : (
                                    <Send className="w-4 h-4" />
                                )}
                                Enviar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>,
        document.body
    );
}
