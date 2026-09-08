import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { buildSidebarNavigation, collectOpenGroupIdsForUrl } from './sidebarNavigation';

function canWith(perms, isSuperAdmin = false) {
    return (permission) => isSuperAdmin || perms.includes(permission);
}

function collectLinkIds(nodes, acc = []) {
    for (const node of nodes || []) {
        if (!node) continue;
        if (node.type === 'link') acc.push(node.id);
        if (node.type === 'group') collectLinkIds(node.children, acc);
    }
    return acc;
}

describe('buildSidebarNavigation permissions', () => {
    it('Super Admin ve administración cuando showAdminMenu es true', () => {
        const tree = buildSidebarNavigation({
            can: canWith([], true),
            showAdminMenu: true,
            manualesHubVisible: true,
            geliaAiVisible: true,
        });
        const ids = collectLinkIds(tree);
        expect(ids).toContain('dashboard');
        expect(ids.length).toBeGreaterThan(5);
        const sistema = tree.find((n) => n?.id === 'sistema' || n?.label === 'Sistema');
        expect(sistema || ids.some((id) => id.includes('admin') || id.includes('usuarios'))).toBeTruthy();
    });

    it('usuario sin permisos solo ve enlaces públicos del árbol', () => {
        const tree = buildSidebarNavigation({
            can: canWith([]),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).toContain('dashboard');
        expect(ids).not.toContain('usuarios');
        expect(ids).not.toContain('roles');
        expect(ids.every((id) => !String(id).startsWith('admin_'))).toBe(true);
    });

    it('usuario parcial solo ve módulos autorizados', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['control_pedidos.ver_listado', 'facturas.ver_listado']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).toContain('control_pedidos_registrar');
        expect(ids).not.toContain('control_pedidos_auditar');
    });

    it('usuario con permiso equipo_ver ve enlace de vendedores', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['punto_venta.acceder', 'pdv.operacion.equipo_ver']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).toContain('punto_venta_vendedores');
    });

    it('usuario sin permiso equipo_ver no ve enlace de vendedores', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['punto_venta.acceder', 'pdv.turnos.ver']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).not.toContain('punto_venta_vendedores');
    });

    it('usuario con permiso PDV ve enlace de resguardos', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['punto_venta.acceder', 'pdv.resguardos.ver']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).toContain('punto_venta_resguardos');
        const puntoVenta = tree.find((n) => n?.id === 'punto_venta');
        expect(puntoVenta?.label).toBe('Punto de venta');
    });

    it('usuario sin permiso PDV no ve enlace de resguardos', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['control_pedidos.ver_listado']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).not.toContain('punto_venta_resguardos');
    });

    it('usuario con permiso atender ve enlace Mi atención', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['punto_venta.acceder', 'pdv.turnos.atender']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).toContain('punto_venta_turnos_ventas');
        const puntoVenta = tree.find((n) => n?.id === 'punto_venta');
        const miAtencion = puntoVenta?.children?.find((n) => n?.id === 'punto_venta_turnos_ventas');
        expect(miAtencion?.label).toBe('Mi atención');
    });

    it('usuario con turnos.ver pero sin atender no ve enlace Mi atención', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['punto_venta.acceder', 'pdv.turnos.ver', 'pdv.turnos.alta']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).not.toContain('punto_venta_turnos_ventas');
    });

    it('usuario con permiso pantalla_sala.abrir ve enlace Pantalla de sala', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['punto_venta.acceder', 'pdv.pantalla_sala.abrir']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).toContain('punto_venta_pantalla_sala');
    });

    it('usuario sin permiso pantalla_sala.abrir no ve enlace Pantalla de sala', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['punto_venta.acceder', 'pdv.turnos.ver']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).not.toContain('punto_venta_pantalla_sala');
    });

    it('abre antecesores de una ruta anidada activa', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['control_pedidos.ver_listado', 'control_pedidos.auditar'], true),
            showAdminMenu: true,
            geliaAiVisible: false,
        });
        const open = collectOpenGroupIdsForUrl(tree, '/control-pedidos/auditar');
        expect(open.size).toBeGreaterThan(0);
    });
});
