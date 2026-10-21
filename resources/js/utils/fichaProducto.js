const CLAVES_RETORNO = ['desde', 'sucursal_id', 'almacen_id', 'q', 'asignacion', 'costo', 'activo', 'page', 'sort', 'dir', 'sin_imagen'];

function tiene(auth, permiso) {
    const permisos = auth?.user?.permissions || [];
    const roles = auth?.user?.roles || [];

    return roles.includes('Super Admin') || permisos.includes(permiso);
}

export function puedeVerFichaProducto(auth) {
    return tiene(auth, 'gestion_interna.productos.ver')
        || tiene(auth, 'catalogos.gestionar')
        || tiene(auth, 'almacenes.productos.ver');
}

export function puedeGestionarImagenes(auth) {
    return tiene(auth, 'gestion_interna.productos.gestionar')
        || tiene(auth, 'catalogos.gestionar');
}

export function paramsFichaProducto(productoId, retorno = {}) {
    const params = { producto: productoId };
    CLAVES_RETORNO.forEach((clave) => {
        const valor = retorno[clave];
        if (valor !== undefined && valor !== null && valor !== '') {
            params[clave] = valor;
        }
    });

    return params;
}

export function hrefFichaProducto(productoId, retorno = {}) {
    return route('almacenes.productos.ficha', paramsFichaProducto(productoId, retorno));
}
