import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, it, expect } from 'vitest';

/** Tokens del sidebar profesional (Fase 1) — fuente: tokens.css. */
const PROFESSIONAL_TOKENS = {
    expanded: '17.5rem',
    collapsed: '4.5rem',
    row: '2.75rem',
    mobile: '20rem',
};

const css = readFileSync(
    resolve(dirname(fileURLToPath(import.meta.url)), '../../../css/gelia/features/sidebar-professional.css'),
    'utf8',
);

describe('professional sidebar tokens', () => {
    it('define geometría coherente expandido > contraído', () => {
        const toRem = (v) => Number.parseFloat(v);
        expect(toRem(PROFESSIONAL_TOKENS.expanded)).toBeGreaterThan(toRem(PROFESSIONAL_TOKENS.collapsed));
        expect(PROFESSIONAL_TOKENS.expanded).toBe('17.5rem');
        expect(PROFESSIONAL_TOKENS.collapsed).toBe('4.5rem');
        expect(PROFESSIONAL_TOKENS.row).toBe('2.75rem');
        expect(PROFESSIONAL_TOKENS.mobile).toBe('20rem');
    });
});

describe('professional sidebar collapse animation', () => {
    it('anima width del aside con tokens concretos, no con custom property intermedia', () => {
        expect(css).toContain('width: var(--gelia-sidebar-expanded-width);');
        expect(css).toContain("width: var(--gelia-sidebar-collapsed-width);");
        expect(css).not.toContain('--gelia-pro-width');
    });

    it('no morpha rail/track/filas durante collapsing (clip del aside)', () => {
        expect(css).not.toContain("[data-anim='collapsing'] .gelia-pro-sidebar__track");
        expect(css).not.toContain("[data-anim='collapsing'] .gelia-pro-sidebar__brand {");
        expect(css).not.toContain("[data-anim='collapsing'] .gelia-pro-sidebar__nav");
        expect(css).not.toContain("[data-anim='collapsing'] .gelia-pro-sidebar__utilities");
        expect(css).toContain("[data-anim='settling']");
        expect(css).toContain("[data-collapsed='true']:not([data-anim])");
        expect(css).toContain('--gelia-pro-settle-duration');
    });

    it('morph de filas en settling, no en collapsing, y sin padding !important', () => {
        expect(css).toContain("[data-anim='settling'] .gelia-pro-sidebar__row-label");
        expect(css).not.toContain('padding: 0 !important');
        expect(css).not.toMatch(/\[data-anim='settling'\][^{]*justify-content:\s*center/);
    });

    it('apila brand/utilities en settling con altura interpolable', () => {
        for (const part of ['__brand', '__utilities']) {
            expect(css).toMatch(
                new RegExp(`\\[data-anim='settling'\\], \\[data-collapsed='true'\\]:not\\(\\[data-anim\\]\\)\\) \\.gelia-pro-sidebar${part} \\{`)
            );
        }
        expect(css).toContain('height: var(--gelia-pro-brand-rail-height)');
        expect(css).toContain('height: var(--gelia-pro-utilities-rail-height)');
        expect(css).not.toContain('min-height: auto');
    });

    it('pliega submenús con grid, no con display:none, durante collapsing', () => {
        expect(css).toMatch(/\[data-anim='collapsing'\], \[data-anim='settling'\]\) \.gelia-pro-sidebar__collapse--open \{[\s\S]*?grid-template-rows: 0fr/);
        const collapseDisplay = css.match(/\[data-anim='collapsing'\][^{]*\.gelia-pro-sidebar__collapse[^{]*\{[^}]*display:\s*none/);
        expect(collapseDisplay).toBeNull();
    });
});
