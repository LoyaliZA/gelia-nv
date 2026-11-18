import React, { useEffect, useState } from 'react';
import ModalFormPedidoLegado, { hayBorradorPedidoLocal } from './ModalFormPedidoLegado';
import FormularioRegistrarTienda from './FormularioRegistrarTienda';
import { usaRegistroActual } from './flujoCapturaPedido';
import useReservaConversionModalidad from './useReservaConversionModalidad';

export { hayBorradorPedidoLocal };

function modoInicial(pedido, recuperarBorrador, intencion) {
    if (intencion === 'conversion') return 'tienda';
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
    intencion = 'alta',
    onActualizar = null,
}) {
    const conversion = intencion === 'conversion';
    const [modo, setModo] = useState(() => modoInicial(pedido, recuperarBorrador, intencion));
    const [pedidoEnvio, setPedidoEnvio] = useState(null);
    const reservaConversion = useReservaConversionModalidad({
        activo: conversion && abierto,
        pedidoId: conversion ? pedido?.id : null,
        flagsIniciales: conversion ? (pedido?.conversion_modalidad || null) : null,
        onActualizar,
    });
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
            onActualizar={onActualizar}
            intencion={intencion}
            reservaConversion={conversion ? reservaConversion : null}
            onContinuarEnvio={(siguiente) => {
                setPedidoEnvio(siguiente);
                setModo('legado');
            }}
        />
    );
}
