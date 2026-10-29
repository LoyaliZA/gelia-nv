// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { FRASES_ACTUALIZACION, decidirAvisoVersion, fraseActualizacion } from './avisoVersion';

describe('decidirAvisoVersion', () => {
    it('no avisa si la versión cargada y la remota coinciden', () => {
        expect(decidirAvisoVersion({
            versionCargada: 'aaa',
            versionRemota: 'aaa',
        })).toBe('nada');
    });

    it('avisa cuando el shell remoto cambió', () => {
        expect(decidirAvisoVersion({
            versionCargada: 'aaa',
            versionRemota: 'bbb',
        })).toBe('avisar');
    });

    it('no tiene una rama de recarga', () => {
        const decisiones = ['nada', 'avisar'].map((esperada) => esperada);
        const obtenida = decidirAvisoVersion({
            versionCargada: 'aaa',
            versionRemota: 'bbb',
        });

        expect(decisiones).toContain(obtenida);
        expect(obtenida).not.toBe('recargar');
    });
});

describe('fraseActualizacion', () => {
    it('avanza y vuelve al inicio', () => {
        expect(fraseActualizacion(0)).toBe(FRASES_ACTUALIZACION[0]);
        expect(fraseActualizacion(1)).toBe(FRASES_ACTUALIZACION[1]);
        expect(fraseActualizacion(FRASES_ACTUALIZACION.length)).toBe(FRASES_ACTUALIZACION[0]);
        expect(fraseActualizacion(-1)).toBe(FRASES_ACTUALIZACION[FRASES_ACTUALIZACION.length - 1]);
    });
});
