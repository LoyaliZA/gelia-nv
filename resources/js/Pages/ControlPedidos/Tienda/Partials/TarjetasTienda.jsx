import React from 'react';
import { Link, router } from '@inertiajs/react';
import {
    Clock, Package, Eye, Truck, AlertTriangle, CheckCircle2, User,
} from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { BTN_PRIMARY, formatearFechaNegocio } from '../../Partials/pedidosBmaStyles';
import AvisoOperativoPedido from '../../Partials/AvisoOperativoPedido';
import BotonAccionCubico from '../../Partials/BotonAccionCubico';

const BADGE_ESTADO = {
    PENDIENTE: { label: 'Pendiente', className: 'bg-orange-500/15 text-orange-700 dark:text-orange-300 border-orange-500/30' },
    EN_ATENCION: { label: 'En atención', className: 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/30' },
    CON_INCIDENCIA: { label: 'Incidencia', className: 'bg-orange-500/15 text-orange-800 dark:text-orange-200 border-orange-500/40' },
    LISTA_PARA_TRASLADO: { label: 'Lista traslado', className: 'bg-violet-500/15 text-violet-700 dark:text-violet-300 border-violet-500/30' },
    LISTA_PARA_CARATULA: { label: 'Lista carátula', className: 'bg-teal-500/15 text-teal-700 dark:text-teal-300 border-teal-500/30' },
    EN_TRASLADO: { label: 'En traslado', className: 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border-indigo-500/30' },
    RECIBIDA_CEDIS: { label: 'Recibida CEDIS', className: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30' },
    RECHAZADA_CEDIS: { label: 'Rechazada CEDIS', className: 'bg-red-500/15 text-red-700 dark:text-red-300 border-red-500/30' },
    RESPONDIDA: { label: 'Respondida', className: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30' },
    LIBERACION_SOLICITADA: { label: 'Liberación', className: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30' },
    LIBERADA: { label: 'Liberada', className: 'bg-zinc-500/15 theme-text-muted border-zinc-500/20' },
    CANCELADA: { label: 'Cancelada', className: 'bg-zinc-500/10 theme-text-muted border-zinc-500/20' },
};

function fmtRelativo(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    const mins = Math.floor((Date.now() - d.getTime()) / 60000);
    if (mins < 60) return `hace ${Math.max(0, mins)} min`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 48) return `hace ${hrs} h`;
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
}

function fmtVencimiento(iso) {
    if (!iso) return null;
    const d = new Date(iso);
    const diff = d.getTime() - Date.now();
    if (diff < 0) return { texto: 'Vencida', urgente: true };
    const dias = Math.ceil(diff / 86400000);
    return { texto: `Vence en ${dias} día${dias === 1 ? '' : 's'}`, urgente: dias <= 1 };
}

function badgeEstado(estado, estadoLabel) {
    const cfg = BADGE_ESTADO[estado] || {
        label: estadoLabel || estado || '—',
        className: 'theme-element theme-text-muted border theme-border',
    };
    return {
        label: estadoLabel || cfg.label,
        className: `inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border ${cfg.className}`,
    };
}

function avisoParaTarea(t, venc) {
    if (t.estado === 'CON_INCIDENCIA' || t.estado === 'RECHAZADA_CEDIS') {
        return {
            label: t.estado === 'RECHAZADA_CEDIS' ? 'Rechazo CEDIS' : 'Incidencia',
            tono: 'danger',
            icon: AlertTriangle,
            texto: t.motivo_rechazo_cedis
                || t.observaciones_respuesta
                || 'Hay un problema reportado. Revise el detalle.',
        };
    }
    if (t.estado === 'PENDIENTE') {
        return {
            label: 'Nueva solicitud',
            tono: 'warning',
            icon: Package,
            texto: t.requiere_traslado_cedis
                ? 'Tome la tarea, localice piezas y prepare el traslado a CEDIS.'
                : 'Tome la tarea, localice piezas y responda con evidencia.',
        };
    }
    if (t.estado === 'LISTA_PARA_TRASLADO') {
        return {
            label: 'Lista para traslado',
            tono: 'info',
            icon: Truck,
            texto: t.solicitud_traspaso?.folio
                ? `Traspaso ${t.solicitud_traspaso.folio}. Confirme la salida hacia CEDIS.`
                : 'Mercancía lista. Genere/confirme el traspaso a CEDIS.',
        };
    }
    if (t.estado === 'LISTA_PARA_CARATULA') {
        return {
            label: 'Lista para carátula',
            tono: 'info',
            icon: Package,
            texto: t.entrega_municipal?.municipio_destino
                ? `Destino ${t.entrega_municipal.municipio_destino}. Genere e imprima la carátula.`
                : 'Genere e imprima la carátula municipal.',
        };
    }
    if (t.estado === 'EN_TRASLADO') {
        return {
            label: 'En traslado',
            tono: 'blue',
            icon: Truck,
            texto: 'Mercancía en camino. CEDIS debe confirmar recepción.',
        };
    }
    if (venc?.urgente) {
        return {
            label: 'Plazo',
            tono: 'warning',
            icon: Clock,
            texto: venc.texto,
        };
    }
    return null;
}

function TarjetaTarea({ tarea: t, puedeTomar }) {
    const venc = fmtVencimiento(t.fecha_limite);
    const badge = badgeEstado(t.estado, t.estado_label);
    const aviso = avisoParaTarea(t, venc);
    const folio = t.pedido?.folio_remision || t.pedido?.folio || `#${t.pedido?.id || t.id}`;
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
        <article className={`${geliaCardClass()} p-4 space-y-3 ${ringAlerta ? 'ring-1 ring-orange-500/40' : ''}`}>
            {t.modalidad?.nombre && (
                <p
                    className="text-sm font-black uppercase tracking-widest text-center py-2 px-3 rounded-xl bg-[var(--color-primario)]/10 m-0"
                    style={{ color: 'var(--color-primario)' }}
                >
                    {t.modalidad.nombre}
                </p>
            )}

            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-base font-black theme-text-main m-0 truncate" title={folio}>
                        {folio}
                    </p>
                    {t.solicitada_at && (
                        <p className="text-[10px] theme-text-muted font-bold mt-1 m-0">
                            {formatearFechaNegocio?.(t.solicitada_at) || new Date(t.solicitada_at).toLocaleDateString('es-MX')}
                            {' · '}
                            {fmtRelativo(t.solicitada_at)}
                        </p>
                    )}
                    {t.responsable?.name && (
                        <p className="text-[11px] theme-text-muted font-bold m-0 mt-1 inline-flex items-center gap-1">
                            <User className="w-3 h-3" /> {t.responsable.name}
                        </p>
                    )}
                </div>
                <div className="flex flex-col items-end gap-1.5 shrink-0 max-w-[50%]">
                    <span className={badge.className}>{badge.label}</span>
                    {t.modalidad?.es_transferencia && (
                        <span className="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border bg-amber-500/15 text-amber-800 dark:text-amber-200 border-amber-500/30">
                            Transferencia
                        </span>
                    )}
                    {t.requiere_traslado_cedis && (
                        <span className="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border bg-sky-500/15 text-sky-800 dark:text-sky-200 border-sky-500/30">
                            Traslado CEDIS
                        </span>
                    )}
                </div>
            </div>

            {aviso && (
                <AvisoOperativoPedido label={aviso.label} tono={aviso.tono} icon={aviso.icon}>
                    {aviso.texto}
                </AvisoOperativoPedido>
            )}

            <div className="grid grid-cols-2 gap-2 text-[10px] font-bold theme-text-muted uppercase">
                <div>
                    <p className="text-[9px] font-black m-0 opacity-70">Cliente</p>
                    <p className="text-xs theme-text-main m-0 mt-0.5 normal-case truncate" title={t.pedido?.cliente_nombre}>
                        {t.pedido?.cliente_nombre || '—'}
                    </p>
                </div>
                <div>
                    <p className="text-[9px] font-black m-0 opacity-70">Almacén</p>
                    <p className="text-xs theme-text-main m-0 mt-0.5 normal-case truncate">
                        {t.almacen?.nombre || '—'}
                    </p>
                </div>
                <div>
                    <p className="text-[9px] font-black m-0 opacity-70">Piezas</p>
                    <p className="text-xs theme-text-main m-0 mt-0.5 normal-case tabular-nums">
                        {t.piezas_solicitadas ?? t.pedido?.cantidad_piezas ?? 0}
                    </p>
                </div>
                <div>
                    <p className="text-[9px] font-black m-0 opacity-70">Antigüedad</p>
                    <p className="text-xs theme-text-main m-0 mt-0.5 normal-case inline-flex items-center gap-1">
                        <Clock className="w-3 h-3" /> {fmtRelativo(t.solicitada_at)}
                    </p>
                </div>
            </div>

            {t.entrega_municipal?.municipio_destino && (
                <div className="rounded-xl border border-teal-500/30 bg-teal-500/5 p-2.5">
                    <p className="text-[9px] font-black uppercase text-teal-700 dark:text-teal-300 m-0">Destino</p>
                    <p className="text-[11px] font-bold theme-text-main m-0 mt-0.5">
                        {t.entrega_municipal.municipio_destino}
                        {t.entrega_municipal.destinatario_nombre ? ` · ${t.entrega_municipal.destinatario_nombre}` : ''}
                    </p>
                </div>
            )}

            {t.solicitud_traspaso?.folio && (
                <div className="rounded-xl border border-violet-500/30 bg-violet-500/5 p-2.5">
                    <p className="text-[9px] font-black uppercase text-violet-700 dark:text-violet-300 m-0">Traspaso</p>
                    <p className="text-[11px] font-bold theme-text-main m-0 mt-0.5">
                        {t.solicitud_traspaso.folio}
                        {t.solicitud_traspaso.estado ? ` · ${t.solicitud_traspaso.estado}` : ''}
                    </p>
                </div>
            )}

            {venc && !aviso?.label?.includes('Plazo') && (
                <p className={`text-[11px] font-black m-0 ${venc.urgente ? 'text-red-600' : 'theme-text-muted'}`}>
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

export default function TarjetasTienda({ tareas = [], auth }) {
    const permisos = auth?.user?.permissions || [];
    const puedeTomar = permisos.includes('control_pedidos.tienda.tomar')
        || auth?.user?.roles?.includes('Super Admin');
    const items = tareas?.data || [];

    if (!items.length) {
        return (
            <div className={`${geliaCardClass()} p-10 md:p-16 text-center text-sm theme-text-muted font-bold uppercase tracking-widest`}>
                Sin tareas en esta bandeja_
            </div>
        );
    }

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 md:gap-4">
            {items.map((t) => (
                <TarjetaTarea key={t.id} tarea={t} puedeTomar={puedeTomar} />
            ))}
        </div>
    );
}
