/** Campos del catálogo fiscal (enlace público + edición inline). */
export const CAMPOS_DATOS_FISCALES = [
    { clave: 'rfc', etiqueta: 'RFC' },
    { clave: 'codigo_postal', etiqueta: 'Código postal' },
    { clave: 'regimen_fiscal', etiqueta: 'Régimen fiscal' },
    { clave: 'correo_electronico', etiqueta: 'Correo electrónico' },
    { clave: 'uso_factura', etiqueta: 'Uso de CFDI' },
    { clave: 'nombre_razon_social', etiqueta: 'Nombre / razón social' },
    { clave: 'telefono', etiqueta: 'Teléfono' },
];

/** Grupo UI «Campos fiscales» (datos fiscales + razón social de la solicitud). */
export const CAMPOS_FISCALES_ERROR = [
    ...CAMPOS_DATOS_FISCALES,
    { clave: 'razon_social', etiqueta: 'Razón social' },
];

export const CAMPOS_ADJUNTOS_ERROR = [
    { clave: 'observaciones_vendedor', etiqueta: 'Observaciones' },
    { clave: 'archivo_fiscal', etiqueta: 'Excel fiscal' },
    { clave: 'vouchers', etiqueta: 'Comprobantes (voucher)' },
];

/** Grupos del modal de reporte / corrección (encargada). */
export const GRUPOS_CAMPOS_ERROR_FACTURA = [
    { id: 'fiscales', titulo: 'Campos fiscales', campos: CAMPOS_FISCALES_ERROR },
    { id: 'archivos', titulo: 'Archivos', campos: CAMPOS_ADJUNTOS_ERROR },
];

export const TODOS_CAMPOS_ERROR = [...CAMPOS_FISCALES_ERROR, ...CAMPOS_ADJUNTOS_ERROR];

const ETIQUETAS = Object.fromEntries(TODOS_CAMPOS_ERROR.map((c) => [c.clave, c.etiqueta]));

export function etiquetaCampoError(clave) {
    return ETIQUETAS[clave] || clave;
}

/** Campo del catálogo fiscal (enlace / formulario inline), sin `razon_social` de solicitud. */
export function esCampoFiscalError(clave) {
    return CAMPOS_DATOS_FISCALES.some((c) => c.clave === clave);
}
