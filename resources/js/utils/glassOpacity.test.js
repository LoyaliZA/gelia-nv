import { describe, it, expect } from 'vitest';
import {
    clampGlassOpacity,
    formatGlassOpacityLabel,
    applyGlassOpacityToRoot,
    GLASS_OPACITY_DEFAULT,
    GLASS_OPACITY_MIN,
    GLASS_OPACITY_MAX,
} from './glassOpacity';

describe('glassOpacity', () => {
    it('acota y redondea al paso de 5 %', () => {
        expect(clampGlassOpacity(0.75)).toBe(0.75);
        expect(clampGlassOpacity(0.73)).toBe(0.75);
        expect(clampGlassOpacity(0.12)).toBe(GLASS_OPACITY_MIN);
        expect(clampGlassOpacity(1)).toBe(GLASS_OPACITY_MAX);
        expect(clampGlassOpacity('no')).toBe(GLASS_OPACITY_DEFAULT);
        expect(clampGlassOpacity(undefined)).toBe(GLASS_OPACITY_DEFAULT);
    });

    it('etiqueta en porcentaje', () => {
        expect(formatGlassOpacityLabel(0.75)).toBe('75%');
        expect(formatGlassOpacityLabel(0.5)).toBe('50%');
    });

    it('publica la variable en el root', () => {
        const values = {};
        const root = { style: { setProperty: (k, v) => { values[k] = v; } } };
        applyGlassOpacityToRoot(0.6, root);
        expect(values['--theme-glass-opacity']).toBe('0.6');
    });
});
