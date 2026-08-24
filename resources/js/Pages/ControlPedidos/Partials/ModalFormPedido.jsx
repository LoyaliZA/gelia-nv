import React from 'react';
import ModalFormPedidoLegado, { hayBorradorPedidoLocal } from './ModalFormPedidoLegado';

export { hayBorradorPedidoLocal };

/**
 * Orquestador del formulario de Ventas.
 * Flag off → layout vigente (legado).
 * Flag on → mismo formulario con shell progresivo (DTO de etapas).
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
    const modoProgresivo = Boolean(catalogos?.formulario_config?.formulario_progresivo);

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
