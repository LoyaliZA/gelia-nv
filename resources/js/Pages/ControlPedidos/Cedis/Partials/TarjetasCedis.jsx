import React from 'react';
import { router } from '@inertiajs/react';
import {
    Eye, CheckCircle2, AlertTriangle, FileText, RotateCcw,
} from 'lucide-react';
import GeliaPaginacion from '../../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import {
    badgeClaseEstatusPedido,
    badgeEmpaqueSemantico,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from '../../Partials/pedidosBmaStyles';

const remisionDe = (pedido) => (pedido?.documentos || []).find((d) => d.tipo === 'remision');

function TarjetaPedido({ pedido, onVerDetalle, onReportarIncidencia }) {
    const fase = pedido.estatus?.fase_ciclo;
    const badgeEstatus = badgeClaseEstatusPedido(pedido.estatus);
    const badgeEmpaque = badgeEmpaqueSemantico(fase);
    const remision = remisionDe(pedido);
    const esIncidencia = fase === 'INCIDENCIA_CEDIS';
    const esEmpacado = fase === 'EN_RUTA';
    const puedeEmpacar = fase === 'EN_CEDIS' || fase === 'INCIDENCIA_CEDIS';

    const marcarEmpacado = () => {
        if (!window.confirm('¿Confirmar que el pedido fue empacado?')) return;
        router.post(route('control_pedidos.cedis.marcar_empacado', pedido.id), {}, { preserveScroll: true });
    };

    const revertirEmpacado = () => {
        if (!window.confirm('¿Revertir el empaque de este pedido?')) return;
        router.post(route('control_pedidos.cedis.revertir_empacado', pedido.id), {}, { preserveScroll: true });
    };

    return (
        <div className={`${geliaCardClass()} p-4 space-y-3 ${esIncidencia ? 'ring-1 ring-orange-500/40' : ''}`}>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-sm font-black theme-text-main uppercase italic m-0">{pedido.folio}</p>
                    <p className="text-[10px] theme-text-muted font-bold mt-1 m-0">
                        {pedido.fecha ? new Date(pedido.fecha).toLocaleDateString('es-MX') : '—'}
                    </p>
                </div>
                <div className="flex flex-col items-end gap-1.5 shrink-0">
                    <span className={badgeEstatus.className} style={badgeEstatus.style}>
                        {pedido.estatus?.nombre_visual || '—'}
                    </span>
                    <span className={badgeEmpaque.className} style={badgeEmpaque.style}>
                        {badgeEmpaque.label}
                    </span>
                </div>
            </div>

            <div className="grid grid-cols-2 gap-2 text-[10px] font-bold theme-text-muted uppercase">
                <div>
                    <p className="text-[9px] font-black m-0 opacity-70">Cliente</p>
                    <p className="text-xs theme-text-main m-0 mt-0.5 normal-case">{pedido.cliente?.nombre || '—'}</p>
                </div>
                <div>
                    <p className="text-[9px] font-black m-0 opacity-70">Almacén</p>
                    <p className="text-xs theme-text-main m-0 mt-0.5 normal-case">{pedido.almacen_salida?.nombre || '—'}</p>
                </div>
                <div>
                    <p className="text-[9px] font-black m-0 opacity-70">Paquetería</p>
                    <p className="text-xs theme-text-main m-0 mt-0.5 normal-case">{pedido.paqueteria?.nombre || '—'}</p>
                </div>
                <div>
                    <p className="text-[9px] font-black m-0 opacity-70">Cajas / Guía</p>
                    <p className="text-xs theme-text-main m-0 mt-0.5 normal-case">
                        {pedido.numero_cajas ?? '—'} · {pedido.tipo_guia?.nombre || '—'}
                    </p>
                </div>
            </div>

            {esIncidencia && pedido.detalle_incidencia_empaque && (
                <div className="p-3 rounded-xl bg-orange-500/10 border border-orange-500/30 space-y-1">
                    <p className="text-[10px] text-orange-600 font-black uppercase m-0 flex items-center gap-1">
                        <AlertTriangle className="w-3.5 h-3.5" /> Incidencia
                    </p>
                    <p className="text-xs font-bold theme-text-main m-0">{pedido.detalle_incidencia_empaque}</p>
                    {pedido.incidencia_empaque_at && (
                        <p className="text-[9px] theme-text-muted font-bold m-0">
                            {(pedido.incidencia_empaque_por?.name || pedido.incidenciaEmpaquePor?.name) && `${pedido.incidencia_empaque_por?.name || pedido.incidenciaEmpaquePor?.name} · `}
                            {new Date(pedido.incidencia_empaque_at).toLocaleString('es-MX')}
                        </p>
                    )}
                </div>
            )}

            {esEmpacado && pedido.empacado_at && (
                <p className="text-[10px] text-emerald-600 font-bold m-0">
                    Empacado por {(pedido.empacado_por?.name || pedido.empacadoPor?.name) || '—'} el {new Date(pedido.empacado_at).toLocaleString('es-MX')}
                </p>
            )}

            <div className="flex flex-wrap gap-2 pt-1">
                {remision && (
                    <a
                        href={remision.url}
                        target="_blank"
                        rel="noreferrer"
                        className={`${BTN_SECONDARY} flex items-center gap-1.5 text-[10px] outline-none no-underline`}
                    >
                        <FileText className="w-3.5 h-3.5" /> Remisión
                    </a>
                )}
                {puedeEmpacar && (
                    <button
                        type="button"
                        onClick={marcarEmpacado}
                        className={`${BTN_PRIMARY} flex items-center gap-1.5 text-[10px] outline-none`}
                    >
                        <CheckCircle2 className="w-3.5 h-3.5" /> Marcar empacado
                    </button>
                )}
                {fase === 'EN_CEDIS' && (
                    <button
                        type="button"
                        onClick={() => onReportarIncidencia(pedido)}
                        className={`${BTN_SECONDARY} flex items-center gap-1.5 text-[10px] border border-orange-500/40 text-orange-600 outline-none`}
                    >
                        <AlertTriangle className="w-3.5 h-3.5" /> Pendiente de empaque
                    </button>
                )}
                {esEmpacado && (
                    <button
                        type="button"
                        onClick={revertirEmpacado}
                        className={`${BTN_SECONDARY} flex items-center gap-1.5 text-[10px] outline-none`}
                    >
                        <RotateCcw className="w-3.5 h-3.5" /> Revertir
                    </button>
                )}
                <button
                    type="button"
                    onClick={() => onVerDetalle(pedido)}
                    className={`${BTN_SECONDARY} flex items-center gap-1.5 text-[10px] outline-none ml-auto`}
                >
                    <Eye className="w-3.5 h-3.5" /> Ver detalle
                </button>
            </div>
        </div>
    );
}

export default function TarjetasCedis({ pedidos, onVerDetalle, onReportarIncidencia }) {
    const items = pedidos?.data || [];

    if (items.length === 0) {
        return (
            <div className={`${geliaCardClass()} p-16 text-center text-sm theme-text-muted font-bold uppercase tracking-widest`}>
                Sin pedidos en esta vista_
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                {items.map((pedido) => (
                    <TarjetaPedido
                        key={pedido.id}
                        pedido={pedido}
                        onVerDetalle={onVerDetalle}
                        onReportarIncidencia={onReportarIncidencia}
                    />
                ))}
            </div>
            {pedidos?.links && <GeliaPaginacion paginator={pedidos} />}
        </div>
    );
}
