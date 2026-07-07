import {
    Link as LinkIcon,
    Database,
    FolderTree,
    Palette,
    Calculator,
    Users,
    History,
    Globe,
    Map,
    Settings,
    ShoppingBag,
    LifeBuoy,
} from 'lucide-react';

/** Módulos del panel de administración (sidebar + /admin). */
export const ADMIN_MODULES = [
    {
        id: 'enlaces',
        title: 'Generar Enlaces',
        description: 'Crear enlaces seguros de registro y asignar permisos a nuevos accesos.',
        path: '/admin/enlaces',
        routeName: 'admin.enlaces',
        icon: LinkIcon,
        permission: 'usuarios.generar_permisos',
    },
    {
        id: 'clientes',
        title: 'Base de Clientes',
        description: 'Gestionar clientes, importaciones, listas especiales y bloqueos.',
        path: '/admin/clientes',
        routeName: 'admin.clientes',
        icon: Database,
        permission: 'clientes.ver',
    },
    {
        id: 'catalogos',
        title: 'Catálogos Globales',
        description: 'Departamentos, procesos, listas, productos y catálogos del sistema.',
        path: '/admin/catalogos',
        routeName: 'admin.catalogos',
        icon: FolderTree,
        permission: 'catalogos.gestionar',
    },
    {
        id: 'personalizacion',
        title: 'Personalización',
        description: 'Tonos, fondos y temas visuales para la experiencia de la plataforma.',
        path: '/admin/personalizacion',
        routeName: 'admin.personalizacion.index',
        icon: Palette,
        permission: 'personalizacion.gestionar',
    },
    {
        id: 'comisiones',
        title: 'Comisiones',
        description: 'Configurar tabuladores y montos de comisión por rol o proceso.',
        path: '/admin/comisiones',
        routeName: 'admin.comisiones',
        icon: Calculator,
        permission: 'comisiones.gestionar',
    },
    {
        id: 'usuarios',
        title: 'Usuarios',
        description: 'Administrar cuentas, roles y permisos heredados del equipo.',
        path: '/admin/usuarios',
        routeName: 'admin.usuarios',
        icon: Users,
        permission: 'usuarios.gestionar',
    },
    {
        id: 'auditorias',
        title: 'Auditorías de Sistema',
        description: 'Historial de cambios en listas de descuento y operaciones críticas.',
        path: '/admin/auditorias-sistema',
        routeName: 'admin.auditorias_sistema.index',
        icon: History,
        permissionAny: ['sistema.auditorias.ver', 'sistema.auditorias.accesos.ver'],
    },
    {
        id: 'api_externa',
        title: 'API Externa',
        description: 'Aplicaciones, recursos, permisos y auditoría de integraciones.',
        path: '/admin/api-externa',
        routeName: 'admin.api_externa.index',
        icon: Globe,
        permissionAny: ['api_externa.gestionar', 'api_externa.ver_auditoria'],
    },
    {
        id: 'mapa_logistico',
        title: 'Mapa Logístico',
        description: 'Configurar zonas, polígonos y periferias para el cotizador de entregas.',
        path: '/admin/mapa-logistico',
        routeName: 'admin.mapa_logistico.index',
        icon: Map,
        permission: 'entregas.configurar_zonas',
    },
    {
        id: 'woocommerce',
        title: 'Sincronizar Precios',
        description: 'WooCommerce: sincronización de precios, catálogo y auditoría.',
        path: '/woocommerce',
        routeName: 'woocommerce.index',
        icon: ShoppingBag,
        permission: 'woocommerce.configurar',
    },
    {
        id: 'configuracion_sistema',
        title: 'Configuración del Sistema',
        description: 'Variables globales, sobreescritura de .env y testeo de integraciones clave.',
        path: '/admin/configuracion-sistema',
        routeName: 'admin.configuracion_sistema.index',
        icon: Settings,
        permission: 'configuracion_sistema.gestionar',
    },
    {
        id: 'soporte_gestion',
        title: 'Gestión de Soporte',
        description: 'Dashboard de tickets, configuración de SLA y catálogos.',
        path: '/soporte/agente/tickets',
        routeName: 'soporte.agente.tickets.index',
        icon: LifeBuoy,
        permissionAny: ['soporte.gestionar', 'soporte.administrar'],
    },
];

export function adminModuleHref(item) {
    if (typeof route === 'function') {
        try {
            return route(item.routeName);
        } catch {
            // Ziggy desactualizado
        }
    }
    return item.path;
}

export function isAdminModuleAllowed(item, can) {
    if (item.permissionAny?.length) {
        return item.permissionAny.some((perm) => can(perm));
    }
    return can(item.permission);
}

export function hasAnyAdminModuleAccess(can) {
    return ADMIN_MODULES.some((item) => isAdminModuleAllowed(item, can));
}

/** Permisos únicos para autorizar la ruta /admin en backend. */
export const ADMIN_PANEL_PERMISSIONS = [
    ...new Set(
        ADMIN_MODULES.flatMap((item) =>
            item.permissionAny?.length ? item.permissionAny : [item.permission]
        )
    ),
];
