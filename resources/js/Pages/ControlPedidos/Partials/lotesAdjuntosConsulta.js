export const CUPO_ADJUNTOS_CONSULTA = 50 * 1024 * 1024;
export const LOTE_ADJUNTO_BYTES = 8 * 1024 * 1024;

export function bytesConsulta(solicitud = {}) {
    const ciclo = solicitud?.revision_disponibilidad?.ciclo_id || null;
    const sumar = (entrada) => {
        if (!entrada || typeof entrada !== 'object') return 0;
        if (ciclo && entrada.ciclo_id && entrada.ciclo_id !== ciclo) return 0;
        const docs = [
            ...(entrada.documentos || []),
            ...(entrada.documentos_piezas || []),
            ...(entrada.documentos_peticion || []),
        ];
        if (docs.length) return docs.reduce((suma, doc) => suma + Number(doc?.tamano_bytes || 0), 0);
        return Number(entrada.tamano_bytes || 0);
    };
    return [...(solicitud.anexo_sin_remision || []), ...(solicitud.retiro_piezas || [])].reduce((suma, entrada) => suma + sumar(entrada), 0);
}

export function partirLotes(archivos = [], limite = LOTE_ADJUNTO_BYTES) {
    const lotes = [];
    let actual = [];
    let peso = 0;
    archivos.forEach((archivo) => {
        const tamano = Number(archivo?.size || 0);
        if (actual.length && peso + tamano > limite) {
            lotes.push(actual);
            actual = [];
            peso = 0;
        }
        actual.push(archivo);
        peso += tamano;
    });
    if (actual.length) lotes.push(actual);
    return lotes;
}

export async function enviarEnLotes({ url, archivos, armar, headers, post }) {
    const lotes = partirLotes(archivos);
    let ultima = null;
    for (let indice = 0; indice < lotes.length; indice += 1) {
        const form = armar(lotes[indice], String(indice));
        form.append('lote', String(indice));
        ultima = await post(url, form, { headers });
    }
    return ultima;
}
