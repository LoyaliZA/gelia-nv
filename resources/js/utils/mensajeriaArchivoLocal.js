const TIPOS_IMAGEN = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const TIPOS_VIDEO = ['video/mp4', 'video/webm', 'video/quicktime'];

const EXT_ARCHIVO = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'xlsm', 'zip', 'sql', 'csv', 'txt'];

export const MAX_PREVIEW_LOCAL_BYTES = 15 * 1024 * 1024;

export function extensionDe(nombre) {
    const match = (nombre || '').toLowerCase().match(/\.([a-z0-9]+)$/i);
    return match ? match[1] : '';
}

export function detectarTipoArchivo(file) {
    if (!file) return 'archivo';
    if (TIPOS_IMAGEN.includes(file.type)) return 'imagen';
    if (TIPOS_VIDEO.includes(file.type)) return 'video';
    if (file.type.startsWith('audio/')) return 'audio';

    return 'archivo';
}

/** @returns {'pdf'|'excel'|'word'|null} */
export function tipoPreviewArchivoLocal(file) {
    if (!file) return null;

    const ext = extensionDe(file.name);
    if (ext === 'pdf' || file.type === 'application/pdf') return 'pdf';
    if (['xlsx', 'xls', 'xlsm', 'csv'].includes(ext) || file.type.includes('spreadsheet') || file.type.includes('excel')) {
        return 'excel';
    }
    if (['docx', 'doc'].includes(ext) || file.type.includes('word')) return 'word';

    return null;
}

export function puedePrevisualizarArchivoLocal(file) {
    const tipo = detectarTipoArchivo(file);
    if (tipo === 'imagen' || tipo === 'video') return true;
    if ((file?.size || 0) > MAX_PREVIEW_LOCAL_BYTES) return false;

    return tipoPreviewArchivoLocal(file) !== null || tipo === 'archivo';
}

export function aplicarNombreArchivo(nombreOriginal, nombreEditado) {
    const original = (nombreOriginal || '').trim();
    const editado = (nombreEditado || '').trim();
    if (!editado) return original;

    const extOriginal = extensionDe(original);
    if (editado.includes('.')) return editado;

    return extOriginal ? `${editado}.${extOriginal}` : editado;
}

export function fileConNombre(file, nombreFinal) {
    return new File([file], nombreFinal, {
        type: file.type,
        lastModified: file.lastModified,
    });
}

export function formatearTamanoArchivo(bytes) {
    if (!bytes) return '0 B';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function esArrastreArchivo(dataTransfer) {
    if (!dataTransfer?.types) return false;
    return Array.from(dataTransfer.types).includes('Files');
}

export function primerArchivoDeDrop(dataTransfer) {
    const archivos = Array.from(dataTransfer?.files || []).filter((f) => f.size > 0);
    return archivos[0] || null;
}
