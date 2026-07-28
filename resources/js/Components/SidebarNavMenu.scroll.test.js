import { describe, it, expect } from 'vitest';
import { sidebarExpandScrollDelta } from './SidebarNavMenu';

describe('sidebarExpandScrollDelta', () => {
    const scroller = { top: 100, bottom: 400, height: 300 };

    it('no mueve si el bloque ya cabe y está a la vista', () => {
        const block = { top: 140, bottom: 220, height: 80 };
        expect(sidebarExpandScrollDelta(scroller, block)).toBe(0);
    });

    it('baja el scroll si el bloque se sale por abajo', () => {
        const block = { top: 280, bottom: 420, height: 140 };
        expect(sidebarExpandScrollDelta(scroller, block)).toBe(28);
    });

    it('ancla al tope si el bloque es más alto que el viewport útil', () => {
        const block = { top: 150, bottom: 520, height: 370 };
        expect(sidebarExpandScrollDelta(scroller, block)).toBe(42);
    });
});
