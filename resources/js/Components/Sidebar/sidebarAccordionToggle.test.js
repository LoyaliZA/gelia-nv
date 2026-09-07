import { describe, it, expect } from 'vitest';
import {
    collectRootGroupIds,
    computeExclusiveGroupToggle,
    mergeRouteOpenGroups,
} from './sidebarAccordionToggle';

describe('collectRootGroupIds', () => {
    it('devuelve solo grupos raíz', () => {
        const tree = [
            { type: 'header', id: 'h1' },
            { type: 'group', id: 'operaciones' },
            { type: 'group', id: 'finanzas' },
        ];
        expect(collectRootGroupIds(tree)).toEqual(['operaciones', 'finanzas']);
    });
});

describe('computeExclusiveGroupToggle', () => {
    const roots = ['inicio', 'operaciones', 'finanzas'];

    it('cierra otras raíces al abrir una', () => {
        const prev = { inicio: true, operaciones: false, finanzas: true };
        const { next, scrollGroupId } = computeExclusiveGroupToggle(prev, 'operaciones', roots, 0);
        expect(next).toEqual({ inicio: false, operaciones: true, finanzas: false });
        expect(scrollGroupId).toBe('operaciones');
    });

    it('no afecta otras raíces al cerrar', () => {
        const prev = { inicio: false, operaciones: true };
        const { next, scrollGroupId } = computeExclusiveGroupToggle(prev, 'operaciones', roots, 0);
        expect(next.operaciones).toBe(false);
        expect(scrollGroupId).toBeNull();
    });

    it('subgrupos no son exclusivos entre raíces', () => {
        const prev = { logistica: false };
        const { next, scrollGroupId } = computeExclusiveGroupToggle(prev, 'logistica', roots, 1);
        expect(next.logistica).toBe(true);
        expect(scrollGroupId).toBe('logistica');
    });
});

describe('mergeRouteOpenGroups', () => {
    const roots = ['inicio', 'operaciones', 'finanzas'];

    it('deja solo una raíz si la ruta abre varias', () => {
        const prev = {};
        const routeIds = new Set(['inicio', 'operaciones', 'logistica']);
        const next = mergeRouteOpenGroups(prev, routeIds, roots);
        expect(next.inicio).toBe(false);
        expect(next.operaciones).toBe(true);
        expect(next.logistica).toBe(true);
    });
});
