/**
 * Extrae archivos de imagen del clipboard (Ctrl+V / Cmd+V).
 * @param {DataTransfer|null|undefined} clipboardData
 * @returns {File[]}
 */
export function archivosImagenDesdeClipboard(clipboardData) {
    const items = clipboardData?.items;
    if (!items?.length) return [];
    const out = [];
    for (let i = 0; i < items.length; i += 1) {
        const item = items[i];
        if (!item?.type || item.type.indexOf('image') === -1) continue;
        const file = typeof item.getAsFile === 'function' ? item.getAsFile() : null;
        if (file) out.push(file);
    }
    return out;
}

/**
 * Documento local para MiniaturaDocumento / ModalVistaPreviaDocumento.
 * @param {File} file
 * @param {string} url blob o http
 */
export function documentoDesdeArchivoLocal(file, url) {
    return {
        url,
        nombre_original: file?.name || 'archivo',
        mime_type: file?.type || '',
        tipo: 'local',
    };
}
