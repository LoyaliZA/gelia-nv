export function emitirToastGelia(mensaje, tipo = 'info') {
    if (!mensaje) {
        return;
    }

    const texto = String(mensaje).trim();
    if (!texto) {
        return;
    }

    window.dispatchEvent(new CustomEvent('gelia-toast', {
        detail: { mensaje: texto, tipo },
    }));
}

/** Compatible con callbacks onError (null = sin acción). */
export function reportarMensajeOperacion(mensaje, tipo = 'error') {
    if (!mensaje) {
        return;
    }

    emitirToastGelia(mensaje, tipo);
}

export function reportarExitoOperacion(mensaje) {
    emitirToastGelia(mensaje, 'success');
}

export function reportarInfoOperacion(mensaje) {
    emitirToastGelia(mensaje, 'info');
}
