import React from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';
import { badgeClaseEstatusPedido, formatearMoneda, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from './pedidosBmaStyles';

const Campo = ({ label, value }) => (
    <div>
        <p className="text-[9px] font-black uppercase theme-text-muted m-0">{label}</p>
        <p className="text-sm font-bold theme-text-main m-0 mt-0.5">{value ?? '—'}</p>
    </div>
);

export default function ModalDetallePedido({ abierto, onClose, pedido }) {
    if (!abierto || !pedido) return null;

    const badge = badgeClaseEstatusPedido(pedido.estatus);
    const envioTienda = pedido.envio_tienda?.es_otro
        ? pedido.envio_tienda_otro || pedido.envio_tienda?.nombre
        : pedido.envio_tienda?.nombre;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-2xl w-full flex flex-col`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="min-w-0">
                        <h2 className="text-xl font-black italic uppercase theme-text-main m-0">{pedido.folio}</h2>
                        <span className={`${badge.className} mt-2 inline-flex`} style={badge.style}>{pedido.estatus?.nombre_visual}</span>
                        {pedido.motivo_rechazo && (
                            <p className="text-sm text-red-500 font-bold mt-3 m-0">Motivo rechazo: {pedido.motivo_rechazo}</p>
                        )}
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted hover:theme-text-main outline-none shrink-0" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <div className="gelia-modal-body p-5 md:p-6">
                    <div className="grid grid-cols-2 gap-4">
                        <Campo label="Cliente" value={`${pedido.cliente?.numero_cliente || ''} — ${pedido.cliente?.nombre || ''}`} />
                        <Campo label="Fecha" value={pedido.fecha ? new Date(pedido.fecha).toLocaleDateString('es-MX') : ''} />
                        <Campo label="Status" value={pedido.estatus?.nombre_visual} />
                        <Campo label="Almacén" value={pedido.almacen_salida?.nombre} />
                        <Campo label="Banco" value={pedido.banco?.nombre} />
                        <Campo label="Tipo caja" value={pedido.tipo_caja?.nombre} />
                        <Campo label="Peso vol." value={pedido.peso_volumetrico_kg != null ? `${pedido.peso_volumetrico_kg} kg` : null} />
                        <Campo label="Peso real" value={pedido.peso_real_kg != null ? `${pedido.peso_real_kg} kg` : null} />
                        <Campo label="Peso c/productos" value={pedido.peso_con_productos_kg != null ? `${pedido.peso_con_productos_kg} kg` : null} />
                        <Campo label="Paquetería" value={pedido.paqueteria?.nombre} />
                        <Campo label="Tipo guía" value={pedido.tipo_guia?.nombre} />
                        <Campo label="Envío / Tienda" value={envioTienda} />
                        <Campo label="Reexpedición" value={pedido.zona?.nombre} />
                        <Campo label="Resguardo" value={pedido.es_resguardo ? 'Sí' : 'No'} />
                        <Campo label="C.P." value={pedido.codigo_postal} />
                        <Campo label="Total a cobrar" value={formatearMoneda(pedido.total_a_cobrar)} />
                        <Campo label="Rastreo" value={pedido.numero_rastreo} />
                    </div>
                    <div className="mt-4 space-y-3">
                        <Campo label="Domicilio" value={pedido.domicilio_entrega} />
                        {pedido.envia_a_otra_persona && (
                            <Campo label="Destinatario alterno" value={pedido.envia_otra_persona} />
                        )}
                        <Campo label="Comentarios" value={pedido.comentarios_drive} />
                    </div>
                    {pedido.documentos?.length > 0 && (
                        <div className="mt-4 flex flex-wrap gap-2">
                            {pedido.documentos.map((doc) => (
                                <a key={doc.id} href={doc.url} target="_blank" rel="noreferrer" className="block w-20 h-20 rounded-xl overflow-hidden border theme-border theme-element">
                                    <img src={doc.url} alt={doc.nombre_original} className="w-full h-full object-cover" />
                                </a>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}
