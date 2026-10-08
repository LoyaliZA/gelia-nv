import React, { useEffect, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import {
    LayoutGrid, List, Package, Truck, CheckCircle2, Eye, Clock,
} from 'lucide-react';
import GeliaPaginacion from '../../../../Components/GeliaPaginacion';
import { geliaCardClass, geliaToggleBtnClass, THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import { BTN_PRIMARY } from '../../Partials/pedidosBmaStyles';
import { TarjetaTareaVacia, TarjetaTarea } from './TarjetasTienda';
import {
    STORAGE_VISTA_LISTADO,
    avisoParaTarea,
    claseBadgeEstadoTienda,
    fmtRelativo,
    fmtVencimiento,
} from './tiendaUi';

function CtaTabla({ t, puedeTomar, showUrl }) {
    if (t.estado === 'PENDIENTE' && puedeTomar) {
        return (
            <button
                type="button"
                onClick={() => router.post(route('control_pedidos.tienda.tomar', t.id), { version: t.version })}
                className={`${THEME_BTN_PRIMARY} inline-flex items-center gap-1.5 text-xs px-3 py-2 min-h-[36px]`}
            >
                <Package className="w-3.5 h-3.5" /> Atender
            </button>
        );
    }
    if (t.estado === 'LISTA_PARA_TRASLADO') {
        return (
            <Link href={showUrl} className={`${BTN_PRIMARY} inline-flex items-center gap-1.5 text-xs px-3 py-2 min-h-[36px]`}>
                <Truck className="w-3.5 h-3.5" /> Salida
            </Link>
        );
    }
    if (t.estado === 'LISTA_PARA_CARATULA') {
        return (
            <Link href={showUrl} className={`${BTN_PRIMARY} inline-flex items-center gap-1.5 text-xs px-3 py-2 min-h-[36px]`}>
                <Package className="w-3.5 h-3.5" /> Carátula
            </Link>
        );
    }
    if (t.estado === 'EN_ATENCION') {
        return (
            <Link href={showUrl} className={`${BTN_PRIMARY} inline-flex items-center gap-1.5 text-xs px-3 py-2 min-h-[36px]`}>
                <CheckCircle2 className="w-3.5 h-3.5" /> Continuar
            </Link>
        );
    }
    return (
        <Link
            href={showUrl}
            className="inline-flex items-center gap-1 text-xs font-semibold theme-text-primario hover:underline min-h-[36px] px-2"
        >
            <Eye className="w-3.5 h-3.5" /> Ver
        </Link>
    );
}

function TablaTareas({ items, puedeTomar }) {
    return (
        <div className="gelia-tienda-op-table-wrap">
            <table className="gelia-tienda-op-table">
                <thead>
                    <tr>
                        <th scope="col">Folio</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Cliente</th>
                        <th scope="col">Almacén</th>
                        <th scope="col">Piezas</th>
                        <th scope="col">Señales</th>
                        <th scope="col">Tiempo</th>
                        <th scope="col" className="text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map((t) => {
                        const venc = fmtVencimiento(t.fecha_limite);
                        const badge = claseBadgeEstadoTienda(t.estado, t.estado_label);
                        const folio = t.pedido?.folio_visible || t.pedido?.folio_remision || t.pedido?.folio || `#${t.pedido?.id || t.id}`;
                        const showUrl = route('control_pedidos.tienda.show', t.id);
                        const alerta = ['CON_INCIDENCIA', 'RECHAZADA_CEDIS'].includes(t.estado) || Boolean(venc?.urgente);
                        const aviso = avisoParaTarea(t, venc);
                        return (
                            <tr key={t.id} data-alerta={alerta}>
                                <td>
                                    <Link href={showUrl} className="gelia-tienda-op-fila-link block truncate max-w-[10rem]" title={folio}>
                                        {folio}
                                    </Link>
                                    {t.modalidad?.nombre && (
                                        <span className="block text-[11px] theme-text-muted font-medium truncate max-w-[12rem] mt-0.5">
                                            {t.modalidad.nombre}
                                        </span>
                                    )}
                                </td>
                                <td>
                                    <span className={badge.className}>{badge.label}</span>
                                </td>
                                <td className="max-w-[9rem]">
                                    <span className="block truncate font-medium theme-text-main" title={t.pedido?.cliente_nombre}>
                                        {t.pedido?.cliente_nombre || '—'}
                                    </span>
                                </td>
                                <td className="max-w-[8rem]">
                                    <span className="block truncate theme-text-muted">{t.almacen?.nombre || '—'}</span>
                                </td>
                                <td className="tabular-nums font-semibold theme-text-main">
                                    {t.piezas_solicitadas ?? t.pedido?.cantidad_piezas ?? 0}
                                </td>
                                <td>
                                    <div className="flex flex-wrap gap-1 max-w-[11rem]">
                                        {t.pedido?.prioridad_md && (
                                            <span className="gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--aviso text-[10px] font-semibold">
                                                Mismo día
                                            </span>
                                        )}
                                        {t.requiere_traslado_cedis && (
                                            <span className="gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--info text-[10px] font-semibold">
                                                CEDIS
                                            </span>
                                        )}
                                        {t.modalidad?.es_transferencia && (
                                            <span className="gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--aviso text-[10px] font-semibold">
                                                Transferencia
                                            </span>
                                        )}
                                    </div>
                                    {aviso && (
                                        <p className="text-[10px] theme-text-muted m-0 mt-1 line-clamp-1" title={aviso.texto}>
                                            {aviso.texto}
                                        </p>
                                    )}
                                </td>
                                <td className="whitespace-nowrap">
                                    <span className="inline-flex items-center gap-1 text-xs theme-text-muted">
                                        <Clock className="w-3 h-3 shrink-0" />
                                        {fmtRelativo(t.solicitada_at)}
                                    </span>
                                    {venc && (
                                        <span className={`block text-[10px] font-semibold mt-0.5 ${venc.urgente ? 'text-primario' : 'theme-text-muted'}`}>
                                            {venc.texto}
                                        </span>
                                    )}
                                </td>
                                <td className="text-right whitespace-nowrap">
                                    <CtaTabla t={t} puedeTomar={puedeTomar} showUrl={showUrl} />
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

export default function ListadoTienda({
    tareas = [],
    auth,
    hayFiltrosActivos = false,
    onLimpiarFiltros,
    onIrAPagina,
    tabLabel = '',
    buscando = false,
}) {
    const permisos = auth?.user?.permissions || [];
    const puedeTomar = permisos.includes('control_pedidos.tienda.tomar')
        || auth?.user?.roles?.includes('Super Admin');
    const items = tareas?.data || [];
    const total = tareas?.total ?? items.length;

    const [vista, setVista] = useState('tabla');

    useEffect(() => {
        try {
            const guardada = localStorage.getItem(STORAGE_VISTA_LISTADO);
            if (guardada === 'tarjetas' || guardada === 'tabla') {
                setVista(guardada);
            }
        } catch {
            /* ignore */
        }
    }, []);

    const cambiarVista = (modo) => {
        setVista(modo);
        try {
            localStorage.setItem(STORAGE_VISTA_LISTADO, modo);
        } catch {
            /* ignore */
        }
    };

    if (!items.length) {
        return (
            <TarjetaTareaVacia
                hayFiltrosActivos={hayFiltrosActivos}
                onLimpiarFiltros={onLimpiarFiltros}
            />
        );
    }

    const usarTabla = vista === 'tabla';

    return (
        <div className={`${geliaCardClass()} gelia-tienda-op-workspace p-4 md:p-5 space-y-4`}>
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-sm font-bold theme-text-main m-0 tabular-nums">
                        {total} pedido{total === 1 ? '' : 's'}
                        {tabLabel ? <span className="theme-text-muted font-semibold"> · {tabLabel}</span> : null}
                    </p>
                    {buscando && (
                        <p className="text-xs theme-text-muted m-0 mt-0.5" aria-live="polite">
                            Actualizando listado…
                        </p>
                    )}
                </div>
                <div className="flex items-center gap-1 shrink-0" role="group" aria-label="Vista del listado">
                    <button
                        type="button"
                        className={geliaToggleBtnClass(usarTabla)}
                        onClick={() => cambiarVista('tabla')}
                        aria-pressed={usarTabla}
                    >
                        <List className="w-3.5 h-3.5 inline mr-1" aria-hidden />
                        Tabla
                    </button>
                    <button
                        type="button"
                        className={geliaToggleBtnClass(!usarTabla)}
                        onClick={() => cambiarVista('tarjetas')}
                        aria-pressed={!usarTabla}
                    >
                        <LayoutGrid className="w-3.5 h-3.5 inline mr-1" aria-hidden />
                        Tarjetas
                    </button>
                </div>
            </div>

            {usarTabla ? (
                <div className="hidden md:block">
                    <TablaTareas items={items} puedeTomar={puedeTomar} />
                </div>
            ) : null}

            <div className={usarTabla ? 'md:hidden' : ''}>
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 md:gap-4">
                    {items.map((t) => (
                        <TarjetaTarea key={t.id} tarea={t} puedeTomar={puedeTomar} compacto />
                    ))}
                </div>
            </div>

            {onIrAPagina && tareas && (
                <div className="pt-2 border-t theme-border">
                    <GeliaPaginacion paginator={tareas} onIrAPagina={onIrAPagina} embedded className="!border-0 !p-0" />
                </div>
            )}
        </div>
    );
}
