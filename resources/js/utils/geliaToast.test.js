import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import {
    emitirToastGelia,
    reportarExitoOperacion,
    reportarInfoOperacion,
    reportarMensajeOperacion,
} from './geliaToast';

describe('geliaToast', () => {
    beforeEach(() => {
        vi.stubGlobal('window', {
            dispatchEvent: vi.fn(),
        });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('emite toast con mensaje y tipo', () => {
        emitirToastGelia('Hola', 'success');

        expect(window.dispatchEvent).toHaveBeenCalledWith(expect.objectContaining({
            type: 'gelia-toast',
            detail: { mensaje: 'Hola', tipo: 'success' },
        }));
    });

    it('ignora mensajes vacíos', () => {
        emitirToastGelia('');
        reportarMensajeOperacion(null);

        expect(window.dispatchEvent).not.toHaveBeenCalled();
    });

    it('reporta tipos de operación', () => {
        reportarMensajeOperacion('Error de validación');
        reportarExitoOperacion('Guardado correctamente');
        reportarInfoOperacion('Actualizando…');

        expect(window.dispatchEvent).toHaveBeenCalledTimes(3);
    });
});
