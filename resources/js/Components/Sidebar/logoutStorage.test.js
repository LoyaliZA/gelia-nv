import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { clearEphemeralThemeKeys } from '../../utils/clearEphemeralThemeKeys';

describe('clearEphemeralThemeKeys', () => {
    const store = new Map();

    beforeEach(() => {
        store.clear();
        vi.stubGlobal('localStorage', {
            getItem: (k) => (store.has(k) ? store.get(k) : null),
            setItem: (k, v) => store.set(k, String(v)),
            removeItem: (k) => store.delete(k),
            clear: () => store.clear(),
            key: (i) => [...store.keys()][i] ?? null,
            get length() {
                return store.size;
            },
        });
        const originalKeys = Object.keys;
        vi.spyOn(Object, 'keys').mockImplementation((obj) => {
            if (obj === globalThis.localStorage) return [...store.keys()];
            return originalKeys(obj);
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('no ejecuta localStorage.clear y conserva preferencias persistentes', () => {
        store.set('theme', 'dark');
        store.set('theme_layout', 'professional_left');
        store.set('theme_color', 'azul');
        store.set('gelia:theme-preview', '{"modo":"light"}');
        store.set('gelia:user:45:theme-preview', '{"modo":"dark"}');

        const clearSpy = vi.spyOn(globalThis.localStorage, 'clear');
        clearEphemeralThemeKeys();

        expect(clearSpy).not.toHaveBeenCalled();
        expect(store.has('theme')).toBe(true);
        expect(store.has('theme_layout')).toBe(true);
        expect(store.has('theme_color')).toBe(true);
        expect(store.has('gelia:theme-preview')).toBe(false);
        expect(store.has('gelia:user:45:theme-preview')).toBe(false);
    });
});
