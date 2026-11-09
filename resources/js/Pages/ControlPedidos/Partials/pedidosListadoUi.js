import {
    badgeEstatusPedido,
    pedidoRequiereLogistica,
    badgeEstatusEnvio,
    badgeGuiaLista,
    badgeObservacionesCedis,
    badgeSinExistencias,
    mostrarBadgeSinExistencias,
    badgesRetrasoSla,
    badgeAuditoriaRevision,
    esFasePreVenta,
    estadoConsultaPedido,
    tieneGuiaLista,
    tieneGuiaPdfDisponible,
    puedeAnexarPagoEnvio,
    puedeCompletarEnvioResguardo,
    puedeCargarGuiaCliente,
    guiaPdfDe,
} from './pedidosBmaStyles';

export const MAX_BADGES_LISTADO = 2;

export function badgesListaPedido(pedido) {
    const badge = badgeEstatusPedido(pedido.estatus, {
        pedido,
        esResguardo: pedido.es_resguardo,
        requiereLogistica: pedidoRequiereLogistica(pedido),
    });
    const items = [{ key: 'estatus', className: badge.className, style: badge.style, label: badge.label }];

    const traslado = (pedido.solicitud_preparacion || pedido.solicitud_preparacion_json)?.segundo_traslado;
    if (traslado && !traslado.recepcion_confirmada && ['pendiente', 'autorizada'].includes(traslado.estado)) {
        items.unshift({ key: 'traslado-sucursal', className: 'rounded-full border theme-border theme-element px-3 py-1 text-xs font-bold theme-text-main', label: traslado.estado === 'pendiente' ? 'Traslado por autorizar' : traslado.movimiento_estado === 'en_camino' ? 'En traslado' : 'Pendiente de salida de sucursal' });
    }

    const fase = pedido.estatus?.fase_ciclo;
    const badgeEnvio = badgeEstatusEnvio(pedido.estatus_envio, {
        pedido,
        faseCiclo: fase,
        respondeTienda: Boolean(pedido.usa_preparacion_tienda),
    });
    if (badgeEnvio) items.push({ key: 'envio', ...badgeEnvio });

    const badgeRevision = badgeAuditoriaRevision(pedido);
    if (badgeRevision) items.push({ key: 'revision', ...badgeRevision });

    if (pedido.tiene_observaciones_fisicas && esFasePreVenta(fase)) {
        const obs = badgeObservacionesCedis({ respondeTienda: Boolean(pedido.usa_preparacion_tienda) });
        items.push({ key: 'obs', className: obs.className, label: obs.label });
    }
    if (mostrarBadgeSinExistencias(pedido)) {
        const sinEx = badgeSinExistencias();
        items.push({ key: 'sin-ex', className: sinEx.className, label: sinEx.label });
    }
    badgesRetrasoSla(pedido).forEach((b) => items.push({ key: b.label, ...b }));
    if (tieneGuiaLista(pedido)) {
        const guia = badgeGuiaLista();
        items.push({ key: 'guia-lista', className: guia.className, label: guia.label });
    }

    return items;
}

export function tituloOverflowBadges(items) {
    const extra = items.slice(MAX_BADGES_LISTADO);
    if (extra.length === 0) return '';
    return extra.map((b) => b.label).join(' · ');
}

const ORDEN_PRIMARIA = [
    'cargar',
    'completar',
    'anexar',
    'editar',
    'restaurar',
    'ver',
];

/**
 * @returns {Array<{ key: string, label: string, tone?: string, onClick: () => void }>}
 */
