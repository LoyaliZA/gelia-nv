import { describe, it, expect } from 'vitest';
import { GRID_COLS, layoutHasCollisions, layoutReferenceWidth, reflowLayoutForWidth, stampLayoutWidth, REFLOW_REFERENCE_WIDTH_PX } from './dashboardLayoutUtils';

const panel = (i, x, y, w, h = 6) => ({ i, x, y, w, h, minW: 4, minH: 4 });
const tres = [panel('a', 0, 0, 8), panel('b', 8, 0, 8), panel('c', 16, 0, 8)];
const cuatroEnDosFilas = [panel('a', 0, 0, 12), panel('b', 12, 0, 12), panel('c', 0, 6, 12), panel('d', 12, 6, 12)];

describe('reflowLayoutForWidth', () => {
    it('no toca el layout en ancho compacto ni cerca de la referencia', () => {
        expect(reflowLayoutForWidth(cuatroEnDosFilas, REFLOW_REFERENCE_WIDTH_PX)).toBe(cuatroEnDosFilas);
        expect(reflowLayoutForWidth(cuatroEnDosFilas, REFLOW_REFERENCE_WIDTH_PX * 1.1)).toBe(cuatroEnDosFilas);
        expect(reflowLayoutForWidth(cuatroEnDosFilas, 0)).toBe(cuatroEnDosFilas);
    });

    it('en un contenedor ancho caben más paneles por fila (no se estiran)', () => {
        const ancho = reflowLayoutForWidth(cuatroEnDosFilas, REFLOW_REFERENCE_WIDTH_PX * 2);
        // Doble de ancho: cada panel conserva su tamaño en px → los 4 caben en una sola fila.
        expect(new Set(ancho.map((p) => p.y)).size).toBe(1);
        expect(ancho.map((p) => p.w)).toEqual([6, 6, 6, 6]);
        expect(ancho.map((p) => p.x)).toEqual([0, 6, 12, 18]);
    });

    it('rellena la fila cuando sobran columnas, sin dejar huecos a la derecha', () => {
        const ancho = reflowLayoutForWidth(tres, REFLOW_REFERENCE_WIDTH_PX * 1.5);
        const usados = ancho.reduce((suma, p) => suma + p.w, 0);
        expect(usados).toBe(GRID_COLS);
        expect(Math.max(...ancho.map((p) => p.x + p.w))).toBe(GRID_COLS);
    });

    it('conserva el orden de lectura, nunca crea solapes y no muta el original', () => {
        const copia = JSON.parse(JSON.stringify(cuatroEnDosFilas));
        const ancho = reflowLayoutForWidth(cuatroEnDosFilas, REFLOW_REFERENCE_WIDTH_PX * 1.4);
        expect(layoutHasCollisions(ancho)).toBe(false);
        expect(cuatroEnDosFilas).toEqual(copia);
        const orden = [...ancho].sort((p, q) => p.y - q.y || p.x - q.x).map((p) => p.i);
        expect(orden).toEqual(['a', 'b', 'c', 'd']);
    });

    it('un panel de ancho completo sigue ocupando toda la fila', () => {
        const ancho = reflowLayoutForWidth([panel('banner', 0, 0, 24), panel('x', 0, 6, 12)], REFLOW_REFERENCE_WIDTH_PX * 2);
        expect(ancho.find((p) => p.i === 'banner').w).toBe(GRID_COLS);
    });

    it('un layout sellado con su ancho se ve idéntico en ese ancho y se reacomoda en otros', () => {
        const editado = stampLayoutWidth(cuatroEnDosFilas, 1900);
        expect(layoutReferenceWidth(editado)).toBe(1900);
        expect(reflowLayoutForWidth(editado, 1900)).toBe(editado);
        expect(reflowLayoutForWidth(editado, 1900 * 1.05)).toBe(editado);
        const estrecho = reflowLayoutForWidth(editado, 950);
        expect(new Set(estrecho.map((p) => p.y)).size).toBeGreaterThan(2);
        expect(layoutHasCollisions(estrecho)).toBe(false);
    });

    it('sin sello se asume el ancho compacto de referencia', () => {
        expect(layoutReferenceWidth(cuatroEnDosFilas)).toBe(REFLOW_REFERENCE_WIDTH_PX);
    });

    it('en un contenedor estrecho los paneles se envuelven en más filas', () => {
        const estrecho = reflowLayoutForWidth(cuatroEnDosFilas, REFLOW_REFERENCE_WIDTH_PX / 2);
        expect(estrecho.map((p) => p.w)).toEqual([24, 24, 24, 24]);
        expect(new Set(estrecho.map((p) => p.y)).size).toBe(4);
    });
});
