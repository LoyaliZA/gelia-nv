export const esRegistroMunicipal = (pedido) => pedido?.solicitud_preparacion?.modalidad_entrega === 'municipal';

// Registro vigente: terminal activa Local / Regional; el costo diferido es independiente.
export const terminalesMunicipales = (catalogos) => (catalogos.paqueterias ?? catalogos.paqueterias_municipio ?? [])
    .filter(item => item.activo !== false && item.categoria === 'local_regional');

export const nombreTerminalMunicipal = pedido => pedido?.paqueteria?.nombre || 'Por seleccionar';

export function areaResponsableMunicipal(pedido) {
    const consulta = pedido?.solicitud_preparacion?.consulta_municipal || {};
    const almacen = pedido?.almacen?.nombre;
    if (consulta.area === 'tienda') return almacen || 'Tienda';
    return almacen || 'CEDIS';
}

