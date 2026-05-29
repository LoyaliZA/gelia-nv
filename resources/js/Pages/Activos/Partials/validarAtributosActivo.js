export function validarAtributosActivo(fields = [], values = {}) {
    const errores = {};

    fields.forEach((field) => {
        const key = field.key;
        if (!key) return;

        const valor = values[key];
        const label = field.label || key;
        const vacio = valor === undefined || valor === null || valor === '';

        if (field.required && vacio) {
            errores[`atributos.${key}`] = `El campo ${label} es obligatorio.`;
            return;
        }

        if (vacio) return;

        if (field.type === 'number') {
            const num = Number(valor);
            if (Number.isNaN(num)) {
                errores[`atributos.${key}`] = `${label} debe ser un número.`;
                return;
            }
            if (field.min !== undefined && num < Number(field.min)) {
                errores[`atributos.${key}`] = `${label} debe ser al menos ${field.min}.`;
            }
            if (field.max !== undefined && num > Number(field.max)) {
                errores[`atributos.${key}`] = `${label} no puede ser mayor que ${field.max}.`;
            }
            return;
        }

        if (['text', 'textarea', 'catalog_marca', 'catalog_modelo'].includes(field.type)) {
            const texto = String(valor);
            if (field.min_length !== undefined && texto.length < Number(field.min_length)) {
                errores[`atributos.${key}`] = `${label} debe tener al menos ${field.min_length} caracteres.`;
            }
            if (field.max_length !== undefined && texto.length > Number(field.max_length)) {
                errores[`atributos.${key}`] = `${label} no puede exceder ${field.max_length} caracteres.`;
            }
            if (field.pattern) {
                try {
                    const regex = new RegExp(field.pattern);
                    if (!regex.test(texto)) {
                        errores[`atributos.${key}`] = field.pattern_message || `${label} no cumple el formato requerido.`;
                    }
                } catch {
                    // patrón inválido en esquema — omitir validación cliente
                }
            }
        }
    });

    return errores;
}

export function tieneErroresAtributos(errores) {
    return Object.keys(errores).length > 0;
}
