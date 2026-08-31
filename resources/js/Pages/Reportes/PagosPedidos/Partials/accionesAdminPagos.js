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

/** Fusiona respuesta admin en detalle cacheado. */
export function mergeAdminEnDetalle(detalle, respuesta) {
    if (!detalle) return detalle;
    const next = {
        ...detalle,
        cierre: { ...detalle.cierre, ...(respuesta.cierre || {}) },
    };
    if (respuesta.item && next.exhibiciones) {
        next.exhibiciones = next.exhibiciones.map((ex) => (
            ex.id === respuesta.item.id ? { ...ex, ...respuesta.item } : ex
        ));
    }
    if (respuesta.items?.length && next.exhibiciones) {
        const byId = Object.fromEntries(respuesta.items.map((it) => [it.id, it]));
        next.exhibiciones = next.exhibiciones.map((ex) => ({ ...ex, ...(byId[ex.id] || {}) }));
    }
    return next;
}
