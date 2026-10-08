import {
    tieneErrorGuiaReportado,
    tieneRetrasoEmpaqueActivo,
    tieneRetrasoRecoleccionActivo,
} from '../../Partials/pedidosBmaStyles';

/** Etiqueta del CTA principal en listado (sin abrir modal). */
export function etiquetaCtaListadoDelegado(pedido) {
    if (pedido?.es_resguardo) return 'Ver';
    const fase = pedido?.estatus?.fase_ciclo;
    if ((fase === 'PENDIENTE_DE_GUIA' || fase === 'EN_CEDIS') && !pedido?.numero_rastreo) {
        return 'Capturar';
    }
    return 'Ver';
}

export function pedidoConSenialOperativa(pedido) {
    return Boolean(
        pedido?.guia_retraso
        || tieneErrorGuiaReportado(pedido)
        || tieneRetrasoEmpaqueActivo(pedido)
        || tieneRetrasoRecoleccionActivo(pedido)
    );
}
