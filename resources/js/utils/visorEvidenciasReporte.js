import { payloadArchivoVoucher } from '@/Components/ModalVisorArchivo';

/** @param {Array<{ evidencia?: { url?: string } }>} exhibiciones */
export function exhibicionesConEvidencia(exhibiciones) {
    return (exhibiciones || []).filter((e) => Boolean(e.evidencia?.url));
}

/** @param {Array<{ evidencia?: { url?: string } }>} exhibiciones @param {number} indice */
export function payloadVisorEnIndice(exhibiciones, indice) {
    const lista = exhibicionesConEvidencia(exhibiciones);
    if (indice == null || indice < 0 || indice >= lista.length) return null;
    return payloadArchivoVoucher(lista[indice]);
}
