export const MAPPING_HUB_DEFAULT = {
    folio: '',
    sku: '',
    descripcion: '',
    marca: '',
    categoria: '',
    codigo_barras: '',
    peso: '',
    activo: '',
    ubicacion: '',
    existencia: '',
    costo: '',
    costo_reposicion: '',
    precio_venta: '',
};

export const MAPPING_HUB_FICHA_REQUIRED = ['sku', 'folio', 'descripcion'];

export const MAPPING_HUB_LABELS = {
    folio: 'Folio *',
    sku: 'SKU *',
    descripcion: 'Descripción *',
    marca: 'Marca',
    categoria: 'Categoría',
    codigo_barras: 'Código de barras',
    peso: 'Peso',
    activo: 'Activo',
    ubicacion: 'Ubicación',
    existencia: 'Existencia',
    costo: 'Costo',
    costo_reposicion: 'Costo reposición',
    precio_venta: 'Precio de referencia',
};

export function autoMapearHub(headers, base = MAPPING_HUB_DEFAULT) {
    const mapping = { ...base };
    headers.forEach((h) => {
        const lower = String(h).toLowerCase();
        if (lower.includes('folio') && !mapping.folio) mapping.folio = h;
        if ((lower.includes('sku') || lower.includes('codigo')) && !lower.includes('barras') && !mapping.sku) mapping.sku = h;
        if ((lower.includes('desc') || lower.includes('nombre')) && !mapping.descripcion) mapping.descripcion = h;
        if (lower.includes('marca') && !mapping.marca) mapping.marca = h;
        if ((lower.includes('cat') || lower.includes('fam')) && !mapping.categoria) mapping.categoria = h;
        if (lower.includes('barras') && !mapping.codigo_barras) mapping.codigo_barras = h;
        if (lower.includes('peso') && !mapping.peso) mapping.peso = h;
        if (lower.includes('ubic') && !mapping.ubicacion) mapping.ubicacion = h;
        if ((lower.includes('exis') || lower.includes('stock')) && !mapping.existencia) mapping.existencia = h;
        if (lower.includes('repos') && !mapping.costo_reposicion) mapping.costo_reposicion = h;
        else if (lower.includes('costo') && !mapping.costo) mapping.costo = h;
        if ((lower.includes('precio') || lower.includes('venta')) && !mapping.precio_venta) mapping.precio_venta = h;
        if (lower.includes('activ') && !mapping.activo) mapping.activo = h;
    });
    return mapping;
}
