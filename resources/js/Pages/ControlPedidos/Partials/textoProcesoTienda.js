/** Traduce referencias de proceso conservadas en movimientos anteriores. */
export function textoProcesoTienda(texto) {
    if (typeof texto !== 'string') return texto;
    const nombres = { '01': 'Pago al recoger', '02': 'Recolección posterior en tienda', '02.1': 'Tienda MD', '03': 'Recolección en City Center', '05': 'Incorporación a envío', '09': 'Crédito' };
    return texto.replace(/\bT-(\d{2}(?:\.\d+)?)\b/gi, (_, codigo) => nombres[codigo] || 'Proceso de tienda');
}
