/**
 * Self-check: default sidebar profesional (sin DOM / vitest).
 * Ejecutar: node resources/js/config/sidebarLayouts.selfcheck.mjs
 */
import assert from 'node:assert/strict';
import {
    DEFAULT_SIDEBAR_LAYOUT,
    DEFAULT_SIDEBAR_MOBILE_LAYOUT,
    SIDEBAR_PRO_DEFAULT_MIGRATION_KEY,
    ensureProfessionalSidebarDefaultOnce,
    resolveSidebarLayout,
    isProfessionalSidebarLayout,
} from './sidebarLayouts.js';

assert.equal(DEFAULT_SIDEBAR_LAYOUT, 'professional_left');
assert.equal(DEFAULT_SIDEBAR_MOBILE_LAYOUT, 'mobile_topbar');
assert.equal(isProfessionalSidebarLayout(DEFAULT_SIDEBAR_LAYOUT), true);
assert.equal(resolveSidebarLayout(null, null), 'professional_left');
assert.equal(resolveSidebarLayout('floating_left', null), 'floating_left');
assert.equal(resolveSidebarLayout(null, 'fixed'), 'fixed');
assert.equal(resolveSidebarLayout('???', 'floating_right'), 'professional_left');

const store = new Map();
const fakeStorage = {
    getItem: (k) => (store.has(k) ? store.get(k) : null),
    setItem: (k, v) => store.set(k, String(v)),
    removeItem: (k) => store.delete(k),
};

store.set('theme_layout', 'floating_left');
assert.equal(ensureProfessionalSidebarDefaultOnce(fakeStorage), true);
assert.equal(store.get('theme_layout'), 'professional_left');
assert.equal(store.get('theme_layout_mobile'), 'mobile_topbar');
assert.equal(store.get(SIDEBAR_PRO_DEFAULT_MIGRATION_KEY), '1');

store.set('theme_layout', 'fixed');
assert.equal(ensureProfessionalSidebarDefaultOnce(fakeStorage), false);
assert.equal(store.get('theme_layout'), 'fixed');

console.log('sidebarLayouts.selfcheck: ok');
