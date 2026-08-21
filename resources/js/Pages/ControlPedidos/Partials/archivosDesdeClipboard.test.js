import { describe, expect, it } from 'vitest';
import { archivosImagenDesdeClipboard, documentoDesdeArchivoLocal } from './archivosDesdeClipboard';

function fakeClipboard(entries) {
    return {
        items: entries.map(({ type, file }) => ({
            type,
            getAsFile: () => file,
        })),
    };
}

describe('archivosImagenDesdeClipboard', () => {
    it('devuelve vacío sin clipboard', () => {
        expect(archivosImagenDesdeClipboard(null)).toEqual([]);
        expect(archivosImagenDesdeClipboard({})).toEqual([]);
    });

    it('solo toma items image/*', () => {
        const png = new File(['x'], 'a.png', { type: 'image/png' });
        const pdf = new File(['y'], 'b.pdf', { type: 'application/pdf' });
        const out = archivosImagenDesdeClipboard(fakeClipboard([
            { type: 'text/plain', file: null },
            { type: 'image/png', file: png },
            { type: 'application/pdf', file: pdf },
        ]));
        expect(out).toHaveLength(1);
        expect(out[0]).toBe(png);
    });
});

describe('documentoDesdeArchivoLocal', () => {
    it('arma shape para miniatura', () => {
        const f = new File(['x'], 'voucher.jpg', { type: 'image/jpeg' });
        expect(documentoDesdeArchivoLocal(f, 'blob:1')).toEqual({
            url: 'blob:1',
            nombre_original: 'voucher.jpg',
            mime_type: 'image/jpeg',
            tipo: 'local',
        });
    });
});
