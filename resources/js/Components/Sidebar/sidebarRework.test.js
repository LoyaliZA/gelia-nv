import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, it, expect } from 'vitest';

const aqui = dirname(fileURLToPath(import.meta.url));
const leer = (...ruta) => readFileSync(resolve(aqui, ...ruta), 'utf8');

const css = leer('../../../css/gelia/features/sidebar-professional.css');
const appCss = leer('../../../css/app.css');
const group = leer('SidebarGroup.jsx');
const tree = leer('SidebarTree.jsx');
const userMenu = leer('SidebarUserMenu.jsx');
const utilities = leer('SidebarUtilities.jsx');

describe('sidebar profesional: reparaciones', () => {
    it('los tooltips ya no dependen de ::after (el aside los recortaba) sino de un portal', () => {
        expect(css).not.toMatch(/__tooltip\[data-tip\]:hover::after/);
        expect(css).toContain('.gelia-pro-tip {');
        expect(css).toMatch(/\.gelia-pro-tip \{[\s\S]*?position: fixed/);
        for (const fuente of [group, leer('SidebarLink.jsx'), leer('SidebarBrand.jsx'), leer('SidebarUserPanel.jsx')]) {
            expect(fuente).not.toContain('gelia-pro-sidebar__tooltip');
        }
    });

    it('los grupos plegados no reciben foco (inert) y el encabezado se pliega por CSS sin cambiar de nodo', () => {
        expect(group).toContain('inert={!isExpanded}');
        expect(tree).not.toContain('gelia-pro-sidebar__rule');
        expect(css).toMatch(/\[data-collapsed='true'\] \.gelia-pro-sidebar__header \{[\s\S]*?height: 1px/);
    });

    it('al terminar la animación nada cambia de sitio: sin bloque sr-only posterior ni transición de padding del contenido', () => {
        expect(css).not.toContain("[data-collapsed='true']:not([data-anim]) .gelia-pro-sidebar__row-label");
        const shell = leer('../../../css/gelia/layout/app-shell.css');
        expect(shell).not.toMatch(/\.gelia-app-body \{\s*transition: padding-inline-start/);
    });

    it('el flyout del rail lleva el icono y la sección en el título', () => {
        const flyout = leer('SidebarCollapsedFlyout.jsx');
        expect(flyout).toContain('gelia-pro-flyout__tile');
        expect(flyout).toContain('gelia-pro-flyout__eyebrow');
        expect(tree).toContain('findSectionLabel');
    });

    it('el cajón móvil cerrado queda invisible para foco y lectores, con visibilidad diferida', () => {
        expect(css).toMatch(/\.gelia-pro-sidebar--drawer \{[\s\S]*?visibility: hidden;[\s\S]*?visibility 0s linear 0\.34s/);
        expect(css).toMatch(/\.gelia-pro-sidebar--drawer\[data-open='true'\] \{\s*visibility: visible;/);
    });

    it('el menú de usuario anima también su salida y se mantiene montado mientras dura', () => {
        expect(userMenu).toContain('useMenuPresence');
        expect(userMenu).toContain("data-state={state}");
        expect(css).toContain("[data-state='closing']");
        expect(css).toContain('@keyframes geliaProMenuOut');
    });

    it('la tira de utilidades suma Ajustes y el rail contraído reserva tres celdas', () => {
        expect(utilities).toContain('abrirAjustes');
        expect(utilities).toContain('aria-label="Ajustes"');
        expect(css).toContain('--gelia-pro-utilities-rail-height: calc(3 * var(--gelia-pro-rail-cell)');
    });

    it('el menú de usuario abre Ajustes en modal, no navega a una página de preferencias', () => {
        expect(userMenu).toContain('abrirAjustes()');
        expect(userMenu).not.toContain('profile.preferencias');
        expect(userMenu).not.toContain('Preferencias');
    });

    it('respeta prefers-reduced-motion en lo nuevo y carga el CSS del modal', () => {
        expect(css).toMatch(/prefers-reduced-motion: reduce\)[\s\S]*?\.gelia-pro-tip/);
        expect(appCss).toContain("./gelia/features/ajustes.css");
    });

    it('las filas raíz no cambian de tamaño al contraer: solo se anima el ancho del aside', () => {
        expect(css).toMatch(/\.gelia-pro-sidebar__row \{[\s\S]*?transition: background 0\.18s ease, border-color 0\.18s ease, color 0\.18s ease;/);
        expect(css).not.toMatch(/\[data-collapsed='true'\] \.gelia-pro-sidebar__row--root \{[^}]*width: var\(--gelia-pro-rail-cell\)/);
        expect(css).not.toMatch(/\[data-collapsed='true'\] \.gelia-pro-sidebar__group \{/);
        expect(leer('SidebarLink.jsx')).toContain('memo(SidebarLink)');
    });

    it('el flyout se ajusta a sus opciones y solo lo limita la pantalla', () => {
        const flyout = leer('SidebarCollapsedFlyout.jsx');
        expect(flyout).not.toContain('24rem');
        expect(flyout).toContain('calc(100dvh -');
    });
});
