import { describe, expect, it } from 'vitest';
import {
    normalizarVolumenPublicidadPct,
    volumenAudioVideoPublicidad,
} from './volumenPublicidadPdv';

describe('volumenPublicidadPdv', () => {
    it('normaliza porcentaje y fracción legada', () => {
        expect(normalizarVolumenPublicidadPct(40)).toBe(40);
        expect(normalizarVolumenPublicidadPct(0.4)).toBe(40);
        expect(normalizarVolumenPublicidadPct(150)).toBe(100);
    });

    it('combina sala y pieza con curva perceptual', () => {
        const bajo = volumenAudioVideoPublicidad(20, 100);
        const medio = volumenAudioVideoPublicidad(50, 100);
        const alto = volumenAudioVideoPublicidad(100, 100);
        expect(bajo).toBeLessThan(medio);
        expect(medio).toBeLessThan(alto);
        expect(volumenAudioVideoPublicidad(50, 50)).toBeLessThan(volumenAudioVideoPublicidad(50, 100));
    });
});
