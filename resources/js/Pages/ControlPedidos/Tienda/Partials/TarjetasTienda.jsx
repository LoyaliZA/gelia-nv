import React from 'react';
import { Link, router } from '@inertiajs/react';
import {
    Clock, Package, Eye, Truck, AlertTriangle, CheckCircle2, User,
} from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { BTN_PRIMARY, formatearFechaNegocio } from '../../Partials/pedidosBmaStyles';
import AvisoOperativoPedido from '../../Partials/AvisoOperativoPedido';
import BotonAccionCubico from '../../Partials/BotonAccionCubico';
import {
    avisoParaTarea,
    claseBadgeEstadoTienda,
    fmtRelativo,
    fmtVencimiento,
} from './tiendaUi';

export function TarjetaTareaVacia({ hayFiltrosActivos, onLimpiarFiltros }) {
    return (
        <div className={`${geliaCardClass()} p-10 md:p-16 text-center space-y-4`}>
            <Package className="w-10 h-10 mx-auto theme-text-muted opacity-60" aria-hidden />
            <p className="text-sm theme-text-muted font-semibold m-0 max-w-md mx-auto">
                No hay tareas en esta bandeja. Prueba otra bandeja o ajusta los filtros.
            </p>
            {hayFiltrosActivos && onLimpiarFiltros && (
                <button
                    type="button"
                    onClick={onLimpiarFiltros}
                    className={`${BTN_PRIMARY} inline-flex items-center justify-center gap-2 text-xs outline-none py-3 px-6 min-h-[44px]`}
                >
                    Limpiar filtros y volver a pendientes
                </button>
            )}
        </div>
    );
}

