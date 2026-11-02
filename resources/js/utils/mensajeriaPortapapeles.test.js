import { describe, expect, it } from 'vitest';
import { archivosImagenDesdePortapapeles } from './mensajeriaPortapapeles';

describe('archivosImagenDesdePortapapeles', () => {
    it('toma solo imágenes del portapapeles', () => {
        const imagen = { type: 'image/png', getAsFile: () => ({ name: 'captura.png' }) };
        const texto = { type: 'text/plain', getAsFile: () => ({ name: 'nota.txt' }) };

        expect(archivosImagenDesdePortapapeles({ items: [texto, imagen] })).toEqual([{ name: 'captura.png' }]);
    });

    it('devuelve vacío si no hay archivos', () => {
        expect(archivosImagenDesdePortapapeles(null)).toEqual([]);
    });

    it('acepta la imagen si el navegador solo llena files', () => {
        const captura = { type: 'image/png', name: 'captura.png' };
        expect(archivosImagenDesdePortapapeles({ items: [], files: [captura] })).toEqual([captura]);
    });
});
