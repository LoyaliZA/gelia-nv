import { describe, expect, it } from 'vitest';
import {
    contarEstados,
    duracionVueltaSegundos,
    estadoPublicidad,
    formatearDuracionSeg,
    formatearTamanoBytes,
    textoEliminacion,
    textoProgramacion,
} from './estadoPublicidadPdv';

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

describe('resumen de playlist', () => {
    const ahora = new Date('2026-09-21T12:00:00');

    it('suma solo piezas activas y etiqueta programación', () => {
        const items = [
            { estado: 'activa', duracion_seg: 10, vigente_desde: null, vigente_hasta: null },
            { estado: 'programada', duracion_seg: 20, vigente_desde: '2026-10-01T08:00' },
            { estado: 'expirada', duracion_seg: 30, vigente_hasta: '2026-09-20T23:59' },
            { estado: 'deshabilitada', duracion_seg: 40 },
        ];
        expect(duracionVueltaSegundos(items, ahora)).toBe(10);
        expect(contarEstados(items)).toMatchObject({ todas: 4, activa: 1, programada: 1, expirada: 1, deshabilitada: 1 });
        expect(textoProgramacion(items[1], ahora)).toContain('Comienza el');
        expect(textoProgramacion(items[2], ahora)).toContain('Finalizó el');
        expect(textoProgramacion(items[0], ahora)).toContain('Desde ahora');
        expect(textoEliminacion({ eliminar_automaticamente: true, eliminar_programado_at: '2026-11-15T10:00' })).toContain('Se eliminará el');
    });
});

describe('formatos publicidad', () => {
    it('formatea duración y tamaño', () => {
        expect(formatearDuracionSeg(154)).toBe('2:34');
        expect(formatearTamanoBytes(385 * 1024 * 1024)).toBe('385.0 MB');
    });
});
