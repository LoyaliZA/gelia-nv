import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Eye, CheckCircle2, AlertTriangle, FileText, Truck,
} from 'lucide-react';
import GeliaPaginacion from '../../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import {
    badgeEstatusPedido,
    badgeEmpaqueSemantico,
    esPedidoEmpacadoCedis,
    etiquetaAlmacen,
    formatearFechaNegocio,
    formatearFechaHoraAuditoria,
    BTN_PRIMARY,
    BTN_SECONDARY,
    tieneGuiaPdfDisponible,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import ModalVistaPreviaDocumento from '../../Partials/ModalVistaPreviaDocumento';
import BotonGuiaPdf from '../../Partials/BotonGuiaPdf';

const remisionDe = (pedido) => (pedido?.documentos || []).find((d) => d.tipo === 'remision');

function TarjetaPedido({ pedido, onVerDetalle, onReportarIncidencia, onSolicitarConfirmacion, onVerDocumento }) {
    const fase = pedido.estatus?.fase_ciclo;
    const badgeEstatus = badgeEstatusPedido(pedido.estatus);
    const badgeEmpaque = badgeEmpaqueSemantico(fase, pedido.es_resguardo);
    const remision = remisionDe(pedido);
    const esIncidencia = fase === 'INCIDENCIA_CEDIS';
    const esEmpacado = esPedidoEmpacadoCedis(fase);
    const puedeEmpacar = (fase === 'EN_CEDIS' || fase === 'INCIDENCIA_CEDIS') && !pedido.es_resguardo;
    const puedeMarcarEnviado = fase === 'PENDIENTE_DE_ENVIO';
    const tieneGuiaPdf = tieneGuiaPdfDisponible(pedido);
    const requiereLogistica = pedido.origen?.requiere_logistica ?? true;

    return (
        <div className={`${geliaCardClass()} p-4 space-y-3 ${esIncidencia ? 'ring-1 ring-orange-500/40' : ''}`}>
            {pedido.origen?.nombre && (
                <p className="text-sm font-black uppercase tracking-widest text-center py-2 px-3 rounded-xl bg-[var(--color-primario)]/10 m-0" style={{ color: 'var(--color-primario)' }}>
                    ORIGEN: {pedido.origen.nombre}
                </p>
            )}
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <EncabezadoFolioPedido pedido={pedido} size="sm" />
                    <p className="text-[10px] theme-text-muted font-bold mt-1 m-0">
                        {formatearFechaNegocio(pedido.fecha)}
                    </p>
                    {pedido.vendedor?.name && (
                        <p className="text-[9px] theme-text-muted font-bold mt-1 m-0">
                            Capturado por: {pedido.vendedor.name}
                        </p>
                    )}
                </div>
                <div className="flex flex-col items-end gap-1.5 shrink-0">
                    <span className={badgeEstatus.className} style={badgeEstatus.style}>
                        {badgeEstatus.label}
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
                    <p className="text-xs theme-text-main m-0 mt-0.5 normal-case">{etiquetaAlmacen(pedido.almacen)}</p>
                </div>
                {requiereLogistica && (
                <>
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
                </>
                )}
            </div>

            {esIncidencia && pedido.detalle_incidencia_empaque && (
                <div className="p-3 rounded-xl bg-orange-500/10 border border-orange-500/30 space-y-1">
                    <p className="text-[10px] text-orange-600 font-black uppercase m-0 flex items-center gap-1">
                        <AlertTriangle className="w-3.5 h-3.5" /> Incidencia
                    </p>
                    <p className="text-xs font-bold theme-text-main m-0">{pedido.detalle_incidencia_empaque}</p>
                    {pedido.incidencia_empaque_at && (
                        <p className="text-[9px] theme-text-muted font-bold m-0 font-mono">
                            {(pedido.incidencia_empaque_por?.name || pedido.incidenciaEmpaquePor?.name) && `${pedido.incidencia_empaque_por?.name || pedido.incidenciaEmpaquePor?.name} · `}
                            {formatearFechaHoraAuditoria(pedido.incidencia_empaque_at)}
                        </p>
                    )}
                </div>
            )}

            {esEmpacado && pedido.empacado_at && (
                <p className="text-[10px] text-emerald-600 font-bold m-0 font-mono">
                    Empacado por {(pedido.empacado_por?.name || pedido.empacadoPor?.name) || '—'} el {formatearFechaHoraAuditoria(pedido.empacado_at)}
                </p>
            )}

            {fase === 'PENDIENTE_DE_ENVIO' && pedido.numero_rastreo && (
                <p className="text-xs font-black font-mono theme-text-main m-0">
                    Guía: {pedido.numero_rastreo}
                </p>
            )}

            <div className="flex flex-wrap gap-2 pt-1">
                {remision && (
                    <button
                        type="button"
                        onClick={() => onVerDocumento(remision)}
                        className={`${BTN_SECONDARY} flex items-center gap-1.5 text-[10px] outline-none`}
                    >
                        <FileText className="w-3.5 h-3.5" /> Remisión
                    </button>
                )}
                {esEmpacado && tieneGuiaPdf && (
                    <BotonGuiaPdf pedido={pedido} onVerPdf={onVerDocumento} compact />
                )}
                {puedeMarcarEnviado && (
                    <button
                        type="button"
                        onClick={() => onSolicitarConfirmacion({ accion: 'enviar', pedido })}
                        className={`${BTN_PRIMARY} flex items-center gap-1.5 text-[10px] outline-none`}
                    >
                        <Truck className="w-3.5 h-3.5" /> Marcar enviado
                    </button>
                )}
                {puedeEmpacar && (
                    <button
                        type="button"
                        onClick={() => onSolicitarConfirmacion({ accion: 'empacar', pedido })}
                        className={`${BTN_PRIMARY} flex items-center gap-1.5 text-[10px] outline-none`}
                    >
                        <CheckCircle2 className="w-3.5 h-3.5" /> Marcar empacado
                    </button>
                )}
                {(fase === 'EN_CEDIS' || fase === 'INCIDENCIA_CEDIS') && pedido.es_resguardo && (
                    <span className="text-[10px] font-bold text-blue-600 uppercase tracking-wide">
                        Empaque bloqueado — en resguardo
                    </span>
                )}
                {fase === 'EN_CEDIS' && (
                    <button
                        type="button"
                        onClick={() => onReportarIncidencia(pedido)}
                        className={`${BTN_SECONDARY} flex items-center gap-1.5 text-[10px] border border-orange-500/40 text-orange-600 outline-none`}
                    >
                        <AlertTriangle className="w-3.5 h-3.5" /> Reportar Error
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
    const [confirmacion, setConfirmacion] = useState(null);
    const [docPreview, setDocPreview] = useState(null);

    const items = pedidos?.data || [];

    const ejecutarConfirmacion = () => {
        const { accion, pedido } = confirmacion || {};
        setConfirmacion(null);
        if (!pedido) return;

        if (accion === 'empacar') {
            router.post(route('control_pedidos.cedis.marcar_empacado', pedido.id), {}, { preserveScroll: true });
        } else if (accion === 'enviar') {
            router.post(route('control_pedidos.cedis.marcar_enviado', pedido.id), {}, { preserveScroll: true });
        }
    };

    const cfgConfirm = confirmacion?.accion === 'empacar'
        ? { titulo: 'Confirmar empaque', mensaje: '¿Confirmar que el pedido fue empacado?', etiquetaConfirmar: 'Marcar empacado', variante: 'primary' }
        : confirmacion?.accion === 'enviar'
            ? { titulo: 'Confirmar envío', mensaje: '¿Confirmar que el pedido salió de almacén?', etiquetaConfirmar: 'Marcar enviado', variante: 'primary' }
            : null;

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
                        onSolicitarConfirmacion={setConfirmacion}
                        onVerDocumento={setDocPreview}
                    />
                ))}
            </div>
            {pedidos?.links && <GeliaPaginacion paginator={pedidos} />}
            <ModalConfirmarAccion
                abierto={Boolean(cfgConfirm)}
                titulo={cfgConfirm?.titulo}
                mensaje={cfgConfirm?.mensaje}
                etiquetaConfirmar={cfgConfirm?.etiquetaConfirmar}
                variante={cfgConfirm?.variante}
                onClose={() => setConfirmacion(null)}
                onConfirm={ejecutarConfirmacion}
            />
            <ModalVistaPreviaDocumento abierto={Boolean(docPreview)} documento={docPreview} onClose={() => setDocPreview(null)} />
        </div>
    );
}
