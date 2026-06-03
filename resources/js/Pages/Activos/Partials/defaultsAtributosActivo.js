/** Valores por defecto cuando un campo dinámico queda vacío al guardar. */
export const DEFAULTS_ATRIBUTOS = {
    serial: 'S/N',
    numero_serie: 'S/N',
    no_serie: 'S/N',
    imei: 'S/N',
    mac: 'N/A',
    ip: 'N/A',
    material: 'N/A',
    color: 'N/A',
    ubicacion_fisica: 'N/A',
    proveedor: 'N/A',
    clave_licencia: 'N/A',
};

const CLAVES_SIN_DEFAULT = new Set(['marca', 'modelo', 'marca_id', 'modelo_id']);

function valorVacio(valor) {
    return valor === undefined || valor === null || String(valor).trim() === '';
}

export function aplicarDefaultsAtributos(fields = [], atributos = {}) {
    const result = { ...atributos };

    fields.forEach((field) => {
        const key = field.key;
        if (!key || CLAVES_SIN_DEFAULT.has(key)) return;
        if (!valorVacio(result[key])) return;

        const type = field.type || 'text';
        if (['boolean', 'date', 'number'].includes(type)) return;
        if (type === 'select' && (field.options || []).length > 0) return;
        if (['catalog_marca', 'catalog_modelo'].includes(type)) return;

        if (DEFAULTS_ATRIBUTOS[key]) {
            result[key] = DEFAULTS_ATRIBUTOS[key];
        } else if (['text', 'textarea'].includes(type)) {
            result[key] = 'N/A';
        }
    });

    return result;
}
