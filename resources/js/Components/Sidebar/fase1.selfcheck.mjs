/**
 * Self-check Fase 1 sidebar profesional (sin vitest).
 * Ejecutar: node resources/js/Components/Sidebar/fase1.selfcheck.mjs
 */
import assert from 'node:assert/strict';
import { SIDEBAR_LAYOUT_IDS, SIDEBAR_LAYOUT_OPTIONS, isProfessionalSidebarLayout } from '../../config/sidebarLayouts.js';
import { clearEphemeralThemeKeys } from '../../utils/clearEphemeralThemeKeys.js';

function resolveSidebarVariant(layout) {
    return isProfessionalSidebarLayout(layout) ? 'professional' : 'legacy';
}

function resolveShellSidebarLayout({ layout, isMobile, mobileLayout }) {
    if (isMobile) {
        if (isProfessionalSidebarLayout(layout) || mobileLayout === 'mobile_topbar') {
            return 'mobile-topbar';
        }
        return 'mobile-bottom';
    }
    if (isProfessionalSidebarLayout(layout)) return 'professional-left';
    if (layout === 'fixed') return 'fixed';
    if (layout === 'floating_right') return 'float-right';
    return 'float-left';
}

function collectOpenGroupIdsForUrl(nodes, url, ancestors = []) {
    const open = new Set(['inicio']);
    const walk = (items, chain) => {
        for (const node of items) {
            if (!node || node.type === 'header') continue;
            if (node.type === 'link') {
                if (node.active?.(url)) chain.forEach((id) => open.add(id));
                continue;
            }
            if (node.type === 'group') {
                const nextChain = [...chain, node.id];
                if (node.children?.length) walk(node.children, nextChain);
            }
        }
    };
    walk(nodes, ancestors);
    return open;
}

// --- layouts ---
assert.deepEqual(SIDEBAR_LAYOUT_IDS, [
    'professional_left',
    'fixed',
    'floating_left',
    'floating_right',
]);
assert.equal(SIDEBAR_LAYOUT_OPTIONS.length, 4);
assert.equal(isProfessionalSidebarLayout('professional_left'), true);
assert.equal(isProfessionalSidebarLayout('fixed'), false);

// --- AppLayout switch ---
assert.equal(resolveSidebarVariant('professional_left'), 'professional');
assert.equal(resolveSidebarVariant('floating_left'), 'legacy');
assert.equal(resolveShellSidebarLayout({ layout: 'professional_left', isMobile: false }), 'professional-left');
assert.equal(resolveShellSidebarLayout({
    layout: 'professional_left',
    isMobile: true,
    mobileLayout: 'mobile_bottom',
}), 'mobile-topbar');

// --- tokens ---
assert.ok(17.5 > 4.5);

// --- active ancestors ---
const tree = [{
    type: 'group',
    id: 'operaciones',
    children: [{
        type: 'group',
        id: 'pedidos',
        children: [{
            type: 'link',
            id: 'auditar',
            active: (url) => url.startsWith('/control-pedidos/auditar'),
        }],
    }],
}];
const open = collectOpenGroupIdsForUrl(tree, '/control-pedidos/auditar');
assert.ok(open.has('operaciones'));
assert.ok(open.has('pedidos'));

// --- logout storage ---
const store = new Map([
    ['theme', 'dark'],
    ['theme_layout', 'professional_left'],
    ['gelia:theme-preview', '{}'],
    ['gelia:user:1:theme-preview', '{}'],
]);
globalThis.localStorage = {
    removeItem: (k) => store.delete(k),
    clear: () => { throw new Error('clear() no debe llamarse'); },
};
const origKeys = Object.keys;
Object.keys = (obj) => (obj === globalThis.localStorage ? [...store.keys()] : origKeys(obj));
clearEphemeralThemeKeys();
Object.keys = origKeys;
assert.equal(store.has('theme'), true);
assert.equal(store.has('theme_layout'), true);
assert.equal(store.has('gelia:theme-preview'), false);
assert.equal(store.has('gelia:user:1:theme-preview'), false);

console.log('fase1.selfcheck: ok');
