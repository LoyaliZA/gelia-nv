/**
 * Self-check: secuencia collapse/expand del sidebar pro (sin DOM).
 * Ejecutar: node resources/js/Components/Sidebar/sidebarAnim.selfcheck.mjs
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { resolveStructuralCollapsed, ANIM_FALLBACK_MS, SETTLE_MS } from './useSidebarState.js';

assert.equal(resolveStructuralCollapsed('expanded', null), false);
assert.equal(resolveStructuralCollapsed('expanded', 'expanding'), false);
assert.equal(resolveStructuralCollapsed('collapsed', 'collapsing'), false);
assert.equal(resolveStructuralCollapsed('collapsed', 'settling'), false);
assert.equal(resolveStructuralCollapsed('collapsed', null), true);
assert.equal(resolveStructuralCollapsed('collapsed', 'expanding'), true);

assert.ok(ANIM_FALLBACK_MS >= 380, 'fallback debe cubrir --gelia-pro-duration');
assert.ok(SETTLE_MS >= 180, 'settle debe cubrir --gelia-pro-settle-duration');

const css = readFileSync(
    resolve(dirname(fileURLToPath(import.meta.url)), '../../../css/gelia/features/sidebar-professional.css'),
    'utf8',
);
assert.match(css, /width: var\(--gelia-sidebar-expanded-width\);/);
assert.match(css, /width: var\(--gelia-sidebar-collapsed-width\);/);
assert.equal(css.includes('--gelia-pro-width'), false);
assert.ok(css.includes('--gelia-pro-settle-duration'));
assert.equal(css.includes("[data-anim='collapsing'] .gelia-pro-sidebar__track"), false);
assert.ok(css.includes("[data-anim='settling']"));
assert.match(css, /\[data-anim='settling'\].*gelia-pro-sidebar__track/s);
assert.ok(css.includes("[data-anim='collapsing'] .gelia-pro-sidebar__collapse--open")
    || css.includes("[data-anim='collapsing'], [data-anim='settling']) .gelia-pro-sidebar__collapse--open"));
assert.match(css, /\[data-anim='settling'\] \.gelia-pro-sidebar__row-label/);
assert.equal(css.includes('padding: 0 !important'), false);

// Brand/utilities: altura explícita e interpolable, y apilado en settling (no en el frame final).
assert.ok(css.includes('height: var(--gelia-pro-brand-height)'));
assert.ok(css.includes('height: var(--gelia-pro-brand-rail-height)'));
assert.ok(css.includes('height: var(--gelia-pro-utilities-height)'));
assert.ok(css.includes('height: var(--gelia-pro-utilities-rail-height)'));
assert.equal(css.includes('min-height: auto'), false);
for (const part of ['__brand', '__utilities']) {
    assert.match(
        css,
        new RegExp(`\\[data-anim='settling'\\], \\[data-collapsed='true'\\]:not\\(\\[data-anim\\]\\)\\) \\.gelia-pro-sidebar${part} \\{`),
        `${part} debe apilarse en settling, no solo asentado`,
    );
}
assert.equal(
    /\[data-anim='collapsing'\][^{]*\.gelia-pro-sidebar__collapse[^{]*\{[^}]*display:\s*none/.test(css),
    false,
);

console.log('sidebarAnim.selfcheck: ok');
