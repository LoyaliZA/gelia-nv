import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import {
    SIDEBAR_LAYOUT_IDS,
    SIDEBAR_LAYOUT_OPTIONS,
    isProfessionalSidebarLayout,
    DEFAULT_SIDEBAR_LAYOUT,
    DEFAULT_SIDEBAR_MOBILE_LAYOUT,
    SIDEBAR_PRO_DEFAULT_MIGRATION_KEY,
    ensureProfessionalSidebarDefaultOnce,
    resolveSidebarLayout,
} from './sidebarLayouts';

describe('sidebarLayouts', () => {
    it('incluye professional_left junto a los estilos legacy', () => {
        expect(SIDEBAR_LAYOUT_IDS).toEqual([
            'professional_left',
            'fixed',
            'floating_left',
            'floating_right',
        ]);
        expect(SIDEBAR_LAYOUT_OPTIONS).toHaveLength(4);
    });

    it('detecta solo el layout profesional', () => {
        expect(isProfessionalSidebarLayout('professional_left')).toBe(true);
        expect(isProfessionalSidebarLayout('fixed')).toBe(false);
        expect(isProfessionalSidebarLayout('floating_left')).toBe(false);
        expect(isProfessionalSidebarLayout(undefined)).toBe(false);
    });

    it('usa professional_left como default de sistema', () => {
        expect(DEFAULT_SIDEBAR_LAYOUT).toBe('professional_left');
        expect(DEFAULT_SIDEBAR_MOBILE_LAYOUT).toBe('mobile_topbar');
        expect(resolveSidebarLayout(null, null)).toBe('professional_left');
        expect(resolveSidebarLayout(null, 'floating_left')).toBe('floating_left');
        expect(resolveSidebarLayout('fixed', 'professional_left')).toBe('fixed');
        expect(resolveSidebarLayout('nope', null)).toBe('professional_left');
    });
});

describe('ensureProfessionalSidebarDefaultOnce', () => {
    const store = new Map();

    beforeEach(() => {
        store.clear();
        vi.stubGlobal('localStorage', {
            getItem: (k) => (store.has(k) ? store.get(k) : null),
            setItem: (k, v) => store.set(k, String(v)),
            removeItem: (k) => store.delete(k),
        });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('fuerza layout pro una sola vez y no pisa después', () => {
        store.set('theme_layout', 'floating_left');
        expect(ensureProfessionalSidebarDefaultOnce(localStorage)).toBe(true);
        expect(store.get('theme_layout')).toBe('professional_left');
        expect(store.get('theme_layout_mobile')).toBe('mobile_topbar');
        expect(store.get(SIDEBAR_PRO_DEFAULT_MIGRATION_KEY)).toBe('1');

        store.set('theme_layout', 'floating_right');
        expect(ensureProfessionalSidebarDefaultOnce(localStorage)).toBe(false);
        expect(store.get('theme_layout')).toBe('floating_right');
    });
});
