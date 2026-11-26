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
        const sistema = tree.find((n) => n?.id === 'sistema');
        expect(sistema).toBeTruthy();
        expect(sistema.children.some((n) => n?.id === 'admin')).toBe(false);
        expect(sistema.children.some((n) => n?.id === 'usuarios')).toBe(true);
        const labels = sistema.children.map((n) => n.label);
        const sorted = [...labels].sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base' }));
        expect(labels).toEqual(sorted);
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
        const logistica = tree.find((n) => n?.id === 'logistica');
        const operaciones = tree.find((n) => n?.id === 'operaciones');
        expect(logistica?.label).toBe('Logística');
        expect(operaciones).toBeFalsy();
    });

    it('usuario con permiso equipo_ver ve Operación General', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['punto_venta.acceder', 'pdv.operacion.equipo_ver']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).toContain('punto_venta_operacion_general');
        expect(ids).not.toContain('punto_venta_vendedores');
        expect(ids).not.toContain('punto_venta_operacion');
    });

    it('usuario con permiso de turnos ve Operación General', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['punto_venta.acceder', 'pdv.turnos.ver']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).toContain('punto_venta_operacion_general');
        expect(ids).not.toContain('punto_venta_vendedores');
    });

    it('usuario PDV sin turnos ni equipo no ve Operación General', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['punto_venta.acceder', 'pdv.turnos.atender']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).not.toContain('punto_venta_operacion_general');
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

    it('usuario con permiso historial entregas ve enlace dedicado', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['punto_venta.acceder', 'pdv.resguardos.ver_historial_entregas']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).toContain('punto_venta_resguardos_entregados');
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

    it('usuario con permiso publicidad.ver ve enlace Publicidad', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['punto_venta.acceder', 'pdv.publicidad.ver']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).toContain('punto_venta_publicidad');
    });

    it('usuario sin permiso publicidad.ver no ve enlace Publicidad', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['punto_venta.acceder', 'pdv.pantalla_sala.abrir']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).not.toContain('punto_venta_publicidad');
    });

    it('usuario con tiendanube.ver ve catálogo y precios en vinculaciones', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['tiendanube.ver']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = collectLinkIds(tree);
        expect(ids).toContain('tiendanube_catalogo');
        expect(ids).toContain('tiendanube_precios');
    });

    it('marca activo precios sin resaltar catálogo en /tiendanube/precios', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['tiendanube.ver']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const vinculaciones = tree.find((n) => n?.id === 'vinculaciones');
        const tiendanube = vinculaciones?.children?.find((n) => n?.id === 'tiendanube');
        const catalogo = tiendanube?.children?.find((n) => n?.id === 'tiendanube_catalogo');
        const precios = tiendanube?.children?.find((n) => n?.id === 'tiendanube_precios');
        expect(catalogo?.active?.('/tiendanube/precios')).toBe(false);
        expect(precios?.active?.('/tiendanube/precios')).toBe(true);
        expect(catalogo?.active?.('/tiendanube')).toBe(true);
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

    it('el grupo Almacenes conserva su orden explícito y el resto sigue alfabético', () => {
        const tree = buildSidebarNavigation({
            can: canWith([], true),
            showAdminMenu: true,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const almacenes = tree.find((n) => n?.id === 'almacenes');
        expect(almacenes.children.map((n) => n.id)).toEqual([
            'almacenes_resumen',
            'almacenes_productos_gi',
            'almacenes_catalogo',
            'almacenes_inventarios',
            'almacenes_costos',
            'almacenes_importaciones',
            'avisos',
            'almacenes_config_catalogos',
        ]);
        expect(almacenes.children.map((n) => n.label)).not.toContain('Cobertura');
        expect(almacenes.children.find((n) => n.id === 'almacenes_costos')?.label).toBe('Costos y precios');
        expect(almacenes.children.find((n) => n.id === 'almacenes_productos_gi')?.href()).toBe('/gestion-interna/productos');

        const gestion = tree.find((n) => n?.id === 'gestion_interna');
        expect(gestion.children.some((n) => n.id === 'gestion_productos')).toBe(false);

        const logistica = tree.find((n) => n?.id === 'logistica');
        const pedidos = logistica.children.find((n) => n?.id === 'gestion_pedidos');
        expect(pedidos.children.map((n) => n.id)).toEqual([
            'control_pedidos_registrar',
            'control_pedidos_auditar',
            'control_pedidos_cedis',
            'control_pedidos_tienda',
            'control_pedidos_delegado',
            'control_pedidos_direcciones',
            'control_pedidos_plazos',
        ]);
        expect(pedidos.children.find((n) => n.id === 'control_pedidos_plazos')?.label).toBe('Configurar Operación');

        const sistema = tree.find((n) => n?.id === 'sistema');
        const labels = sistema.children.map((n) => n.label);
        const sorted = [...labels].sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base', numeric: true }));
        expect(labels).toEqual(sorted);
        expect(labels).not.toEqual(almacenes.children.map((n) => n.label));
    });

    it('quien solo ve costos no recibe Cobertura ni inventarios en el menú', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['almacenes.costos.ver']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const almacenes = tree.find((n) => n?.id === 'almacenes');
        const ids = almacenes.children.map((n) => n.id);
        expect(ids).toEqual(['almacenes_resumen', 'almacenes_catalogo', 'almacenes_costos']);
        expect(almacenes.children.find((n) => n.id === 'almacenes_costos')?.label).toBe('Costos y precios');
    });

    it('quien solo ve inventario no recibe costos ni el ítem Cobertura', () => {
        const tree = buildSidebarNavigation({
            can: canWith(['almacenes.inventarios.ver']),
            showAdminMenu: false,
            manualesHubVisible: false,
            geliaAiVisible: false,
        });
        const ids = tree.find((n) => n?.id === 'almacenes').children.map((n) => n.id);
        expect(ids).toEqual(['almacenes_resumen', 'almacenes_catalogo', 'almacenes_inventarios']);
        expect(ids).not.toContain('almacenes_cobertura');
    });

    it('agrupa el menú en secciones con encabezado y ningún encabezado queda vacío', () => {
        const tree = buildSidebarNavigation({
            can: canWith([], true),
            showAdminMenu: true,
            manualesHubVisible: true,
            geliaAiVisible: true,
        });
        const secuencia = tree.map((n) => `${n.type}:${n.id}`);
        expect(secuencia).toEqual([
            'header:seccion_principal',
            'group:inicio',
            'header:seccion_operacion',
            'group:comercial',
            'group:logistica',
            'group:punto_venta',
            'header:seccion_finanzas',
            'group:finanzas',
            'group:reportes',
            'header:seccion_inventario',
            'group:almacenes',
            'group:vinculaciones',
            'header:seccion_organizacion',
            'group:gestion_interna',
            'header:seccion_sistema',
            'group:herramientas',
            'group:soporte',
            'group:sistema',
        ]);

        const parcial = buildSidebarNavigation({
            can: canWith(['facturas.ver_listado']),
            showAdminMenu: false,
        });
        const headers = parcial.filter((n) => n.type === 'header').map((n) => n.id);
        expect(headers).toContain('seccion_finanzas');
        expect(headers).not.toContain('seccion_inventario');
        parcial.forEach((nodo, i) => {
            if (nodo.type === 'header') expect(parcial[i + 1]?.type).toBe('group');
        });
    });

    it('Inicio conserva el Panel Principal primero y no repite enlaces entre grupos', () => {
        const tree = buildSidebarNavigation({
            can: canWith([], true),
            showAdminMenu: true,
            manualesHubVisible: true,
            geliaAiVisible: true,
        });
        const inicio = tree.find((n) => n?.id === 'inicio');
        expect(inicio.children.map((n) => n.id)).toEqual(['dashboard', 'mensajeria', 'gelia_ai']);

        const ids = collectLinkIds(tree);
        expect(new Set(ids).size).toBe(ids.length);
    });

    it('reubica los módulos en la sección que les corresponde', () => {
        const tree = buildSidebarNavigation({
            can: canWith([], true),
            showAdminMenu: true,
            manualesHubVisible: true,
            geliaAiVisible: true,
        });
        const grupo = (id) => tree.find((n) => n?.id === id);
        const idsDe = (id) => collectLinkIds(grupo(id).children);

        expect(idsDe('logistica')).toEqual(expect.arrayContaining(['traspasos', 'traspasos_cedis', 'mapa_logistico']));
        expect(idsDe('comercial')).toEqual(expect.arrayContaining(['solicitudes', 'cancelaciones', 'mis_clientes', 'plantilla_bellaroma']));
        expect(idsDe('finanzas')).toContain('ejercicio_escalonamiento');
        expect(idsDe('reportes')).toContain('listados');
        expect(idsDe('gestion_interna')).toContain('asistencia');
        expect(idsDe('almacenes')).toContain('avisos');
        expect(idsDe('herramientas').sort()).toEqual(
            ['gastos', 'limpieza_archivos', 'limpieza_clientes', 'transacciones'].sort()
        );
        expect(tree.some((n) => n?.id === 'operaciones' || n?.id === 'solicitudes_group')).toBe(false);
    });

    it('Logística pone la operación diaria primero y la configuración de zonas al final', () => {
        const tree = buildSidebarNavigation({ can: canWith([], true), showAdminMenu: false });
        const ids = tree.find((n) => n?.id === 'logistica').children.map((n) => n.id);
        expect(ids).toEqual(['entregas', 'traspasos', 'traspasos_cedis', 'gestion_pedidos', 'mapa_logistico']);
    });
});
