import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { usePage } from '@inertiajs/react';
import { X, User, MapPin } from 'lucide-react';
import {
    badgeEstatusPedido,
    etiquetaEstatusPedido,
    formatearMoneda,
    etiquetaAlmacen,
    formatearFechaNegocio,
    formatearFechaHoraAuditoria,
    tieneGuiaLista,
    badgeGuiaLista,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    BTN_SECONDARY,
} from './pedidosBmaStyles';
import EncabezadoFolioPedido from './EncabezadoFolioPedido';
import ModalVistaPreviaDocumento, { MiniaturaDocumento } from './ModalVistaPreviaDocumento';
import SeccionGuiaRastreo from './SeccionGuiaRastreo';
import DireccionPedidoResumen from './DireccionPedidoResumen';
import { codigoDireccionCliente } from './codigoDireccionCliente';
import ModalCambiarDireccion from './ModalCambiarDireccion';

const Campo = ({ label, value }) => (
    <div>
        <p className="text-[9px] font-black uppercase theme-text-muted m-0">{label}</p>
        <p className="text-sm font-bold theme-text-main m-0 mt-0.5">{value ?? '—'}</p>
    </div>
);

export default function ModalDetallePedido({ abierto, onClose, pedido }) {
    const [docPreview, setDocPreview] = useState(null);
    const [cambiarDir, setCambiarDir] = useState(false);
    const { auth } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const can = (p) => permisos.includes(p) || auth?.user?.roles?.includes('Super Admin');

    if (!abierto || !pedido) return null;

    const badge = badgeEstatusPedido(pedido.estatus, { esResguardo: pedido.es_resguardo });
    const guiaLista = tieneGuiaLista(pedido);
    const badgeGuia = badgeGuiaLista();
    const docsSinGuia = (pedido.documentos || []).filter((d) => d.tipo !== 'guia');
    const snap = pedido.direccion_vigente || pedido.direccionVigente;

    return createPortal(
        <>
            <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
                <div
                    className={`${THEME_MODAL_SHELL} max-w-2xl w-full flex flex-col`}
                    style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                        <div className="min-w-0">
                            <EncabezadoFolioPedido pedido={pedido} size="lg" />
                            <div className="flex flex-wrap items-center gap-2 mt-2">
                                <span className={badge.className} style={badge.style}>{badge.label}</span>
                                {guiaLista && (
                                    <span className={badgeGuia.className}>{badgeGuia.label}</span>
                                )}
                            </div>
                            {pedido.vendedor?.name && (
                                <p className="text-xs font-bold theme-text-muted mt-2 m-0 flex items-center gap-1">
                                    <User className="w-3.5 h-3.5" /> Capturado por: {pedido.vendedor.name}
                                </p>
                            )}
                            {pedido.motivo_rechazo && (
                                <p className="text-sm text-red-500 font-bold mt-3 m-0">Motivo rechazo: {pedido.motivo_rechazo}</p>
                            )}
                        </div>
                        <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted hover:theme-text-main outline-none shrink-0" aria-label="Cerrar">
                            <X className="w-5 h-5" />
                        </button>
                    </div>
                    <div className="gelia-modal-body p-5 md:p-6">
                        <SeccionGuiaRastreo pedido={pedido} onVerPdf={setDocPreview} />
                        <div className="grid grid-cols-2 gap-4">
                            <Campo label="Cliente" value={`${pedido.cliente?.numero_cliente || ''} — ${pedido.cliente?.nombre || ''}`} />
                            <Campo label="Fecha pedido" value={formatearFechaNegocio(pedido.fecha)} />
                            <Campo label="Registrado" value={formatearFechaHoraAuditoria(pedido.created_at)} />
                            <Campo label="Status" value={etiquetaEstatusPedido(pedido.estatus, { esResguardo: pedido.es_resguardo })} />
                            <Campo label="Origen" value={pedido.origen?.nombre} />
                            <Campo label="Almacén" value={etiquetaAlmacen(pedido.almacen)} />
                            <Campo label="Banco" value={pedido.banco?.nombre} />
                            <Campo label="Tipo caja" value={pedido.tipo_caja?.nombre} />
                            <Campo label="Peso vol." value={pedido.peso_volumetrico_kg != null ? `${pedido.peso_volumetrico_kg} kg` : null} />
                            <Campo label="Peso real" value={pedido.peso_real_kg != null ? `${pedido.peso_real_kg} kg` : null} />
                            <Campo label="Peso cobrado guía" value={pedido.peso_cobrado_guia_kg != null ? `${pedido.peso_cobrado_guia_kg} kg` : null} />
                            <Campo label="Paquetería" value={pedido.paqueteria?.nombre} />
                            <Campo label="Tipo guía" value={pedido.tipo_guia?.nombre} />
                            <Campo label="Reexpedición" value={pedido.zona?.nombre} />
                            <Campo label="Resguardo" value={pedido.es_resguardo ? 'Sí' : 'No'} />
                            <Campo label="Anexar remisión" value={pedido.anexar_remision ? 'Sí' : 'No'} />
                            <Campo label="C.P." value={pedido.codigo_postal} />
                            <Campo label="Total a cobrar" value={formatearMoneda(pedido.total_a_cobrar)} />
                        </div>
                        <div className="mt-4 space-y-3">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-[9px] font-black uppercase theme-text-muted m-0">Domicilio de envío</p>
                                {can('control_pedidos.direccion.cambiar') && (
                                    <button
                                        type="button"
                                        className={`${BTN_SECONDARY} text-xs py-1.5 px-2 inline-flex items-center gap-1`}
                                        onClick={() => setCambiarDir(true)}
                                    >
                                        <MapPin className="w-3.5 h-3.5" /> Cambiar
                                    </button>
                                )}
                            </div>
                            <DireccionPedidoResumen
                                direccion={snap}
                                domicilioLegacy={pedido.domicilio_entrega}
                                codigoPostal={pedido.codigo_postal}
                                codigoDireccion={codigoDireccionCliente(pedido.cliente?.numero_cliente, snap?.numero_direccion)}
                            />
                            {pedido.envia_a_otra_persona && (
                                <Campo label="Destinatario alterno" value={pedido.envia_otra_persona} />
                            )}
                            <Campo label="Comentarios" value={pedido.comentarios_drive} />
                        </div>
                        {docsSinGuia.length > 0 && (
                            <div className="mt-4 flex flex-wrap gap-2">
                                {docsSinGuia.map((doc) => (
                                    <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
            <ModalVistaPreviaDocumento abierto={Boolean(docPreview)} documento={docPreview} onClose={() => setDocPreview(null)} />
            <ModalCambiarDireccion abierto={cambiarDir} onClose={() => setCambiarDir(false)} pedido={pedido} />
        </>,
        document.body
    );
}
