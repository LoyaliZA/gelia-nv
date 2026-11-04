import React, { useState } from 'react';
import ModalFormPedidoLegado, { hayBorradorPedidoLocal } from './ModalFormPedidoLegado';
import FormularioRegistrarTienda from './FormularioRegistrarTienda';
import { usaRegistroActual } from './flujoCapturaPedido';

export { hayBorradorPedidoLocal };

function modoInicial(pedido, recuperarBorrador) {
    if (recuperarBorrador) return 'legado';
    return usaRegistroActual(pedido) ? 'tienda' : 'legado';
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
