export function leerSnapshotSeguro(snapshot) {
    if (typeof snapshot !== 'string') return snapshot;
    try { return JSON.parse(snapshot); } catch { return null; }
}

/** Legacy records use only metadata recorded in that event, never a later proposal. */
export function cotizacionHistorial(snapshot, registro, { listas = [], tiposCliente = [], procesos = [] } = {}) {
    if (snapshot?.cotizado) return snapshot.cotizado;
    if (!snapshot?.antes) return null;
    const cotizado = { ...snapshot.antes };
    cotizado.monto_venta = snapshot.antes.monto_venta != null && snapshot.monto_cotizado != null
        ? Number(snapshot.antes.monto_venta) + Number(snapshot.monto_cotizado) : undefined;
    for (const [idKey, nombreKey, destino, catalogo] of [
        ['lista_descuento_id', 'lista_descuento_nombre', 'lista_nombre', listas],
        ['tipo_cliente_id', 'tipo_cliente_nombre', 'tipo_cliente_nombre', tiposCliente],
    ]) {
        if (!Object.hasOwn(snapshot, idKey)) cotizado[destino] = undefined;
        else if (snapshot[idKey]) cotizado[destino] = snapshot[nombreKey] || catalogo.find(item => String(item.id) === String(snapshot[idKey]))?.nombre || `Registro #${snapshot[idKey]}`;
    }
    const proceso = snapshot.proceso_nombre || procesos.find(item => String(item.id) === String(snapshot.proceso_id))?.nombre;
    if (!proceso) cotizado.tag_vendedor_nombre = undefined;
    else if (/ASIGNAR TAG|ASIGNAR CLIENTE/i.test(proceso)) cotizado.tag_vendedor_nombre = registro.usuario?.name;
    return cotizado;
}

/** Response timestamps describe the last update when an explicit response date is unavailable. */
export function eventosHistorial(solicitud, orden = 'reciente') {
    const eventos = (solicitud.auditorias || []).map(registro => ({ key: `audit-${registro.id}`, tipo: 'auditoria', fecha: registro.created_at, registro }));
    for (const consulta of solicitud.consultas || []) {
        eventos.push({ key: `consulta-${consulta.id}`, tipo: 'consulta', fecha: consulta.created_at, registro: consulta });
        if (consulta.estado === 'respondida') eventos.push({ key: `respuesta-${consulta.id}`, tipo: 'respuesta', fecha: consulta.respondido_at || consulta.updated_at || consulta.created_at, fechaEsActualizacion: !consulta.respondido_at, registro: consulta });
    }
    const fechaValida = fecha => Date.parse(fecha) || 0;
    return eventos.sort((a, b) => (fechaValida(a.fecha) - fechaValida(b.fecha)) * (orden === 'reciente' ? -1 : 1));
}
