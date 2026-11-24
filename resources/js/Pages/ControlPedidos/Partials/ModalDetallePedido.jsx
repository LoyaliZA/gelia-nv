import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { usePage } from '@inertiajs/react';
import usePedidoDialog from './usePedidoDialog';
import {
    respondeComoTienda,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from './pedidosBmaStyles';
import ModalVistaPreviaDocumento from './ModalVistaPreviaDocumento';
import ModalCambiarDireccion from './ModalCambiarDireccion';
import ReferenciaPedidoCompartido from './ReferenciaPedidoCompartido';
import DetallePedidoCabecera from './detalle-pedido/DetallePedidoCabecera';
import { DetallePedidoDocumentos } from './detalle-pedido/DetallePedidoSeccion';
import CuerpoDetalleTienda from './detalle-pedido/CuerpoDetalleTienda';
import CuerpoDetalleMunicipal from './detalle-pedido/CuerpoDetalleMunicipal';
import CuerpoDetalleNacional from './detalle-pedido/CuerpoDetalleNacional';
import { resolverModalidadDetalle } from './detalle-pedido/resolverModalidadDetalle';

const CUERPO = {
    tienda: CuerpoDetalleTienda,
    municipal: CuerpoDetalleMunicipal,
    nacional: CuerpoDetalleNacional,
    legado: CuerpoDetalleNacional,
};

export default function ModalDetallePedido({ abierto, onClose, pedido }) {
    const [docPreview, setDocPreview] = useState(null);
    const [cambiarDir, setCambiarDir] = useState(false);
    const { auth } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const can = (p) => permisos.includes(p) || auth?.user?.roles?.includes('Super Admin');
    const puedeAtender = Boolean(pedido?.puede_mutar)
        || Number(pedido?.vendedor_id) === Number(auth?.user?.id);

    const dialog = usePedidoDialog({ abierto: abierto && Boolean(pedido), onClose });

    if (!abierto || !pedido) return null;

    const modalidad = resolverModalidadDetalle(pedido);
    const Cuerpo = CUERPO[modalidad] || CuerpoDetalleNacional;
    const respondeTienda = respondeComoTienda(pedido);
    const verGaleria = (documentos, indice) => setDocPreview({ documentos, indice });

    return createPortal(
        <>
            <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
                <div
                    {...dialog}
                    aria-labelledby="detalle-pedido-titulo"
                    className={`${THEME_MODAL_SHELL} gelia-pedidos-dialog-workspace gelia-pedidos-detalle max-w-6xl w-full flex flex-col`}
                    style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                    onClick={(e) => e.stopPropagation()}
                >
                    <DetallePedidoCabecera pedido={pedido} modalidad={modalidad} onClose={onClose} />
                    <div className="gelia-modal-body p-4 md:p-6">
                        <div className="gelia-pedidos-detalle-grid">
                            <aside className="gelia-pedidos-referencia">
                                <ReferenciaPedidoCompartido
                                    id="detalle-compartido"
                                    pedido={pedido}
                                    descripcion={respondeTienda
                                        ? 'Compara las piezas solicitadas con la revisión y las evidencias de Tienda.'
                                        : undefined}
                                    onVerGaleria={verGaleria}
                                />
                            </aside>
                            <div className="min-w-0 space-y-4">
                                <Cuerpo
                                    pedido={pedido}
                                    vacios={modalidad === 'legado'}
                                    puedeAtender={puedeAtender}
                                    puedeCambiarDireccion={can('control_pedidos.direccion.cambiar')}
                                    onVerDoc={setDocPreview}
                                    onVerGaleria={verGaleria}
                                    onCambiarDireccion={() => setCambiarDir(true)}
                                />
                                <DetallePedidoDocumentos
                                    pedido={pedido}
                                    onVerDoc={setDocPreview}
                                    ocultarApartadoCedis={modalidad === 'municipal' || modalidad === 'tienda'}
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <ModalVistaPreviaDocumento abierto={Boolean(docPreview)} documento={docPreview?.documentos ? null : docPreview} documentos={docPreview?.documentos} indice={docPreview?.indice || 0} onClose={() => setDocPreview(null)} />
            <ModalCambiarDireccion abierto={cambiarDir} onClose={() => setCambiarDir(false)} pedido={pedido} />
        </>,
        document.body
    );
}
