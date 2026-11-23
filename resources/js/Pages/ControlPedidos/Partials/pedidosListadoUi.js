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
    const municipal = pedido.solicitud_preparacion?.modalidad_entrega === 'municipal';
    const consulta = pedido.solicitud_preparacion?.consulta_municipal;
    const revisionMunicipal = pedido.solicitud_preparacion?.revision_disponibilidad;
    if (municipal && consulta?.enviada_at) {
        const pendiente = revisionMunicipal?.actualizacion_pendiente || revisionMunicipal?.vigente === false
            || revisionMunicipal?.sustituida_at || revisionMunicipal?.aceptacion_vigente === false;
        items[0].label = consulta.estado === 'cerrada' ? 'Consulta cerrada'
            : pendiente || consulta.estado === 'excepciones_pendientes' ? 'Excepciones pendientes'
            : consulta.estado === 'respondida' ? 'Consulta respondida'
            : consulta.area === 'tienda' ? 'Consulta pendiente: Tienda' : 'Consulta pendiente: CEDIS';
    }


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
    // En un envío municipal el transporte diferido se regulariza en terminal: no es un pendiente de la vendedora.
    const regularizacionMunicipal = municipal && pedido.estatus_envio === 'pendiente_regularizacion';
    if (badgeEnvio && !regularizacionMunicipal) items.push({ key: 'envio', ...badgeEnvio });

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

export const GRUPOS_ACCIONES_PEDIDO = {
    consulta: 'Consulta y seguimiento',
    operaciones: 'Operaciones',
    administracion: 'Administración',
};

const GRUPO_POR_CLAVE = {
    ver: 'consulta',
    bitacora: 'consulta',
    auditoria: 'consulta',
    guia: 'operaciones',
    cargar: 'operaciones',
    completar: 'operaciones',
    anexar: 'operaciones',
    'traslado-tienda': 'operaciones',
    'seguimiento-traslado': 'operaciones',
    'modalidad-tienda': 'operaciones',
    'conversion-modalidad': 'operaciones',
    'piezas-adicionales': 'operaciones',
    'anexo-extraordinario': 'operaciones',
    'cambio-a-tienda': 'operaciones',
    'saldo-envio': 'operaciones',
    editar: 'operaciones',
    espera: 'operaciones',
    cancelar: 'administracion',
    'eliminar-borrador': 'administracion',
    'eliminar-registro': 'administracion',
    restaurar: 'administracion',
};

const CLAVE_GRUPO_POR_ETIQUETA = Object.fromEntries(
    Object.entries(GRUPOS_ACCIONES_PEDIDO).map(([clave, etiqueta]) => [etiqueta, clave])
);

export function grupoAccionPedido(accion) {
    if (GRUPO_POR_CLAVE[accion?.key]) return GRUPO_POR_CLAVE[accion.key];
    if (GRUPOS_ACCIONES_PEDIDO[accion?.grupo]) return accion.grupo;
    return CLAVE_GRUPO_POR_ETIQUETA[accion?.grupo] || 'operaciones';
}

export function agruparAccionesPedido(acciones) {
    const orden = ['consulta', 'operaciones', 'administracion'];
    const buckets = { consulta: [], operaciones: [], administracion: [] };
    for (const accion of acciones || []) {
        (buckets[grupoAccionPedido(accion)] || buckets.operaciones).push(accion);
    }
    return orden.filter((key) => buckets[key].length).map((key) => ({
        key,
        label: GRUPOS_ACCIONES_PEDIDO[key],
        acciones: buckets[key],
    }));
}

export function flagsConversionPedido(pedido) {
    return pedido?.conversion_modalidad || {};
}

