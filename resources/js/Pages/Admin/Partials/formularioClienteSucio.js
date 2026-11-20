/** Compara el formulario con su apertura. Los tipos del select y del monto no cuentan como cambio. */
export function formularioClienteSucio(actual, inicial) {
    return Object.keys(inicial).some((clave) => String(actual?.[clave] ?? '') !== String(inicial[clave] ?? ''));
}
