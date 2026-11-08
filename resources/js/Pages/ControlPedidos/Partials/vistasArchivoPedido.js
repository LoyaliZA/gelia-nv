/** Solo las rutas propias generan derivados; blobs, URLs firmadas y otros módulos se conservan. */
export function vistaArchivoPedido(url, vista = 'previa') {
    if (!url || !['previa', 'miniatura'].includes(vista)) return url;
    const partes = String(url).split('#');
    const [ruta, query = ''] = partes[0].split('?');
    if (!/\/control-pedidos\/.*(?:\/documentos\/\d+|\/solicitud\/\d+|\/evidencia\/\d+\/descargar|\/sesion-evidencia\/fotos\/\d+)$/.test(ruta)
        && !/\/(?:cedis|tienda)-evidencia\/[^/]+\/fotos\/\d+$/.test(ruta)) return url;
    const params = new URLSearchParams(query);
    params.set('vista', vista);
    return `${ruta}?${params}${partes.length > 1 ? `#${partes.slice(1).join('#')}` : ''}`;
}

/** Nombre breve de presentación; el File y el original guardado conservan su nombre. */
export function nombreVisibleArchivoPedido(nombre, indice = 0) {
    const valor = String(nombre || '');
    const base = valor.replace(/\.[^.]+$/, '');
    if (valor && valor.length <= 64 && !/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}|[a-z0-9_-]{28,}/i.test(base)) return valor;
    const extension = valor.match(/\.([a-z0-9]{1,8})$/i)?.[0] || '';
    return `evidencia-${indice + 1}${extension.toLowerCase()}`;
}
