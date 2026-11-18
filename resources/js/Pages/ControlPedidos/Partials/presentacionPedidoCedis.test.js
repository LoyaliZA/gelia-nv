import { expect, it } from 'vitest';
import { presentacionPedidoCedis } from './presentacionPedidoCedis';

it('municipal muestra el almacén y terminal reales sin confundir sucursal con terminal', () => {
    expect(presentacionPedidoCedis({ solicitud_preparacion: { modalidad_entrega: 'municipal' }, almacen: { nombre: 'CEDIS Norte' }, paqueteria: { nombre: 'Taxi Tacotalpa' }, sucursal_destino: { nombre: 'PDV' } }))
        .toMatchObject({ etiqueta: 'Envío:', nombre: 'MUNICIPAL', origen: 'CEDIS Norte', destino: 'Taxi Tacotalpa' });
});

it('no inventa almacén ni terminal cuando solo se conoce el área', () => {
    expect(presentacionPedidoCedis({ solicitud_preparacion: { modalidad_entrega: 'municipal', consulta_municipal: { area: 'cedis' } } }))
        .toMatchObject({ origen: 'Por confirmar', destino: 'Por confirmar' });
});

it('Nacional conserva la ruta a sucursal y Tienda su etiqueta incluso con logística heredada', () => {
    expect(presentacionPedidoCedis({ solicitud_preparacion: { modalidad_entrega: 'nacional' }, origen: { requiere_logistica: true }, almacen: { nombre: 'Salida' }, sucursal_destino: { nombre: 'Destino' } }))
        .toMatchObject({ etiqueta: 'Envío:', nombre: 'NACIONAL', origen: 'Salida', destino: 'Destino' });
    expect(presentacionPedidoCedis({ solicitud_preparacion: { modalidad_entrega: 'tienda' }, origen: { requiere_logistica: true, nombre: 'TIENDA' } }))
        .toMatchObject({ etiqueta: 'Tipo de pedido', nombre: 'TIENDA' });
});

it('distingue el municipal histórico y el traslado interno sin inferir por nombres comerciales', () => {
    expect(presentacionPedidoCedis({ solicitud_preparacion: { codigo_modalidad: 'ENVIO_MUNICIPIO' } }).nombre).toBe('MUNICIPAL');
    expect(presentacionPedidoCedis({ origen: { nombre: 'Transporte', requiere_logistica: true } }).nombre).toBe('NACIONAL');
    expect(presentacionPedidoCedis({ origen: { nombre: 'Tienda', requiere_logistica: true }, es_traslado_interno_pdv: true }).etiqueta).toBe('Tipo de pedido');
});
