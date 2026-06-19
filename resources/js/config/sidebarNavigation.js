import {
    Home,
    LayoutDashboard,
    MessageCircle,
    Briefcase,
    User,
    Users,
    Ban,
    Truck,
    ClipboardList,
    Map,
    CreditCard,
    Receipt,
    Settings,
    Package,
    Wrench,
    BarChart3,
    List,
    Lock,
    Shield,
    Tag,
    Database,
    FileSpreadsheet,
    ShoppingBag,
    Calculator,
    LifeBuoy,
    Bug,
} from 'lucide-react';

function routeHref(name, fallback) {
    if (typeof route === 'function') {
        try {
            return route(name);
        } catch {
            return fallback;
        }
    }
    return fallback;
}

/** Árbol de navegación del menú lateral (permisos aplicados al renderizar). */
export function buildSidebarNavigation({ can, showAdminMenu }) {
    const showReportes = can('solicitudes.exportar');
    const showListados = can('listados.ver');
    const showLimpieza = can('funciones.limpieza_clientes');
    const showEjercicioEscalonamiento = can('ejercicio_escalonamiento.ver');
    const showHerramientas = showReportes || showListados || showLimpieza || showEjercicioEscalonamiento;

    const solicitudesChildren = [
        can('solicitudes.ver_listado') && {
            type: 'link',
            id: 'solicitudes',
            label: 'Cambio de Lista y Tags',
            icon: Tag,
            href: () => routeHref('solicitudes.index', '/solicitudes'),
            active: (url) => url.startsWith('/solicitudes'),
        },
        can('cancelaciones_cotizaciones.ver_listado') && {
            type: 'link',
            id: 'cancelaciones',
            label: 'Cancelación y cotización',
            icon: Ban,
            href: () => routeHref('cancelaciones_cotizaciones.index', '/cancelaciones-cotizaciones'),
            active: (url) => url.startsWith('/cancelaciones-cotizaciones'),
        },
        can('facturas.ver_listado') && {
            type: 'link',
            id: 'facturas',
            label: 'Facturas',
            icon: Receipt,
            href: () => routeHref('facturas.index', '/facturas'),
            active: (url) => url.startsWith('/facturas'),
        },
    ].filter(Boolean);

    const comercialChildren = [
        can('mis_clientes.gestionar') && {
            type: 'link',
            id: 'mis_clientes',
            label: 'Mis Clientes',
            icon: Users,
            href: () => routeHref('mis_clientes.index', '/mis-clientes'),
            active: (url) => url.startsWith('/mis-clientes'),
        },
        can('cobranza.ver') && {
            type: 'link',
            id: 'auto_cobranza',
            label: 'Auto-Cobranza',
            icon: CreditCard,
            href: () => routeHref('auto-cobranza.index', '/auto-cobranza'),
            active: (url) => url.startsWith('/auto-cobranza'),
        },
    ].filter(Boolean);

    const logisticaChildren = [
        can('entregas.cotizar') && {
            type: 'link',
            id: 'entregas',
            label: 'Cotizar Entregas',
            icon: Map,
            href: () => routeHref('entregas.index', '/entregas/cotizador'),
            active: (url) => url.startsWith('/entregas'),
        },
    ].filter(Boolean);

    const operacionesChildren = [
        solicitudesChildren.length > 0 && {
            type: 'group',
            id: 'solicitudes_group',
            label: 'Solicitudes',
            icon: ClipboardList,
            children: solicitudesChildren,
        },
        comercialChildren.length > 0 && {
            type: 'group',
            id: 'comercial',
            label: 'Comercial',
            icon: User,
            children: comercialChildren,
        },
        logisticaChildren.length > 0 && {
            type: 'group',
            id: 'logistica',
            label: 'Logística',
            icon: Truck,
            children: logisticaChildren,
        },
    ].filter(Boolean);

    const herramientasChildren = [
        showReportes && {
            type: 'link',
            id: 'reportes',
            label: 'Reportes',
            icon: BarChart3,
            href: () => routeHref('reportes.solicitudes.index', '/reportes/solicitudes'),
            active: (url) => url.startsWith('/reportes/solicitudes'),
        },
        showListados && {
            type: 'link',
            id: 'listados',
            label: 'Listados',
            icon: List,
            href: () => routeHref('listados.index', '/funciones/listados'),
            active: (url) => url.startsWith('/funciones/listados') || url.startsWith('/listados'),
        },
        showLimpieza && {
            type: 'link',
            id: 'limpieza_clientes',
            label: 'Limpieza de Clientes',
            icon: Database,
            href: () => routeHref('funciones.limpieza-clientes.index', '/funciones/limpieza-clientes'),
            active: (url) => url.startsWith('/funciones/limpieza-clientes'),
        },
        showEjercicioEscalonamiento && {
            type: 'link',
            id: 'ejercicio_escalonamiento',
            label: 'Ejercicio Escalonamiento',
            icon: Calculator,
            href: () => routeHref('ejercicio_escalonamiento.index', '/funciones/ejercicio-escalonamiento'),
            active: (url) => url.startsWith('/funciones/ejercicio-escalonamiento'),
        },
    ].filter(Boolean);

    const gestionChildren = [
        can('plantilla_pedidos.ver') && {
            type: 'link',
            id: 'plantilla_bellaroma',
            label: 'Plantilla Pedidos',
            icon: FileSpreadsheet,
            href: () => routeHref('plantilla_bellaroma.index', '/plantilla-bellaroma'),
            active: (url) => url.startsWith('/plantilla-bellaroma'),
        },
        can('woocommerce.ver') && {
            type: 'link',
            id: 'woocommerce',
            label: 'Sincronizar Precios',
            icon: ShoppingBag,
            href: () => routeHref('woocommerce.index', '/woocommerce'),
            active: (url) => url.startsWith('/woocommerce'),
        },
        can('rh.ver') && {
            type: 'link',
            id: 'rh',
            label: 'Recursos Humanos',
            icon: Users,
            href: () => routeHref('rh.index', '/rh'),
            active: (url) => url.startsWith('/rh'),
        },
        can('activos.ver') && {
            type: 'link',
            id: 'activos',
            label: 'Control de Activos',
            icon: Package,
            href: () => routeHref('activos.index', '/activos'),
            active: (url) => url.startsWith('/activos'),
        },
    ].filter(Boolean);

    const soporteChildren = [
        (can('soporte.gestionar') || can('soporte.administrar')) && {
            type: 'link',
            id: 'soporte_dashboard',
            label: 'Dashboard de Soporte',
            icon: Shield,
            href: () => routeHref('soporte.agente.tickets.index', '/soporte/agente/tickets'),
            active: (url) => url.startsWith('/soporte/agente'),
        },
        {
            type: 'link',
            id: 'soporte_reportar',
            label: 'Reportar Errores',
            icon: Bug,
            href: () => routeHref('soporte.tickets.index', '/soporte/mis-tickets'),
            active: (url) => url.startsWith('/soporte/mis-tickets'),
        },
        {
            type: 'link',
            id: 'soporte_qa',
            label: 'QyA',
            icon: MessageCircle,
            href: () => routeHref('soporte.qa.index', '/soporte/qa'),
            active: (url) => url.startsWith('/soporte/qa'),
        },
    ].filter(Boolean);

    const sistemaChildren = [
        showAdminMenu && {
            type: 'link',
            id: 'admin',
            label: 'Administración',
            icon: Shield,
            href: () => routeHref('admin.index', '/admin'),
            active: (url) => url.startsWith('/admin') && !url.startsWith('/admin/catalogo-maestro'),
        },
        showAdminMenu && {
            type: 'link',
            id: 'catalogo_maestro',
            label: 'Catálogo Maestro',
            icon: Database,
            href: () => routeHref('admin.catalogo-maestro.index', '/admin/catalogo-maestro'),
            active: (url) => url.startsWith('/admin/catalogo-maestro'),
        },
        can('mensajeria.monitorear') && {
            type: 'link',
            id: 'mensajeria_monitoreo',
            label: 'Monitoreo Mensajería',
            icon: MessageCircle,
            href: () => routeHref('mensajeria_monitoreo.index', '/admin/mensajeria-monitoreo'),
            active: (url) => url.startsWith('/admin/mensajeria-monitoreo'),
        },
    ].filter(Boolean);

    return [
        { type: 'header', id: 'accesos', label: 'ACCESOS_' },
        {
            type: 'group',
            id: 'inicio',
            label: 'Inicio',
            icon: Home,
            defaultOpen: true,
            children: [
                {
                    type: 'link',
                    id: 'dashboard',
                    label: 'Panel Principal',
                    icon: LayoutDashboard,
                    href: () => routeHref('dashboard', '/dashboard'),
                    active: (url) => url === '/dashboard',
                },
                {
                    type: 'link',
                    id: 'mensajeria',
                    label: 'Mensajería',
                    icon: MessageCircle,
                    href: () => routeHref('mensajeria.index', '/mensajeria'),
                    active: (url) => url.startsWith('/mensajeria'),
                },
            ],
        },
        operacionesChildren.length > 0 && {
            type: 'group',
            id: 'operaciones',
            label: 'Operaciones',
            icon: Briefcase,
            children: operacionesChildren,
        },
        showHerramientas && herramientasChildren.length > 0 && {
            type: 'group',
            id: 'herramientas',
            label: 'Herramientas',
            icon: Wrench,
            children: herramientasChildren,
        },
        gestionChildren.length > 0 && {
            type: 'group',
            id: 'gestion_interna',
            label: 'Gestión Interna',
            icon: Settings,
            children: gestionChildren,
        },
        soporteChildren.length > 0 && {
            type: 'group',
            id: 'soporte',
            label: 'Soporte',
            icon: LifeBuoy,
            children: soporteChildren,
        },
        sistemaChildren.length > 0 && {
            type: 'group',
            id: 'sistema',
            label: 'Sistema',
            icon: Lock,
            children: sistemaChildren,
        },
    ].filter(Boolean);
}

/** IDs de grupos ancestros de la ruta activa (para expandir al navegar). */
export function collectOpenGroupIdsForUrl(nodes, url, ancestors = []) {
    const open = new Set(['inicio']);

    const walk = (items, chain) => {
        for (const node of items) {
            if (!node || node.type === 'header') continue;

            if (node.type === 'link') {
                if (node.active?.(url)) {
                    chain.forEach((id) => open.add(id));
                }
                continue;
            }

            if (node.type === 'group') {
                const nextChain = [...chain, node.id];
                if (node.defaultOpen) {
                    open.add(node.id);
                }
                if (node.children?.length) {
                    walk(node.children, nextChain);
                }
            }
        }
    };

    walk(nodes, ancestors);
    return open;
}