export function TarjetaTarea({ tarea: t, puedeTomar, compacto = false }) {
    const venc = fmtVencimiento(t.fecha_limite);
    const badge = claseBadgeEstadoTienda(t.estado, t.estado_label);
    const aviso = avisoParaTarea(t, venc);
    const folio = t.pedido?.folio_visible || t.pedido?.folio_remision || t.pedido?.folio || `#${t.pedido?.id || t.id}`;
    const showUrl = route('control_pedidos.tienda.show', t.id);
    const ringAlerta = ['CON_INCIDENCIA', 'RECHAZADA_CEDIS'].includes(t.estado)
        || Boolean(venc?.urgente);

    const ctaPrincipal = () => {
        if (t.estado === 'PENDIENTE' && puedeTomar) {
            return (
                <button
                    type="button"
                    onClick={() => router.post(route('control_pedidos.tienda.tomar', t.id), { version: t.version })}
                    className={`${BTN_PRIMARY} w-full flex items-center justify-center gap-2 text-xs outline-none py-3 min-h-[44px]`}
                >
                    <Package className="w-4 h-4" /> Atender solicitud
                </button>
            );
        }
        if (t.estado === 'LISTA_PARA_TRASLADO') {
            return (
                <Link
                    href={showUrl}
                    className={`${BTN_PRIMARY} w-full flex items-center justify-center gap-2 text-xs outline-none py-3 min-h-[44px]`}
                >
                    <Truck className="w-4 h-4" /> Confirmar salida
                </Link>
            );
        }
        if (t.estado === 'LISTA_PARA_CARATULA') {
            return (
                <Link
                    href={showUrl}
                    className={`${BTN_PRIMARY} w-full flex items-center justify-center gap-2 text-xs outline-none py-3 min-h-[44px]`}
                >
                    <Package className="w-4 h-4" /> Generar carátula
                </Link>
            );
        }
        if (t.estado === 'EN_ATENCION') {
            return (
                <Link
                    href={showUrl}
                    className={`${BTN_PRIMARY} w-full flex items-center justify-center gap-2 text-xs outline-none py-3 min-h-[44px]`}
                >
                    <CheckCircle2 className="w-4 h-4" /> Continuar respuesta
                </Link>
            );
        }
        return null;
    };

    return (
        <article
            className={`${geliaCardClass()} p-4 space-y-3 transition-shadow duration-200 hover:shadow-md ${
                ringAlerta ? 'ring-1 ring-[color-mix(in_srgb,var(--color-peligro)_35%,transparent)]' : ''
            }`}
        >
            {!compacto && t.modalidad?.nombre && (
                <p
                    className="text-xs font-semibold text-center py-2 px-3 rounded-xl bg-[color-mix(in_srgb,var(--color-primario)_12%,transparent)] m-0 theme-text-primario"
                >
                    {t.modalidad.nombre}
                </p>
            )}

            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-base font-bold theme-text-main m-0 truncate" title={folio}>
                        {folio}
                    </p>
                    {compacto && t.modalidad?.nombre && (
                        <p className="text-xs theme-text-muted font-medium m-0 mt-0.5 truncate">{t.modalidad.nombre}</p>
                    )}
                    {t.solicitada_at && (
                        <p className="text-xs theme-text-muted font-medium mt-1 m-0">
                            {formatearFechaNegocio?.(t.solicitada_at) || new Date(t.solicitada_at).toLocaleDateString('es-MX')}
                            {' · '}
                            {fmtRelativo(t.solicitada_at)}
                        </p>
                    )}
                    {(t.pedido?.prioridad_md || t.pedido?.origen_solicitud) && (
                        <p className="text-xs font-medium theme-text-muted m-0 mt-0.5">
                            {t.pedido.prioridad_md ? 'Mismo día' : ''}
                            {t.pedido.prioridad_md && t.pedido.origen_solicitud ? ' · ' : ''}
                            {t.pedido.origen_solicitud ? t.pedido.origen_solicitud.replace('_', ' ') : ''}
                        </p>
                    )}
                    {t.responsable?.name && (
                        <p className="text-xs theme-text-muted font-medium m-0 mt-1 inline-flex items-center gap-1">
                            <User className="w-3 h-3" /> {t.responsable.name}
                        </p>
                    )}
                </div>
                <div className="flex flex-col items-end gap-1.5 shrink-0 max-w-[50%]">
                    <span className={badge.className}>{badge.label}</span>
                    {t.modalidad?.es_transferencia && (
                        <span className="gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--aviso text-[10px] font-semibold">
                            Transferencia
                        </span>
                    )}
                    {t.requiere_traslado_cedis && (
                        <span className="gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--info text-[10px] font-semibold">
                            Traslado CEDIS
                        </span>
                    )}
                </div>
            </div>

            {aviso && !compacto && (
                <AvisoOperativoPedido label={aviso.label} tono={aviso.tono} icon={aviso.icon}>
                    {aviso.texto}
                </AvisoOperativoPedido>
            )}

            <div className="grid grid-cols-2 gap-2 text-xs font-medium theme-text-muted">
                <div>
                    <p className="text-[11px] font-semibold m-0 theme-text-muted">Cliente</p>
                    <p className="text-sm theme-text-main m-0 mt-0.5 normal-case truncate" title={t.pedido?.cliente_nombre}>
                        {t.pedido?.cliente_nombre || '—'}
                    </p>
                </div>
                <div>
                    <p className="text-[11px] font-semibold m-0 theme-text-muted">Almacén</p>
                    <p className="text-sm theme-text-main m-0 mt-0.5 normal-case truncate">
                        {t.almacen?.nombre || '—'}
                    </p>
                </div>
                <div>
                    <p className="text-[11px] font-semibold m-0 theme-text-muted">Piezas</p>
                    <p className="text-sm theme-text-main m-0 mt-0.5 tabular-nums">
                        {t.piezas_solicitadas ?? t.pedido?.cantidad_piezas ?? 0}
                    </p>
                </div>
                <div>
                    <p className="text-[11px] font-semibold m-0 theme-text-muted">Antigüedad</p>
                    <p className="text-sm theme-text-main m-0 mt-0.5 inline-flex items-center gap-1">
                        <Clock className="w-3 h-3" /> {fmtRelativo(t.solicitada_at)}
                    </p>
                </div>
            </div>

            {t.entrega_municipal?.municipio_destino && (
                <div className="rounded-xl border theme-border bg-[color-mix(in_srgb,var(--color-info)_8%,transparent)] p-2.5">
                    <p className="text-[11px] font-semibold theme-text-muted m-0">Destino</p>
                    <p className="text-xs font-bold theme-text-main m-0 mt-0.5">
                        {t.entrega_municipal.municipio_destino}
                        {t.entrega_municipal.destinatario_nombre ? ` · ${t.entrega_municipal.destinatario_nombre}` : ''}
                    </p>
                </div>
            )}

            {t.solicitud_traspaso?.folio && (
                <div className="rounded-xl border theme-border bg-[color-mix(in_srgb,var(--color-info)_6%,transparent)] p-2.5">
                    <p className="text-[11px] font-semibold theme-text-muted m-0">Traspaso</p>
                    <p className="text-xs font-bold theme-text-main m-0 mt-0.5">
                        {t.solicitud_traspaso.folio}
                        {t.solicitud_traspaso.estado ? ` · ${t.solicitud_traspaso.estado}` : ''}
                    </p>
                </div>
            )}

            {venc && !aviso?.label?.includes('Plazo') && (
                <p className={`text-xs font-semibold m-0 ${venc.urgente ? 'text-primario' : 'theme-text-muted'}`}>
                    {venc.texto}
                </p>
            )}

            <div className="pt-2 border-t theme-border space-y-2">
                {ctaPrincipal()}
                <BotonAccionCubico
                    icon={Eye}
                    label="Ver detalle"
                    onClick={() => router.visit(showUrl)}
                    conLabel
                    className="w-full"
                />
            </div>
        </article>
    );
}

/** @deprecated Usar ListadoTienda en Index; se mantiene por compatibilidad. */
export default function TarjetasTienda({ tareas = [], auth, hayFiltrosActivos = false, onLimpiarFiltros }) {
    const permisos = auth?.user?.permissions || [];
    const puedeTomar = permisos.includes('control_pedidos.tienda.tomar')
        || auth?.user?.roles?.includes('Super Admin');
    const items = tareas?.data || [];

    if (!items.length) {
        return <TarjetaTareaVacia hayFiltrosActivos={hayFiltrosActivos} onLimpiarFiltros={onLimpiarFiltros} />;
    }

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 md:gap-4">
            {items.map((t) => (
                <TarjetaTarea key={t.id} tarea={t} puedeTomar={puedeTomar} />
            ))}
        </div>
    );
}
