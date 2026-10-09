// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { decidirRecargaDeploy, vistaEnReposo } from './deployWatch';

describe('decidirRecargaDeploy', () => {
    it('no recarga si la versión de la superficie no cambió', () => {
        expect(decidirRecargaDeploy({
            versionCargada: 'aaa',
            versionRemota: 'aaa',
            enReposo: true,
        })).toBe('nada');
    });

    it('recarga la sala cuando su versión cambió', () => {
        expect(decidirRecargaDeploy({
            versionCargada: 'aaa',
            versionRemota: 'bbb',
            enReposo: true,
        })).toBe('recargar');
    });

    it('no repite una recarga ya intentada para la misma versión', () => {
        expect(decidirRecargaDeploy({
            versionCargada: 'aaa',
            versionRemota: 'bbb',
            intentoPrevio: 'bbb',
            enReposo: true,
        })).toBe('nada');
    });

    it('deja la recarga en espera cuando la vista no está en reposo', () => {
        expect(decidirRecargaDeploy({
            versionCargada: 'aaa',
            versionRemota: 'bbb',
            enReposo: false,
        })).toBe('esperar');
    });
});

describe('vistaEnReposo', () => {
    it('no está en reposo mientras hay una mutación en curso', () => {
        expect(vistaEnReposo(1)).toBe(false);
    });
});
