export function faltantesRespuesta({ lineas, esRecoge, ubicacion, extra, requisitos, documentos, evidencias }) {
    const faltantes = [];
    if (!lineas.length) faltantes.push({ campo: 'tienda-escanear', texto: 'Agregue los productos preparados o reporte el problema.' });
    lineas.forEach((l, i) => {
        if (l.cantidad === '' || !Number.isInteger(Number(l.cantidad)) || Number(l.cantidad) < 0) {
            faltantes.push({ campo: `tienda-cantidad-${i}`, texto: `Indique una cantidad entera para ${l.descripcion}.` });
        }
        if (!esRecoge && requisitos.evidencia_por_producto && Number(l.cantidad) > 0 && !l.evidencias.length
            && !documentos.some((d) => l.id && Number(d.pedido_bma_tarea_producto_id) === Number(l.id) && d.tipo_evidencia === 'evidencia_producto')) {
            faltantes.push({ campo: `tienda-fotos-${i}`, texto: `Adjunte evidencia de ${l.descripcion}.` });
        }
    });
    if (esRecoge) {
        if (!ubicacion.trim()) faltantes.push({ campo: 'tienda-ubicacion', texto: 'Indique dónde queda el apartado.' });
        return faltantes;
    }
    for (const [requisito, campo, texto] of [
        ['peso_real_obligatorio', 'peso_real_kg', 'Capture el peso real en kg.'],
        ['peso_volumetrico_obligatorio', 'peso_volumetrico_kg', 'Capture el peso volumétrico en kg.'],
    ]) {
        if (requisitos[requisito] && !(Number(extra[campo]) > 0)) faltantes.push({ campo: `tienda-${campo}`, texto });
    }
    if (requisitos.caja_obligatoria && !extra.catalogo_tipo_caja_id) faltantes.push({ campo: 'tienda-catalogo_tipo_caja_id', texto: 'Seleccione el tipo de caja.' });
    if (requisitos.observaciones_fisicas_obligatorias && !extra.observaciones_fisicas.trim()) faltantes.push({ campo: 'tienda-observaciones_fisicas', texto: 'Describa el estado físico general.' });
    if (requisitos.evidencia_general_obligatoria && !evidencias.length && !documentos.some((d) => d.tipo_evidencia === 'evidencia_general' && !d.inmutable)) {
        faltantes.push({ campo: 'tienda-evidencia-general', texto: 'Adjunte al menos una evidencia del conjunto preparado.' });
    }
    if (requisitos.requiere_identificacion && !documentos.some((d) => d.tipo_evidencia === 'identificacion')) faltantes.push({ campo: 'tienda-identificacion', texto: 'Adjunte la identificación del destinatario.' });
    return faltantes;
}
