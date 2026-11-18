const instante = valor => Date.parse(valor || '') || 0;

/** La bitácora existente ordena cambios incluso dentro del mismo segundo. UUID identifica, no ordena. */
export function lecturaConsultaVigente(actual, lectura) {
    if (!lectura || (actual?.id && lectura.id && Number(actual.id) !== Number(lectura.id))) return false;
    if (actual?.version_consulta != null && lectura.version_consulta != null
        && Number(actual.version_consulta) !== Number(lectura.version_consulta)) {
        return Number(lectura.version_consulta) > Number(actual.version_consulta);
    }
    const previa = actual?.solicitud_preparacion?.revision_disponibilidad || {};
    const siguiente = lectura.solicitud_preparacion?.revision_disponibilidad || {};
    const respuestaA = instante(previa.respondida_at);
    const respuestaB = instante(siguiente.respondida_at);
    if (respuestaB !== respuestaA) return respuestaB > respuestaA;
    if (instante(lectura.updated_at) < instante(actual?.updated_at)) return false;
    if (respuestaA && previa.respuesta_id && siguiente.respuesta_id && previa.respuesta_id !== siguiente.respuesta_id) return false;
    return true;
}

/** Solo lectura operativa. La captura común, financiera y logística local se conserva. */
export function fusionarLecturaConsulta(actual, lectura) {
    if (!lecturaConsultaVigente(actual, lectura)) return actual;
    const solicitud = { ...actual?.solicitud_preparacion };
    for (const clave of ['consulta_municipal', 'revision_disponibilidad', 'anexo_sin_remision', 'retiro_piezas', 'acciones_disponibilidad', 'anexo_integrado', 'solicitudes_ajuste_ids', 'solicitudes_ajuste_lotes']) {
        if (Object.hasOwn(lectura.solicitud_preparacion || {}, clave)) solicitud[clave] = lectura.solicitud_preparacion[clave];
    }
    const fusion = { ...actual, solicitud_preparacion: solicitud };
    for (const clave of ['version_consulta', 'revisiones_producto', 'documentos', 'historial', 'consulta_cerrada', 'consulta_cerrada_at', 'consulta_cerrada_por_id', 'consulta_actualizacion_pendiente', 'puede_cerrar_consulta', 'motivo_bloqueo_cierre', 'updated_at', 'estatus', 'etiqueta_consulta', 'pesaje_respondido_por', 'pesajeRespondidoPor']) {
        if (Object.hasOwn(lectura, clave)) fusion[clave] = lectura[clave];
    }
    return fusion;
}
