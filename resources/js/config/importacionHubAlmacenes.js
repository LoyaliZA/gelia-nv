export const MAPPING_HUB_DEFAULT = {
    folio: '',
    sku: '',
    descripcion: '',
    marca: '',
    categoria: '',
    codigo_barras: '',
    peso: '',
    margen: '',
    largo: '',
    ancho: '',
    altura: '',
    activo: '',
    ubicacion: '',
    existencia: '',
    costo: '',
    costo_reposicion: '',
};

export const MAPPING_HUB_FICHA_REQUIRED = ['sku', 'folio', 'descripcion'];

export const MAPPING_HUB_LABELS = {
    folio: 'Folio',
    sku: 'SKU',
    descripcion: 'Descripción',
    marca: 'Marca',
    categoria: 'Categoría',
    codigo_barras: 'Código de barras',
    peso: 'Peso',
    margen: 'Margen (%)',
    largo: 'Largo (cm)',
    ancho: 'Ancho (cm)',
    altura: 'Altura (cm)',
    activo: 'Activo',
    ubicacion: 'Ubicación',
    existencia: 'Existencia',
    costo: 'Costo promedio',
    costo_reposicion: 'Costo reposición',
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
        if (lower.includes('margen') && !mapping.margen) mapping.margen = h;
        if (lower.includes('largo') && !mapping.largo) mapping.largo = h;
        if (lower.includes('ancho') && !mapping.ancho) mapping.ancho = h;
        if ((lower.includes('altura') || lower.includes('alto')) && !mapping.altura) mapping.altura = h;
        if (lower.includes('ubic') && !mapping.ubicacion) mapping.ubicacion = h;
        if ((lower.includes('exis') || lower.includes('stock')) && !mapping.existencia) mapping.existencia = h;
        if (lower.includes('repos') && !mapping.costo_reposicion) mapping.costo_reposicion = h;
        else if (lower.includes('costo') && !mapping.costo) mapping.costo = h;
        if (lower.includes('activ') && !mapping.activo) mapping.activo = h;
    });
    return mapping;
}
