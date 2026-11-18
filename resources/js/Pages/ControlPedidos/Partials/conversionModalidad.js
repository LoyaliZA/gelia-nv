import axios from 'axios';

export const LATIDO_CONVERSION_MS = 18000;

export const TEXTO_HERENCIA = {
    sin_consulta: 'No hay consulta previa. Tras confirmar, el envío municipal quedará listo para iniciar la consulta.',
    parcial: 'La consulta se reutiliza en parte. Quedan excepciones o una revisión complementaria; no se declara cerrada.',
    respondida_abierta: 'La consulta respondida se reutiliza completa. El cierre posterior es el cierre municipal habitual.',
};

export const DESTINOS_P0 = [
    { codigo: 'municipal', nombre: 'Envío municipal', habilitado: true, motivo_bloqueo: null },
    { codigo: 'tienda', nombre: 'Tienda', habilitado: false, motivo_bloqueo: 'Este destino se habilitará más adelante.' },
    { codigo: 'nacional', nombre: 'Envío nacional', habilitado: false, motivo_bloqueo: 'Este destino se habilitará más adelante.' },
];

export function flagsConversionDe(data) {
    if (data?.conversion_modalidad) return data.conversion_modalidad;
    if (data?.origen && Array.isArray(data?.destinos)) return data;
    return null;
}

export function mensajeErrorConversion(error, reserva) {
    if (error?.response?.status === 419) return 'La sesión venció. Recargue GELIA antes de continuar.';
    const datos = error?.response?.data;
    const lista = datos?.errors ? Object.values(datos.errors).flat() : [];
    return lista[0] || datos?.message || reserva;
}

function tokenCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || window.axios?.defaults?.headers?.common?.['X-CSRF-TOKEN']
        || '';
}

export function clienteHttpConversion() {
    return (typeof window !== 'undefined' && window.axios) ? window.axios : axios;
}

export function soltarReservaConversion(pedidoId, instanciaId) {
    if (!pedidoId || !instanciaId) return Promise.resolve();
    return clienteHttpConversion().delete(`/control-pedidos/${pedidoId}/conversion-modalidad/reserva`, {
        instancia_id: instanciaId,
        data: { instancia_id: instanciaId },
        headers: { Accept: 'application/json' },
    }).catch(() => {});
}

export function soltarReservaKeepalive(pedidoId, instanciaId) {
    if (!pedidoId || !instanciaId || typeof fetch !== 'function') return;
    fetch(`/control-pedidos/${pedidoId}/conversion-modalidad/reserva`, {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': tokenCsrf(),
        },
        body: JSON.stringify({ instancia_id: instanciaId }),
        keepalive: true,
        credentials: 'same-origin',
    }).catch(() => {});
}

export function importeConversion(valor) {
    if (valor == null || valor === '') return null;
    const numero = Number(valor);
    return Number.isFinite(numero) ? numero : null;
}

export function fusionarPedidoConversion(pedido, resultado) {
    if (!resultado) return pedido;
    const consulta = resultado.herencia?.consulta
        || resultado.conversion_modalidad?.herencia?.consulta;
    return {
        ...pedido,
        ...resultado,
        id: resultado.pedido_id || pedido?.id,
        folio: resultado.folio || pedido?.folio,
        modalidad_entrega: resultado.modalidad_entrega || 'municipal',
        solicitud_preparacion: {
            ...(pedido?.solicitud_preparacion || {}),
            modalidad_entrega: 'municipal',
            ...(consulta ? { consulta_municipal: consulta } : {}),
        },
    };
}

export function armarFormularioConversion({
    destino = 'municipal',
    instanciaId,
    motivo,
    errorCaptura,
    revisionEsperada,
    conversionVersion,
    claveIdempotencia,
    captura = {},
    evidencias = [],
}) {
    const form = new FormData();
    form.append('destino', destino);
    form.append('instancia_id', instanciaId || '');
    form.append('motivo', (motivo || '').trim());
    form.append('error_captura', errorCaptura ? '1' : '0');
    form.append('revision_esperada', String(revisionEsperada ?? 0));
    form.append('conversion_version', String(conversionVersion ?? 0));
    form.append('clave_idempotencia', claveIdempotencia || '');
    Object.entries(captura || {}).forEach(([clave, valor]) => {
        if (valor == null || valor === '') return;
        form.append(clave, String(valor));
    });
    evidencias.forEach((archivo) => form.append('evidencias[]', archivo));
    return form;
}
