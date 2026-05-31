const MAX_PREVIEW_BYTES = 15 * 1024 * 1024;

const EXTENSION_A_TIPO = {
    pdf: 'pdf',
    xlsx: 'excel',
    xls: 'excel',
    xlsm: 'excel',
    docx: 'word',
    doc: 'word',
};

const EXCEL_MIMES = new Set([
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
    'application/vnd.ms-excel.sheet.macroenabled.12',
    'application/vnd.oasis.opendocument.spreadsheet',
]);

const WORD_MIMES = new Set([
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/msword',
    'application/vnd.oasis.opendocument.text',
]);

const PDF_MIMES = new Set([
    'application/pdf',
]);

export function extensionAdjunto(adjunto) {
    const nombre = (adjunto?.nombre_original || '').toLowerCase();
    const match = nombre.match(/\.([a-z0-9]+)$/i);
    return match ? match[1] : null;
}

/** @returns {'pdf'|'excel'|'word'|null} */
export function tipoPrevisualizacionAdjunto(adjunto) {
    if (!adjunto) return null;

    const ext = extensionAdjunto(adjunto);
    if (ext && EXTENSION_A_TIPO[ext]) {
        return EXTENSION_A_TIPO[ext];
    }

    const mime = (adjunto.mime || '').toLowerCase();

    if (PDF_MIMES.has(mime)) return 'pdf';

    if (
        EXCEL_MIMES.has(mime)
        || mime.includes('spreadsheet')
        || mime.includes('excel')
    ) {
        return 'excel';
    }

    if (
        WORD_MIMES.has(mime)
        || mime.includes('wordprocessing')
        || mime.includes('msword')
    ) {
        return 'word';
    }

    return null;
}

export function puedePrevisualizarAdjunto(adjunto) {
    return tipoPrevisualizacionAdjunto(adjunto) !== null;
}

export function adjuntoExcedeLimitePreview(adjunto) {
    return (adjunto?.tamano || 0) > MAX_PREVIEW_BYTES;
}

export function urlAdjuntoMensajeria(adjuntoId, { inline = false } = {}) {
    const base = route('mensajeria.adjuntos.show', adjuntoId);
    if (!inline) return base;
    const separador = base.includes('?') ? '&' : '?';
    return `${base}${separador}inline=1`;
}

/** Evita navegación del navegador (descarga) y abre el callback de vista previa. */
export function manejarClickPrevisualizar(event, onAbrir) {
    event.preventDefault();
    event.stopPropagation();
    onAbrir?.();
}
