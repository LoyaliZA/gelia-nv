import React, { useEffect, useState } from 'react';
import ModalFormPedidoLegado, { hayBorradorPedidoLocal } from './ModalFormPedidoLegado';
import FormularioRegistrarTienda from './FormularioRegistrarTienda';
import { usaRegistroActual } from './flujoCapturaPedido';

export { hayBorradorPedidoLocal };

function modoInicial(pedido, recuperarBorrador) {
    if (recuperarBorrador) return 'legado';
    if (pedido?.solicitud_preparacion?.modalidad_entrega === 'municipal') return 'tienda';
    return usaRegistroActual(pedido) ? 'tienda' : 'legado';
}

/**
 * La captura actual comparte datos y documento entre Tienda y Municipal.
 * Nacional y municipales históricos sin marca conservan el formulario anterior.
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
    onLecturaPropia = null,
}) {
    const [modo, setModo] = useState(() => modoInicial(pedido, recuperarBorrador));
    const [pedidoEnvio, setPedidoEnvio] = useState(null);
    useEffect(() => {
        onLecturaPropia?.(abierto && modo === 'tienda');
        return () => onLecturaPropia?.(false);
    }, [abierto, modo, onLecturaPropia]);

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
