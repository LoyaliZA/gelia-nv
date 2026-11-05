/** @type {Record<string, string[]>} */
export const PRESET_OPERACIONES = {
    productos: ['ficha_producto'],
    costos: ['costos_precios'],
    existencias: ['cantidades_referencia', 'asignacion_almacen'],
    inventario: ['cantidades_referencia', 'asignacion_almacen'],
    completa: ['cantidades_referencia', 'asignacion_almacen'],
    asignacion: ['asignacion_almacen'],
};

export function operacionesDesdePreset(preset) {
    if (!preset) return [];
    return PRESET_OPERACIONES[preset] ?? [];
}
