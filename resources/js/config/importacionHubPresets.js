/** @type {Record<string, string[]>} */
export const PRESET_OPERACIONES = {
    productos: ['ficha_producto'],
    costos: ['costos_precios'],
    inventario: ['ficha_producto', 'cantidades_referencia', 'costos_precios'],
    existencias: ['cantidades_referencia'],
    asignacion: ['asignacion_almacen'],
};

export function operacionesDesdePreset(preset) {
    if (!preset) return [];
    return PRESET_OPERACIONES[preset] ?? [];
}
