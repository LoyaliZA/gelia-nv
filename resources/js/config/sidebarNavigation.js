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
    Layers,
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
    Landmark,
    Warehouse,
    Boxes,
    DollarSign,
    ClipboardCheck,
    Link2,
} from 'lucide-react';

import { ADMIN_MODULES, isAdminModuleAllowed, adminModuleHref } from './adminModules';

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
    
    const showAsistencia = can('funciones.asistencia');
    const showAvisos = can('funciones.avisos');
    const showGastos = can('funciones.gastos');
    const showLimpiezaArchivos = can('funciones.limpieza_archivos');
    const showTransacciones = can('funciones.transacciones');

    const showHerramientas = showReportes || showListados || showLimpieza || showEjercicioEscalonamiento || showAsistencia || showAvisos || showGastos || showLimpiezaArchivos || showTransacciones;

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
    ].filter(Boolean);

    const gestionPedidosChildren = [
        can('control_pedidos.ver_listado') && {
            type: 'link',
            id: 'control_pedidos_registrar',
            label: 'Registrar Pedidos',
            icon: Package,
            href: () => routeHref('control_pedidos.index', '/control-pedidos'),
            active: (url) => {
                const path = url.split('?')[0].replace(/\/$/, '');
                return path === '/control-pedidos';
            },
        },
        can('control_pedidos.auditar') && {
            type: 'link',
            id: 'control_pedidos_auditar',
            label: 'Auditar Pedidos',
            icon: ClipboardCheck,
            href: () => routeHref('control_pedidos.auditar.index', '/control-pedidos/auditar'),
            active: (url) => url.startsWith('/control-pedidos/auditar'),
        },
        can('control_pedidos.cedis') && {
            type: 'link',
            id: 'control_pedidos_cedis',
            label: 'Control Pedidos',
            icon: Warehouse,
            href: () => routeHref('control_pedidos.cedis.index', '/control-pedidos/cedis'),
            active: (url) => url.startsWith('/control-pedidos/cedis'),
        },
    ].filter(Boolean);

    const finanzasChildren = [
        can('contabilidad.ver') && {
            type: 'link',
            id: 'contabilidad',
            label: 'Contabilidad',
            icon: Calculator,
            href: () => routeHref('contabilidad.index', '/contabilidad'),
            active: (url) => url.startsWith('/contabilidad'),
        },
        can('facturas.ver_listado') && {
            type: 'link',
            id: 'facturas',
            label: 'Facturas',
            icon: Receipt,
            href: () => routeHref('facturas.index', '/facturas'),
            active: (url) => url.startsWith('/facturas'),
        },
        can('cobranza.ver') && {
            type: 'link',
            id: 'auto_cobranza',
            label: 'Credibox',
            icon: CreditCard,
            href: () => routeHref('auto-cobranza.index', '/auto-cobranza'),
            active: (url) => url.startsWith('/auto-cobranza'),
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
    ].filter(Boolean);

    const logisticaChildren = [
        can('entregas.cotizar') && {
            type: 'link',
            id: 'entregas',
            label: 'Cotizar Entregas',
            icon: Map,
            href: () => routeHref('entregas.index', '/entregas/cotizador'),
            active: (url) => url.startsWith('/entregas') && !url.startsWith('/admin/mapa-logistico'),
        },
        can('entregas.configurar_zonas') && {
            type: 'link',
            id: 'mapa_logistico',
            label: 'Mapa Logístico',
            icon: Layers,
            href: () => routeHref('admin.mapa_logistico.index', '/admin/mapa-logistico'),
            active: (url) => url.startsWith('/admin/mapa-logistico'),
        },
        gestionPedidosChildren.length > 0 && {
            type: 'group',
            id: 'gestion_pedidos',
            label: 'Gestión de pedidos',
            icon: Package,
            children: gestionPedidosChildren,
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
        showAsistencia && {
            type: 'link',
            id: 'asistencia',
            label: 'Asistencia',
            icon: Users,
            href: () => routeHref('funciones.asistencia.index', '/funciones/asistencia'),
            active: (url) => url.startsWith('/funciones/asistencia'),
        },
        showAvisos && {
            type: 'link',
            id: 'avisos',
            label: 'Avisos Mercancía',
            icon: FileSpreadsheet,
            href: () => routeHref('funciones.avisos.index', '/funciones/avisos'),
            active: (url) => url.startsWith('/funciones/avisos'),
        },
        showGastos && {
            type: 'link',
            id: 'gastos',
            label: 'Depuración Gastos',
            icon: DollarSign,
            href: () => routeHref('funciones.gastos.index', '/funciones/gastos'),
            active: (url) => url.startsWith('/funciones/gastos'),
        },
        showLimpiezaArchivos && {
            type: 'link',
            id: 'limpieza_archivos',
            label: 'Limpieza Archivos',
            icon: Database,
            href: () => routeHref('funciones.limpieza_archivos.index', '/funciones/limpieza-archivos'),
            active: (url) => url.startsWith('/funciones/limpieza-archivos'),
        },
        showTransacciones && {
            type: 'link',
            id: 'transacciones',
            label: 'Depuración Transacciones',
            icon: Receipt,
            href: () => routeHref('funciones.transacciones.index', '/funciones/transacciones'),
            active: (url) => url.startsWith('/funciones/transacciones'),
        },
    ].filter(Boolean);

    const almacenesChildren = [
        (can('almacenes.productos.ver') || can('catalogos.gestionar')) && {
            type: 'link',
            id: 'almacenes_productos',
            label: 'Productos',
            icon: Package,
            href: () => routeHref('almacenes.productos.index', '/almacenes/productos'),
            active: (url) => url.startsWith('/almacenes/productos'),
        },
        (can('almacenes.inventarios.ver') || can('catalogos.gestionar')) && {
            type: 'link',
            id: 'almacenes_inventarios',
            label: 'Inventarios',
            icon: Boxes,
            href: () => routeHref('almacenes.inventarios.index', '/almacenes/inventarios'),
            active: (url) => url.startsWith('/almacenes/inventarios'),
        },
        (can('almacenes.costos.ver') || can('catalogos.gestionar')) && {
            type: 'link',
            id: 'almacenes_costos',
            label: 'Costos',
            icon: DollarSign,
            href: () => routeHref('almacenes.costos.index', '/almacenes/costos'),
            active: (url) => url.startsWith('/almacenes/costos'),
        },
    ].filter(Boolean);

    const vinculacionesChildren = [
        can('woocommerce.ver') && {
            type: 'group',
            id: 'woocommerce',
            label: 'WooCommerce',
            icon: ShoppingBag,
            children: [
                {
                    type: 'link',
                    id: 'woocommerce_productos',
                    label: 'Productos (Sincronizar precios)',
                    icon: Package,
                    href: () => routeHref('woocommerce.index', '/woocommerce'),
                    active: (url) => url.startsWith('/woocommerce'),
                }
            ]
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
        can('rh.ver') && {
            type: 'link',
            id: 'rh',
            label: 'Recursos Humanos',
            icon: Users,
            href: () => routeHref('rh.index', '/rh'),
            active: (url) => url.startsWith('/rh'),
        },
        can('rh.incidencias.gerente.ver') && !can('rh.ver') && {
            type: 'link',
            id: 'rh_incidencias_gerente',
            label: 'Incidencias RH',
            icon: Users,
            href: () => routeHref('rh.incidencias_gerente.index', '/rh/incidencias-gerente'),
            active: (url) => url.startsWith('/rh/incidencias-gerente'),
        },
        can('activos.ver') && {
            type: 'link',
            id: 'activos',
            label: 'Control de Activos',
            icon: Package,
            href: () => routeHref('activos.index', '/activos'),
            active: (url) => url.startsWith('/activos'),
        },
        can('gestion_interna.directorio.ver') && {
            type: 'link',
            id: 'directorio',
            label: 'Directorio Interno',
            icon: Users,
            href: () => routeHref('gestion_interna.directorio.index', '/gestion-interna/directorio'),
            active: (url) => url.startsWith('/gestion-interna/directorio'),
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
            type: 'group',
            id: 'admin',
            label: 'Administración',
            icon: Shield,
            children: ADMIN_MODULES.map((item) => {
                return isAdminModuleAllowed(item, can) && {
                    type: 'link',
                    id: item.id,
                    label: item.title,
                    description: item.description,
                    icon: item.icon,
                    href: () => adminModuleHref(item),
                    active: (url) => url.startsWith(item.path),
                };
            }).filter(Boolean),
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
        finanzasChildren.length > 0 && {
            type: 'group',
            id: 'finanzas',
            label: 'Finanzas',
            icon: Landmark,
            children: finanzasChildren,
        },
        showHerramientas && herramientasChildren.length > 0 && {
            type: 'group',
            id: 'herramientas',
            label: 'Herramientas',
            icon: Wrench,
            children: herramientasChildren,
        },
        vinculacionesChildren.length > 0 && {
            type: 'group',
            id: 'vinculaciones',
            label: 'Vinculaciones',
            icon: Link2,
            children: vinculacionesChildren,
        },
        gestionChildren.length > 0 && {
            type: 'group',
            id: 'gestion_interna',
            label: 'Gestión Interna',
            icon: Settings,
            children: gestionChildren,
        },
        almacenesChildren.length > 0 && {
            type: 'group',
            id: 'almacenes',
            label: 'Almacenes',
            icon: Warehouse,
            children: almacenesChildren,
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
