// @vitest-environment node
import { describe, expect, it } from 'vitest';
import {
    estiloCapaRotada,
    estiloEscenarioKiosko,
    girarRotacionPublicidad,
    normalizarRotacionPublicidad,
    urlKioskoConDisposicion,
} from './rotacionMedioPublicidad';

describe('rotacionMedioPublicidad', () => {
    it('normaliza rotaciones válidas y descarta el resto', () => {
        expect(normalizarRotacionPublicidad(90)).toBe(90);
        expect(normalizarRotacionPublicidad('270')).toBe(270);
        expect(normalizarRotacionPublicidad(45)).toBe(0);
        expect(normalizarRotacionPublicidad(null)).toBe(0);
    });

    it('gira en pasos de 90 grados', () => {
        expect(girarRotacionPublicidad(0, 'derecha')).toBe(90);
        expect(girarRotacionPublicidad(270, 'derecha')).toBe(0);
        expect(girarRotacionPublicidad(0, 'izquierda')).toBe(270);
        expect(girarRotacionPublicidad(90, 'izquierda')).toBe(0);
    });

    it('intercambia ancho y alto en 90 y 270', () => {
        const noventa = estiloCapaRotada(90, 1600, 900);
        expect(noventa.width).toBe(900);
        expect(noventa.height).toBe(1600);
        expect(noventa.transform).toContain('rotate(90deg)');

        const doscientos = estiloCapaRotada(270, 800, 400);
        expect(doscientos.width).toBe(400);
        expect(doscientos.height).toBe(800);
        expect(doscientos.transform).toContain('rotate(270deg)');

        const sinGiro = estiloCapaRotada(0, 1600, 900);
        expect(sinGiro.inset).toBe(0);
        expect(sinGiro.transform).toBeUndefined();
    });

    it('fuerza el escenario vertical solo si el viewport es apaisado', () => {
        const forzado = estiloEscenarioKiosko('vertical', 1920, 1080);
        expect(forzado.transform).toBe('rotate(90deg)');
        expect(forzado.width).toBe('100dvh');

        const yaVertical = estiloEscenarioKiosko('vertical', 1080, 1920);
        expect(yaVertical.transform).toBeUndefined();
        expect(yaVertical.width).toBe('100%');

        const automatico = estiloEscenarioKiosko('auto', 1920, 1080);
        expect(automatico.transform).toBeUndefined();
    });

    it('añade disposicion vertical a la URL del kiosko', () => {
        expect(urlKioskoConDisposicion('https://gelia.test/sala-publicidad/t/abc', 'auto'))
            .toBe('https://gelia.test/sala-publicidad/t/abc');
        expect(urlKioskoConDisposicion('https://gelia.test/sala-publicidad/t/abc', 'vertical'))
            .toBe('https://gelia.test/sala-publicidad/t/abc?disposicion=vertical');
    });
});
