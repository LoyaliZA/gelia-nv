import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Eye, CheckCircle2, AlertTriangle, FileText, Truck, PackageCheck, Scale, History,
} from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import {
    badgeEmpaqueSemantico,
    badgeEstatusEnvio,
    badgeRetrasoGuia,
    badgesRetrasoSla,
    tieneRetrasoEmpaqueActivo,
    tieneRetrasoRecoleccionActivo,
    badgeConComplementos,
    complementosDe,
    esPedidoEmpacadoCedis,
    etiquetaAlmacen,
    formatearFechaNegocio,
    formatearFechaHoraAuditoria,
    BTN_PRIMARY,
    tieneGuiaPdfDisponible,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import BloqueVendedorPedido from '../../Partials/BloqueVendedorPedido';
import BotonAccionCubico from '../../Partials/BotonAccionCubico';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import ModalVistaPreviaDocumento from '../../Partials/ModalVistaPreviaDocumento';
import BotonGuiaPdf from '../../Partials/BotonGuiaPdf';
import AvisoOperativoPedido from '../../Partials/AvisoOperativoPedido';

const remisionDe = (pedido) => (pedido?.documentos || []).find((d) => d.tipo === 'remision');
const pdfPedidoDe = (pedido) => (pedido?.documentos || []).find((d) => d.tipo === 'pdf_pedido');
const anexoPiezasDe = (pedido) => (pedido?.documentos || []).find((d) => d.tipo === 'anexo_piezas');

function TarjetaPedido({
    pedido, onVerDetalle, onResponderPesaje, onReportarErrorDatos, onMarcarApartado, onSolicitarConfirmacion, onVerDocumento, onBitacora,
}) {
    const fase = pedido.estatus?.fase_ciclo;
    const pendientePesaje = pedido.estatus_envio === 'pendiente_pesaje';
    const badgeEmpaque = badgeEmpaqueSemantico(fase, pedido.es_resguardo, Boolean(pedido.resguardo_apartado_at));
    const badgeEnvio = badgeEstatusEnvio(pedido.estatus_envio, { forzarPesaje: true });
    const badgeRetraso = pedido.guia_retraso ? badgeRetrasoGuia() : null;
    const badgesSla = badgesRetrasoSla(pedido);
    const badgeComp = badgeConComplementos(pedido);
    const complementos = complementosDe(pedido);
    const remision = remisionDe(pedido);
    const pdfPedido = pdfPedidoDe(pedido);
    const anexoPiezas = anexoPiezasDe(pedido);
    const esErrorCedis = fase === 'INCIDENCIA_CEDIS';
    const esEmpacado = esPedidoEmpacadoCedis(fase);
    const puedeEmpacar = (fase === 'EN_CEDIS' || fase === 'INCIDENCIA_CEDIS') && !pedido.es_resguardo;
    const puedeMarcarEnviado = fase === 'PENDIENTE_DE_ENVIO';
    const cajasPedido = pedido.cajas || [];
    const cajasPendientesCount = pedido.cajas_pendientes
        ?? cajasPedido.filter((c) => (c.estatus_recoleccion || 'pendiente') === 'pendiente').length;
    const cajasRecolectadasCount = pedido.cajas_recolectadas
        ?? cajasPedido.filter((c) => c.estatus_recoleccion === 'recolectada').length;
    const requiereSeleccionEnvios = cajasPendientesCount > 1;
    const puedeReportarError = ['EN_CEDIS', 'INCIDENCIA_CEDIS', 'PENDIENTE_DE_GUIA', 'PENDIENTE_DE_ENVIO'].includes(fase) && !pedido.es_resguardo;
    const puedeApartar = Boolean(pedido.es_resguardo) && fase === 'EN_CEDIS' && !pedido.resguardo_apartado_at;
    const tieneGuiaPdf = tieneGuiaPdfDisponible(pedido);
    const requiereLogistica = pedido.origen?.requiere_logistica ?? true;
    const ringRetraso = pedido.guia_retraso
        || tieneRetrasoEmpaqueActivo(pedido)
        || tieneRetrasoRecoleccionActivo(pedido);

    return (
        <div className={`${geliaCardClass()} p-4 space-y-3 ${esErrorCedis ? 'ring-1 ring-orange-500/40' : ''} ${ringRetraso ? 'ring-1 ring-amber-500/40' : ''}`}>
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
                    <BloqueVendedorPedido pedido={pedido} variante="nombre" />
                </div>
                <div className="flex flex-col items-end gap-1.5 shrink-0 max-w-[50%]">
                    <BloqueVendedorPedido pedido={pedido} variante="etiquetas" className="mt-0 justify-end" />
                    {!pendientePesaje && (
                        <span className={badgeEmpaque.className} style={badgeEmpaque.style}>{badgeEmpaque.label}</span>
                    )}
                    {badgeEnvio && (
                        <span className={badgeEnvio.className} style={badgeEnvio.style}>{badgeEnvio.label}</span>
                    )}
                    {badgeComp && (
                        <span className={badgeComp.className} style={badgeComp.style}>{badgeComp.label}</span>
                    )}
                    {badgeRetraso && (
                        <span className={badgeRetraso.className} style={badgeRetraso.style}>{badgeRetraso.label}</span>
                    )}
                    {badgesSla.map((b) => (
                        <span key={b.label} className={b.className} style={b.style}>{b.label}</span>
                    ))}
                    {fase === 'PENDIENTE_DE_ENVIO' && cajasPedido.length > 1 && (
                        <span className="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-700">
                            {cajasRecolectadasCount}/{cajasPedido.length} recolectadas
                        </span>
                    )}
                </div>
            </div>

            {pendientePesaje && (
                <AvisoOperativoPedido label="Consulta de pesaje" tono="warning" icon={Scale}>
                    {pedido.motivo_repesaje
                        ? `Re-pesaje solicitado (${pedido.motivo_repesaje}). Revise el PDF/foto y registre peso/cajas.`
                        : 'Revise el PDF o foto del pedido y registre peso y cajas.'}
                </AvisoOperativoPedido>
            )}

            {complementos.length > 0 && (
                <div className="rounded-xl border border-teal-500/30 bg-teal-500/5 p-2.5 space-y-1">
                    <p className="text-[9px] font-black uppercase text-teal-700 dark:text-teal-400 m-0">Complementos</p>
                    {complementos.map((c) => (
                        <p key={c.id} className="text-[11px] font-bold theme-text-main m-0">
                            {c.folio}{c.folio_remision ? ` · ${c.folio_remision}` : ''}
                        </p>
                    ))}
                </div>
            )}

            <AvisoOperativoPedido
                label="Nota de compra en el envío"
                tono={pedido.anexar_remision ? 'success' : 'warning'}
                icon={FileText}
            >
                {pedido.anexar_remision
                    ? 'Incluir nota de compra en el paquete'
                    : 'No incluir nota de compra (dropshipping)'}
            </AvisoOperativoPedido>

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

            {esErrorCedis && (pedido.detalle_incidencia_empaque || pedido.detalle_error_datos) && (
                <AvisoOperativoPedido label="Error reportado" tono="danger" icon={AlertTriangle}>
                    {pedido.detalle_incidencia_empaque || pedido.detalle_error_datos}
                </AvisoOperativoPedido>
            )}

            {(fase === 'EN_CEDIS' || fase === 'INCIDENCIA_CEDIS') && pedido.es_resguardo && (
                <AvisoOperativoPedido label="Resguardo" tono="blue" icon={PackageCheck}>
                    {pedido.resguardo_apartado_at
                        ? 'Resguardo apartado — empaque bloqueado'
                        : 'Empaque bloqueado — en resguardo'}
                </AvisoOperativoPedido>
            )}

            {esEmpacado && pedido.empacado_at && (
                <AvisoOperativoPedido label="Empaque" tono="success" icon={CheckCircle2}>
                    Empacado por {(pedido.empacado_por?.name || pedido.empacadoPor?.name) || '—'}
                    <span className="block text-sm font-bold mt-1 opacity-80 font-mono">
                        {formatearFechaHoraAuditoria(pedido.empacado_at)}
                    </span>
                </AvisoOperativoPedido>
            )}
            {fase === 'PENDIENTE_GUIA_CLIENTE' && (
                <AvisoOperativoPedido label="Guía del cliente" tono="info">
                    Esperando guía del cliente (vendedora). Solo lectura hasta que cargue la guía.
                </AvisoOperativoPedido>
            )}

            <div className="pt-2 border-t theme-border space-y-2">
                {pendientePesaje && (
                    <button type="button" onClick={() => onResponderPesaje?.(pedido)} className={`${BTN_PRIMARY} w-full flex items-center justify-center gap-2 text-xs outline-none py-3 min-h-[44px]`}>
                        <Scale className="w-4 h-4" /> Responder pesaje
                    </button>
                )}
                {puedeMarcarEnviado && (
                    <button
                        type="button"
                        onClick={() => (requiereSeleccionEnvios
                            ? onVerDetalle(pedido)
                            : onSolicitarConfirmacion({ accion: 'enviar', pedido }))}
                        className={`${BTN_PRIMARY} w-full flex items-center justify-center gap-2 text-xs outline-none py-3 min-h-[44px]`}
                    >
                        <Truck className="w-4 h-4" />
                        {requiereSeleccionEnvios ? 'Elegir envíos a recolectar' : 'Marcar enviado'}
                    </button>
                )}
                {puedeEmpacar && (
                    <button type="button" onClick={() => onSolicitarConfirmacion({ accion: 'empacar', pedido })} className={`${BTN_PRIMARY} w-full flex items-center justify-center gap-2 text-xs outline-none py-3 min-h-[44px]`}>
                        <CheckCircle2 className="w-4 h-4" /> {complementos.length ? 'Empacar grupo' : 'Marcar empacado'}
                    </button>
                )}
                {puedeApartar && (
                    <button type="button" onClick={() => onMarcarApartado?.(pedido)} className={`${BTN_PRIMARY} w-full flex items-center justify-center gap-2 text-xs outline-none py-3 min-h-[44px]`}>
                        <PackageCheck className="w-4 h-4" /> Marcar apartado
                    </button>
                )}
                <div className="grid grid-cols-2 gap-2">
                    {pendientePesaje && pdfPedido && (
                        <BotonAccionCubico icon={FileText} label="PDF pedido" onClick={() => onVerDocumento(pdfPedido)} conLabel />
                    )}
                    {pendientePesaje && anexoPiezas && (
                        <BotonAccionCubico icon={FileText} label="Piezas extra" onClick={() => onVerDocumento(anexoPiezas)} conLabel />
                    )}
                    {esEmpacado && tieneGuiaPdf && (
                        <div className="col-span-2 sm:col-span-1 [&_button]:w-full [&_button]:min-h-[44px]">
                            <BotonGuiaPdf pedido={pedido} onVerPdf={onVerDocumento} compact className="w-full justify-center py-2.5 min-h-[44px]" />
                        </div>
                    )}
                    {remision && (
                        <BotonAccionCubico icon={FileText} label="Remisión" onClick={() => onVerDocumento(remision)} conLabel />
                    )}
                    {puedeReportarError && (
                        <BotonAccionCubico icon={AlertTriangle} label="Reportar" onClick={() => onReportarErrorDatos?.(pedido)} tone="warn" conLabel className="col-span-2" />
                    )}
                    {!pendientePesaje && (
                        <BotonAccionCubico icon={Eye} label="Detalle" onClick={() => onVerDetalle(pedido)} conLabel />
                    )}
                    {onBitacora && (
                        <BotonAccionCubico
                            icon={History}
                            label="Bitácora"
                            onClick={() => onBitacora(pedido)}
                            tone="purple"
                            conLabel
                            className={pendientePesaje && !pdfPedido && !anexoPiezas ? 'col-span-2' : ''}
                        />
                    )}
                </div>
            </div>
        </div>
    );
}

export default function TarjetasCedis({
    pedidos, onVerDetalle, onResponderPesaje, onReportarErrorDatos, onMarcarApartado, onBitacora,
}) {
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

    const comps = confirmacion?.accion === 'empacar' ? complementosDe(confirmacion.pedido) : [];
    const cfgConfirm = confirmacion?.accion === 'empacar'
        ? {
            titulo: comps.length ? 'Confirmar empaque del grupo' : 'Confirmar empaque',
            mensaje: comps.length
                ? `Se empacará ${confirmacion.pedido.folio} y ${comps.length} complemento(s).`
                : '¿Confirmar que el pedido fue empacado?',
            etiquetaConfirmar: comps.length ? 'Empacar grupo' : 'Marcar empacado',
            variante: 'primary',
        }
        : confirmacion?.accion === 'enviar'
            ? { titulo: 'Confirmar envío', mensaje: 'Al confirmar, el pedido sale a recolección.', etiquetaConfirmar: 'Marcar enviado', variante: 'primary' }
            : null;

    if (items.length === 0) {
        return (
            <div className={`${geliaCardClass()} p-10 md:p-16 text-center text-sm theme-text-muted font-bold uppercase tracking-widest`}>
                Sin pedidos en esta vista_
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 md:gap-4">
                {items.map((pedido) => (
                    <TarjetaPedido
                        key={pedido.id}
                        pedido={pedido}
                        onVerDetalle={onVerDetalle}
                        onResponderPesaje={onResponderPesaje}
                        onReportarErrorDatos={onReportarErrorDatos}
                        onMarcarApartado={onMarcarApartado}
                        onSolicitarConfirmacion={setConfirmacion}
                        onVerDocumento={setDocPreview}
                        onBitacora={onBitacora}
                    />
                ))}
            </div>
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
