export async function confirmarPedidoAdmin(cierreId) {
    const res = await fetch(route('reportes.pagos_pedidos.confirmar_admin.pedido', { cierre: cierreId }), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        credentials: 'same-origin',
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(data.message || 'No se pudo confirmar el pedido.');
    }
    return data;
}

export async function confirmarExhibicionAdmin(cierreId, itemId) {
    const res = await fetch(route('reportes.pagos_pedidos.confirmar_admin.exhibicion', { cierre: cierreId, item: itemId }), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        credentials: 'same-origin',
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(data.message || 'No se pudo confirmar la exhibición.');
    }
    return data;
}

export async function reportarErrorAdmin({ cierreId, itemId, comentario, evidencia }) {
    const form = new FormData();
    form.append('comentario', comentario);
    form.append('evidencia', evidencia);

    const url = itemId
        ? route('reportes.pagos_pedidos.reportar_error_admin.exhibicion', { cierre: cierreId, item: itemId })
        : route('reportes.pagos_pedidos.reportar_error_admin.pedido', { cierre: cierreId });

    const res = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: form,
        credentials: 'same-origin',
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const msg = data.errors
            ? Object.values(data.errors).flat().join(' ')
            : (data.message || 'No se pudo reportar el error.');
        throw new Error(msg);
    }
    return data;
}

const CAMPOS_ADMIN_CIERRE = [
    'admin_resumen',
    'admin_resumen_label',
    'admin_exhibiciones_revisadas',
    'admin_exhibiciones_total',
    'admin_exhibiciones_pendientes',
    'admin_revisado_por',
    'admin_revisado_at',
    'admin_pedido_error_comentario',
    'admin_pedido_error_reportado_at',
    'admin_pedido_error_reportado_por',
    'admin_pedido_error_evidencia_url',
    'admin_pedido_error_evidencia_nombre',
];

/** Campos admin del listado de pedidos para sincronizar cierre cacheado. */
export function camposAdminDesdePedido(pedido) {
    if (!pedido) return {};
    return CAMPOS_ADMIN_CIERRE.reduce((acc, key) => {
        if (pedido[key] !== undefined) {
            acc[key] = pedido[key];
        }
        return acc;
    }, {});
}

/** Fusiona respuesta admin en lista de exhibiciones (vouchers / tabla plana). */
export function mergeAdminEnExhibiciones(exhibiciones, respuesta) {
    if (!exhibiciones?.length || !respuesta) return exhibiciones ?? [];
    if (respuesta.item) {
        const itemId = respuesta.item.id;
        return exhibiciones.map((ex) => (ex.id === itemId ? { ...ex, ...respuesta.item } : ex));
    }
    if (respuesta.items?.length) {
        const byId = Object.fromEntries(respuesta.items.map((it) => [it.id, it]));
        return exhibiciones.map((ex) => ({ ...ex, ...(byId[ex.id] || {}) }));
    }
    return exhibiciones;
}

/** Fusiona respuesta admin en detalle cacheado. */
export function mergeAdminEnDetalle(detalle, respuesta) {
    if (!detalle) return detalle;
    const next = {
        ...detalle,
        cierre: { ...detalle.cierre, ...(respuesta.cierre || {}) },
    };
    if (respuesta.item && next.exhibiciones) {
        const itemId = respuesta.item.id;
        next.exhibiciones = next.exhibiciones.map((ex) => (
            ex.id === itemId ? { ...ex, ...respuesta.item } : ex
        ));
    }
    if (respuesta.items?.length && next.exhibiciones) {
        const byId = Object.fromEntries(respuesta.items.map((it) => [it.id, it]));
        next.exhibiciones = next.exhibiciones.map((ex) => ({ ...ex, ...(byId[ex.id] || {}) }));
    }
    return next;
}
