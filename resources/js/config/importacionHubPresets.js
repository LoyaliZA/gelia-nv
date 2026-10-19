/** @type {Record<string, string[]>} */
export const PRESET_OPERACIONES = {
    productos: ['ficha_producto', 'asignacion_almacen'],
    costos: ['costos_precios'],
    existencias: ['cantidades_referencia'],
    inventario: ['ficha_producto', 'asignacion_almacen', 'costos_precios', 'cantidades_referencia'],
    completa: ['ficha_producto', 'asignacion_almacen', 'costos_precios', 'cantidades_referencia'],
    asignacion: ['ficha_producto', 'asignacion_almacen'],
};

export function operacionesDesdePreset(preset) {
    if (!preset) return [];
    return PRESET_OPERACIONES[preset] ?? [];
}
