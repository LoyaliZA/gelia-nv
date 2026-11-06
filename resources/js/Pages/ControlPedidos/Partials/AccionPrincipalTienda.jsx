import React from 'react';
import { createPortal } from 'react-dom';

/** En el modal comparte el pie; en otras vistas conserva la ubicación original. */
export default function AccionPrincipalTienda({ destino, children }) {
    if (destino === undefined) return children;
    return destino ? createPortal(children, destino) : null;
}
