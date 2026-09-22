import { describe, expect, it } from 'vitest';
import { estadoPublicidad, formatearDuracionSeg, formatearTamanoBytes } from './estadoPublicidadPdv';

describe('estadoPublicidad', () => {
    const ahora = new Date('2026-09-21T12:00:00');

    it('deshabilitada gana sobre fechas', () => {
        expect(estadoPublicidad({
            activa: false,
            vigente_desde: '2026-09-01T00:00',
        }, ahora)).toBe('deshabilitada');
    });

    it('programada si aún no inicia', () => {
        expect(estadoPublicidad({
            activa: true,
            vigente_desde: '2026-10-01T08:00',
        }, ahora)).toBe('programada');
    });

    it('expirada si ya pasó el fin', () => {
        expect(estadoPublicidad({
            activa: true,
            vigente_hasta: '2026-09-20T23:59',
        }, ahora)).toBe('expirada');
    });

    it('activa si está en vigencia', () => {
        expect(estadoPublicidad({ activa: true }, ahora)).toBe('activa');
    });
});

describe('formatos publicidad', () => {
    it('formatea duración y tamaño', () => {
        expect(formatearDuracionSeg(154)).toBe('2:34');
        expect(formatearTamanoBytes(385 * 1024 * 1024)).toBe('385.0 MB');
    });
});
