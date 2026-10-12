import React from 'react';
import ModalFormPedidoLegado, { hayBorradorPedidoLocal } from './ModalFormPedidoLegado';

export { hayBorradorPedidoLocal };

/**
 * Orquestador del formulario de Ventas.
 * El registro usa el formulario por fases en el mismo modal.
 */
export default function ModalFormPedido({
    abierto,
    onClose,
    pedido = null,
    catalogos = {},
    direccionesNormalizadas = false,
    recuperarBorrador = false,
    onPedidoCreado = null,
}) {
    const modoProgresivo = true;

    return (
        <ModalFormPedidoLegado
            abierto={abierto}
            onClose={onClose}
            pedido={pedido}
            catalogos={catalogos}
            direccionesNormalizadas={direccionesNormalizadas}
            recuperarBorrador={recuperarBorrador}
            onPedidoCreado={onPedidoCreado}
            modoProgresivo={modoProgresivo}
        />
    );
}
