import { Link } from '@inertiajs/react';

export function puedeVerProductosPorAlmacen(auth) {
    const permisos = auth?.user?.permissions || [];
    const roles = auth?.user?.roles || [];

    return permisos.includes('almacenes.inventarios.ver')
        || permisos.includes('catalogos.gestionar')
        || roles.includes('Super Admin');
}

function puede(auth, permiso) {
    const permisos = auth?.user?.permissions || [];
    const roles = auth?.user?.roles || [];

    return permisos.includes(permiso) || roles.includes('Super Admin');
}

function paramsContexto({ sucursalId, almacenId, q }) {
    return {
        sucursal_id: sucursalId || undefined,
        almacen_id: almacenId || undefined,
        q: q || undefined,
    };
}

const CLASE_ENLACE = 'px-3 py-2 text-[10px] font-black uppercase theme-element border theme-border rounded-xl';

export default function EnlacesContextoAlmacen({ auth, sucursalId = '', almacenId = '', q = '', actual }) {
    const params = paramsContexto({ sucursalId, almacenId, q });
    const enlaces = [
        actual !== 'cobertura' && puedeVerProductosPorAlmacen(auth) && {
            href: route('almacenes.cobertura.index', params),
            label: 'Productos por almacén',
        },
        actual !== 'inventarios' && (puede(auth, 'almacenes.inventarios.ver') || puede(auth, 'catalogos.gestionar')) && {
            href: route('almacenes.inventarios.index', params),
            label: 'Inventarios de referencia',
        },
        actual !== 'costos' && (puede(auth, 'almacenes.costos.ver') || puede(auth, 'catalogos.gestionar')) && {
            href: route('almacenes.costos.index', params),
            label: 'Costos y precios',
        },
    ].filter(Boolean);

    if (enlaces.length === 0) {
        return null;
    }

    return (
        <nav aria-label="Contexto del almacén" className="flex flex-wrap gap-2">
            {enlaces.map((enlace) => (
                <Link key={enlace.label} href={enlace.href} className={CLASE_ENLACE}>
                    {enlace.label}
                </Link>
            ))}
        </nav>
    );
}