export function puedeCambiarModalidadConversion(pedido) {
    const flags = flagsConversionPedido(pedido);
    return Boolean(flags.puede_iniciar || flags.puede_continuar);
}

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
    onCambiarModalidad,
    onAgregarPiezas,
    onCambioATienda,
    onSaldoEnvio,
    onVerGuia,
    onAnexarEnvio,
    onCompletarEnvio,
    onCargarGuia,
    onEsperaPago,
    onAnexoExtraordinario,
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
    // «Completar envío» pide dirección, guía y peso: es del resguardo nacional. El municipal solicita su continuación en su propio formulario.
    if (puedeMutar && pedido.solicitud_preparacion?.modalidad_entrega !== 'municipal' && puedeCompletarEnvioResguardo(pedido) && (can('control_pedidos.crear') || can('control_pedidos.editar')) && onCompletarEnvio) {
        out.push({ key: 'completar', label: 'Completar envío', tone: 'teal', onClick: () => onCompletarEnvio(pedido) });
    }
    const consultaMunicipalAbierta = pedido.solicitud_preparacion?.modalidad_entrega === 'municipal'
        && pedido.solicitud_preparacion?.consulta_municipal?.estado !== 'cerrada';
    if (!consultaMunicipalAbierta && puedeMutar && puedeAnexarPagoEnvio(pedido) && (can('control_pedidos.crear') || can('control_pedidos.auditar')) && onAnexarEnvio) {
        out.push({ key: 'anexar', label: 'Anexar pago', tone: 'amber', onClick: () => onAnexarEnvio(pedido) });
    }
    if (can('control_pedidos.ver_detalle') && onVer) {
        out.push({ key: 'ver', label: 'Ver', onClick: () => onVer(pedido) });
    }
    const solicitudTienda = pedido.solicitud_preparacion || pedido.solicitud_preparacion_json || {};
    if (!esPapelera && solicitudTienda.modalidad_entrega === 'tienda' && !pedido.cancelado_at && pedido.estatus?.fase_ciclo !== 'ENTREGADO') {
        const traslado = solicitudTienda.segundo_traslado;
        const trasladoActivo = Boolean(traslado && !traslado.recepcion_confirmada && ['pendiente', 'autorizada'].includes(traslado.estado));
        const enCustodiaOperable = pedido.estatus_sucursal === 'en_custodia' && pedido.puede_mutar && can('control_pedidos.crear');
        const convertir = puedeCambiarModalidadConversion(pedido);
        if (onMovimientoTienda && enCustodiaOperable && !trasladoActivo && !solicitudTienda.cambio_modalidad_tienda) {
            out.push({ key: 'traslado-tienda', label: 'Solicitar traslado', onClick: () => onMovimientoTienda(pedido, 'traslado') });
        }
        if (convertir && (onCambiarModalidad || onMovimientoTienda)) {
            out.push({
                key: 'conversion-modalidad',
                label: 'Cambiar modalidad',
                onClick: () => (onCambiarModalidad
                    ? onCambiarModalidad(pedido)
                    : onMovimientoTienda(pedido, 'modalidad')),
            });
        } else if (onMovimientoTienda && enCustodiaOperable && !trasladoActivo && !solicitudTienda.cambio_modalidad_tienda) {
            out.push({ key: 'modalidad-tienda', label: 'Cambiar modalidad', onClick: () => onMovimientoTienda(pedido, 'modalidad') });
        }
        if (onMovimientoTienda && (traslado || (solicitudTienda.cambio_modalidad_tienda && !convertir))) {
            out.push({ key: 'seguimiento-traslado', label: 'Seguimiento de traslado y modalidad', onClick: () => onMovimientoTienda(pedido, 'traslado') });
        }
    }
    // Envío municipal con remisión: piezas adicionales con pago y remisión propios, mientras no salga a terminal.
    const piezasAdicionales = pedido.piezas_adicionales;
    if (onAgregarPiezas && piezasAdicionales && (piezasAdicionales.puede_agregar || piezasAdicionales.items?.length > 0)) {
        out.push({
            key: 'piezas-adicionales',
            label: piezasAdicionales.items?.length > 0 ? 'Piezas adicionales' : 'Agregar piezas',
            onClick: () => onAgregarPiezas(pedido),
        });
    }
    // Nacional pagado o remisionado: el cliente agrega piezas y el anexo viaja como minipedido aparte (antes o después de salir).
    const solicitudNacional = pedido.solicitud_preparacion || pedido.solicitud_preparacion_json || {};
    if (onAnexoExtraordinario
        && solicitudNacional.modalidad_entrega === 'nacional'
        && solicitudNacional.captura_nacional === 'compartida'
        && !solicitudNacional.complemento_nacional
        && (pedido.numero_remision || pedido.pago_validado_at)
        && !pedido.cancelado_at
        && pedido.estatus?.fase_ciclo !== 'CANCELADO'
        && can('control_pedidos.crear')) {
        out.push({ key: 'anexo-extraordinario', label: 'Anexo extraordinario', onClick: () => onAnexoExtraordinario(pedido) });
    }
    // Envío municipal con compra registrada: Ventas pide que el cliente recoja en sucursal, o consulta el estado de la solicitud.
    const cambioATienda = pedido.cambio_a_tienda;
    if (onCambioATienda && cambioATienda && (cambioATienda.puede_solicitar || cambioATienda.estado)) {
        out.push({
            key: 'cambio-a-tienda',
            label: cambioATienda.estado ? 'Recolección en sucursal' : 'Cambiar a recolección en sucursal',
            onClick: () => onCambioATienda(pedido),
        });
    }
    // Administración reconoce el transporte no utilizado de un envío que pasó a recolección en sucursal.
    if (onSaldoEnvio && cambioATienda?.puede_autorizar_saldo) {
        out.push({ key: 'saldo-envio', label: 'Autorizar saldo de envío', tone: 'teal', onClick: () => onSaldoEnvio(pedido) });
    }
    if (onBitacora) {
        out.push({ key: 'bitacora', label: 'Bitácora', tone: 'purple', onClick: () => onBitacora(pedido) });
    }
    if (puedeEditar?.(pedido) && onEditar) {
        out.push({
            key: 'editar',
            label: pedido.puede_continuar_nacional || estadoConsultaPedido(pedido) === 'respondida' ? 'Continuar' : 'Editar',
            onClick: () => onEditar(pedido),
        });
    }
    // Municipal en resguardo: Ventas comunica la liberación tras la decisión del cliente (AC-11) desde su formulario.
    if (!puedeEditar?.(pedido) && onEditar && pedido.resguardo_envio?.capacidades?.puede_solicitar_continuacion) {
        out.push({ key: 'continuacion', label: 'Solicitar continuación', tone: 'teal', onClick: () => onEditar(pedido) });
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

    return out.map((accion) => ({
        ...accion,
        grupo: GRUPOS_ACCIONES_PEDIDO[GRUPO_POR_CLAVE[accion.key]] || GRUPOS_ACCIONES_PEDIDO.operaciones,
    }));
}

export function elegirAccionPrimaria(acciones) {
    if (!acciones.length) return null;
    for (const key of ORDEN_PRIMARIA) {
        const found = acciones.find((a) => a.key === key);
        if (found) return found;
    }
    return acciones[0];
}
