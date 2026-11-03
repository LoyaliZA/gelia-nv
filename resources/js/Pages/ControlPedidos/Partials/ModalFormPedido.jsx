import React, { useState } from 'react';
import ModalFormPedidoLegado, { hayBorradorPedidoLocal } from './ModalFormPedidoLegado';
import FormularioRegistrarTienda from './FormularioRegistrarTienda';

export { hayBorradorPedidoLocal };

const CODIGOS_TIENDA = ['RECOGE_TIENDA', 'RECOGE_TIENDA_TRANSFERENCIA', 'RECOGE_TIENDA_CREDITO'];

function esCapturaRegistrar(pedido) {
    const solicitud = pedido?.solicitud_preparacion;
    if (!solicitud || typeof solicitud !== 'object') return false;
    if (solicitud.modalidad_entrega === 'municipal' || solicitud.modalidad_entrega === 'nacional') return false;
    if (solicitud.modalidad_entrega === 'tienda' || Object.prototype.hasOwnProperty.call(solicitud, 'modalidad_entrega')) return true;
    const marcaTienda = Object.prototype.hasOwnProperty.call(solicitud, 'atencion_tienda')
        || Object.prototype.hasOwnProperty.call(solicitud, 'piezas_tienda');
    if (!marcaTienda) return false;
    const codigo = solicitud.codigo_modalidad;
    return !codigo || CODIGOS_TIENDA.includes(codigo);
}

function modoInicial(pedido, recuperarBorrador) {
    if (recuperarBorrador) return 'legado';
    if (!pedido) return 'tienda';
    if (esCapturaRegistrar(pedido)) return 'tienda';
    return 'legado';
}

/**
 * Un alta nueva, o un borrador guardado por esta captura, usa Registrar pedidos — Tienda.
 * Municipal, nacional y los pedidos ya abiertos en el formulario anterior siguen ahí.
 */
export default function ModalFormPedido({
    abierto,
    onClose,
    pedido = null,
    catalogos = {},
    direccionesNormalizadas = false,
    recuperarBorrador = false,
    onPedidoCreado = null,
    registrarTienda = {},
}) {
    const [modo, setModo] = useState(() => modoInicial(pedido, recuperarBorrador));
    const [pedidoEnvio, setPedidoEnvio] = useState(null);

    if (modo === 'legado') {
        return (
            <ModalFormPedidoLegado
                abierto={abierto}
                onClose={onClose}
                pedido={pedidoEnvio || pedido}
                catalogos={catalogos}
                direccionesNormalizadas={direccionesNormalizadas}
                recuperarBorrador={recuperarBorrador}
                onPedidoCreado={onPedidoCreado}
                modoProgresivo
            />
        );
    }

    return (
        <FormularioRegistrarTienda
            abierto={abierto}
            onClose={onClose}
            pedido={pedido}
            catalogos={catalogos}
            registrarTienda={registrarTienda}
            onPedidoCreado={onPedidoCreado}
            onContinuarEnvio={(siguiente) => {
                setPedidoEnvio(siguiente);
                setModo('legado');
            }}
        />
    );
}
