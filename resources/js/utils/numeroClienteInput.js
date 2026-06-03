/**
 * Deja solo dígitos en el número de cliente (Wizerp).
 */
export function soloDigitosNumeroCliente(valor) {
    return String(valor ?? '').replace(/\D/g, '');
}