export function accionesPedidoLista({
    pedido,
    can,
    esPapelera,
    puedeEditar,
    puedeEliminarBorrador,
    puedeEliminarRegistro,
    puedeCancelar,
    onVer,
    onEditar,
    onEliminar,
    onEliminarRegistro,
    onRestaurar,
    onVerAuditoria,
    onCancelar,
    onBitacora,
    onMovimientoTienda,
    onVerGuia,
    onAnexarEnvio,
    onCompletarEnvio,
    onCargarGuia,
    onEsperaPago,
}) {
    const out = [];

    if (esPapelera) {
        if (can('control_pedidos.ver_detalle') && onVer) {
            out.push({ key: 'ver', label: 'Ver', onClick: () => onVer(pedido) });
        }
        if (can('control_pedidos.eliminados') && onRestaurar) {
            out.push({ key: 'restaurar', label: 'Restaurar', tone: 'teal', onClick: () => onRestaurar(pedido) });
        }
        if (onVerAuditoria) {
            out.push({ key: 'auditoria', label: 'Auditoría', tone: 'purple', onClick: () => onVerAuditoria(pedido) });
        }
        return out;
    }

    const puedeMutar = Boolean(pedido.puede_editar ?? pedido.puede_mutar);
    const guiaPdf = tieneGuiaPdfDisponible(pedido) && onVerGuia ? guiaPdfDe(pedido) : null;

    if (guiaPdf) {
        out.push({ key: 'guia', label: 'Guía PDF', onClick: () => onVerGuia(guiaPdf) });
    }
    if (puedeMutar && puedeCargarGuiaCliente(pedido) && (can('control_pedidos.crear') || can('control_pedidos.editar')) && onCargarGuia) {
        out.push({ key: 'cargar', label: 'Cargar guía', tone: 'fuchsia', onClick: () => onCargarGuia(pedido) });
    }
    if (puedeMutar && puedeCompletarEnvioResguardo(pedido) && (can('control_pedidos.crear') || can('control_pedidos.editar')) && onCompletarEnvio) {
        out.push({ key: 'completar', label: 'Completar envío', tone: 'teal', onClick: () => onCompletarEnvio(pedido) });
    }
    if (puedeMutar && puedeAnexarPagoEnvio(pedido) && (can('control_pedidos.crear') || can('control_pedidos.auditar')) && onAnexarEnvio) {
        out.push({ key: 'anexar', label: 'Anexar pago', tone: 'amber', onClick: () => onAnexarEnvio(pedido) });
    }
    if (can('control_pedidos.ver_detalle') && onVer) {
        out.push({ key: 'ver', label: 'Ver', onClick: () => onVer(pedido) });
    }
    const solicitudTienda = pedido.solicitud_preparacion || pedido.solicitud_preparacion_json || {};
    if (!esPapelera && onMovimientoTienda && solicitudTienda.modalidad_entrega === 'tienda' && !pedido.cancelado_at && pedido.estatus?.fase_ciclo !== 'ENTREGADO') {
        if (pedido.estatus_sucursal === 'en_custodia' && pedido.puede_mutar && can('control_pedidos.crear') && (!['pendiente', 'autorizada'].includes(solicitudTienda.segundo_traslado?.estado) || solicitudTienda.segundo_traslado?.recepcion_confirmada) && !solicitudTienda.cambio_modalidad_tienda) {
            out.push({ key: 'traslado-tienda', label: 'Solicitar traslado', onClick: () => onMovimientoTienda(pedido, 'traslado') });
            out.push({ key: 'modalidad-tienda', label: 'Cambiar modalidad', onClick: () => onMovimientoTienda(pedido, 'modalidad') });
        } else if (solicitudTienda.segundo_traslado || solicitudTienda.cambio_modalidad_tienda) {
            out.push({ key: 'traslado-tienda', label: 'Seguimiento de traslado y modalidad', onClick: () => onMovimientoTienda(pedido, 'traslado') });
        }
    }
    if (onBitacora) {
        out.push({ key: 'bitacora', label: 'Bitácora', tone: 'purple', onClick: () => onBitacora(pedido) });
    }
    if (puedeEditar?.(pedido) && onEditar) {
        out.push({
            key: 'editar',
            label: estadoConsultaPedido(pedido) === 'respondida' ? 'Continuar' : 'Editar',
            onClick: () => onEditar(pedido),
        });
    }
    if (pedido.puede_espera_pago && can('control_pedidos.espera_pago') && onEsperaPago) {
        out.push({ key: 'espera', label: 'Esperando pago', tone: 'teal', onClick: () => onEsperaPago(pedido) });
    }
    if ((puedeCancelar?.(pedido) || pedido.cancelacion_operativa) && onCancelar) {
        out.push({
            key: 'cancelar',
            label: pedido.cancelacion_operativa ? 'Seguir cancelación' : 'Cancelar',
            tone: 'warn',
            onClick: () => onCancelar(pedido),
        });
    }
    if (puedeEliminarBorrador?.(pedido) && onEliminar) {
        out.push({ key: 'eliminar-borrador', label: 'Eliminar borrador', tone: 'danger', onClick: () => onEliminar(pedido) });
    }
    if (puedeEliminarRegistro?.(pedido) && onEliminarRegistro) {
        out.push({ key: 'eliminar-registro', label: 'Eliminar registro', tone: 'danger', onClick: () => onEliminarRegistro(pedido) });
    }

    return out;
}

export function elegirAccionPrimaria(acciones) {
    if (!acciones.length) return null;
    for (const key of ORDEN_PRIMARIA) {
        const found = acciones.find((a) => a.key === key);
        if (found) return found;
    }
    return acciones[0];
}
