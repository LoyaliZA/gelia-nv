/** Conserva solo dígitos, hasta `max`. */
export function soloDigitos(valor, max = Infinity) {
    return String(valor ?? '').replace(/\D/g, '').slice(0, max);
}

/** Teléfono mexicano de 10 dígitos como «55 1234 5678». Acepta lada +52 y separadores pegados. */
export function formatearTelefono(valor) {
    let digitos = soloDigitos(valor);
    if (digitos.length > 10 && digitos.startsWith('52')) digitos = digitos.slice(2);
    digitos = digitos.slice(0, 10);

    return [digitos.slice(0, 2), digitos.slice(2, 6), digitos.slice(6, 10)].filter(Boolean).join(' ');
}
