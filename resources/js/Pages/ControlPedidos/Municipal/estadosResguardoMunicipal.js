/** Textos de interfaz del resguardo de envío. Sin códigos de ficha ni identificadores internos. */
export const ESTADOS_RESGUARDO = {
    intencion_registrada: { texto: 'Intención de resguardo registrada', tono: 'info' },
    pago_pendiente_registro: { texto: 'Pago pendiente de registrar', tono: 'aviso' },
    pago_registrado_ventas: { texto: 'Pago registrado por Ventas', tono: 'info' },
    pendiente_auditoria: { texto: 'Pendiente de auditoría', tono: 'aviso' },
    pago_auditado: { texto: 'Pago auditado', tono: 'exito' },
    pago_rechazado: { texto: 'Pago rechazado', tono: 'peligro' },
    resguardo_vigente: { texto: 'Resguardo vigente', tono: 'info' },
    acumulacion_abierta: { texto: 'Acumulación abierta', tono: 'aviso' },
    acumulacion_cerrada: { texto: 'Acumulación cerrada', tono: 'info' },
    continuacion_solicitada: { texto: 'Continuación solicitada', tono: 'info' },
    pendiente_integracion: { texto: 'Pendiente de integración', tono: 'aviso' },
    liberacion_autorizada: { texto: 'Liberación autorizada', tono: 'exito' },
    listo_continuar: { texto: 'Listo para continuar preparación', tono: 'exito' },
};

export const MOTIVOS_RESGUARDO = {
    fecha_posterior: 'Entrega en fecha posterior',
    acumulacion: 'Acumular mercancía',
    espera_complemento: 'Esperar un complemento',
};

export const MECANISMOS_REMISION = {
    custodia_local: 'Integrar en sucursal',
    custodia_cedis: 'Trasladar a CEDIS',
    resguardo_envio: 'Ya está en CEDIS',
};

const ESTADOS_FISICOS = {
    pendiente: 'Pendiente de integrar en la sucursal',
    integrado: 'Integrada',
    pendiente_traslado: 'Pendiente de salir hacia CEDIS',
    en_traslado: 'En traslado a CEDIS',
    recibido_cedis: 'Recibida en CEDIS',
    pendiente_integracion: 'Recibida; falta integrarla en CEDIS',
    anexado: 'Pendiente de confirmar en CEDIS',
};

export const estadoFisicoTexto = (estado) => ESTADOS_FISICOS[estado] || 'Pendiente de integración';

/** Acción pendiente y área responsable de una remisión del conjunto. */
export function accionPendienteRemision(vinculo) {
    if (vinculo.integrado) return { texto: 'Sin pendientes', area: null };
    if (vinculo.mecanismo === 'custodia_local') return { texto: 'Confirmar integración en la sucursal', area: 'Sucursal' };
    if (vinculo.estado === 'pendiente_traslado') return { texto: 'Registrar la salida hacia CEDIS', area: 'Sucursal' };
    if (vinculo.estado === 'en_traslado') return { texto: 'Confirmar la recepción en CEDIS', area: 'CEDIS' };
    return { texto: 'Confirmar la integración en CEDIS', area: 'CEDIS' };
}

export function dineroOCero(valor) {
    const numero = Number(valor);
    return Number.isFinite(numero) ? numero : 0;
}

export function textoTransporte(cobertura) {
    return {
        cubierto: 'Transporte cubierto',
        diferido: 'Transporte diferido; el costo se determina después',
        pendiente: 'Transporte pendiente',
        conocido_exigible: 'Transporte pendiente de pago',
        no_aplica: 'Sin transporte aplicable',
    }[cobertura] || 'Transporte pendiente';
}

export function textoCoberturaMercancia(cobertura) {
    return {
        auditada: 'Pago de la mercancía auditado',
        declarada: 'Pago de la mercancía registrado por Ventas; falta auditarlo',
        rechazada: 'Hay un pago rechazado sin corregir',
        ausente: 'La mercancía aún no está pagada',
    }[cobertura] || 'La mercancía aún no está pagada';
}
