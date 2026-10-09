import { describe, expect, it } from 'vitest';
import { remisionVigenteDe } from './remisionVigente';

describe('remisión vigente en auditoría', () => {
    it('muestra la sustitución aunque la anterior aparezca primero en el historial', () => {
        const actual = { id: 2, tipo: 'remision', activo: true };
        expect(remisionVigenteDe({ documentos: [{ id: 1, tipo: 'remision', activo: false }, actual] })).toBe(actual);
    });
    it.each([false, 0, '0'])('no permite usar una remisión eliminada (%s)', (activo) => {
        expect(remisionVigenteDe({ documentos: [{ tipo: 'remision', activo }] })).toBeUndefined();
    });
    it('descarta documentos sustituidos y otros tipos de archivo', () => {
        expect(remisionVigenteDe({ documentos: [{ tipo: 'pdf_pedido', activo: true }, { tipo: 'remision', sustituido_at: '2026-10-09' }] })).toBeUndefined();
    });
    it('admite documentos anteriores sin metadatos de vigencia y pedidos sin archivos', () => {
        const legado = { tipo: 'remision' };
        expect(remisionVigenteDe({ documentos: [legado] })).toBe(legado);
        expect(remisionVigenteDe(null)).toBeUndefined();
    });
});
