import { expect, it } from 'vitest';
import { destinoDomicilioNacional, presentacionPedidoCedis } from './presentacionPedidoCedis';

it('municipal muestra el almacén y terminal reales sin confundir sucursal con terminal', () => {
    expect(presentacionPedidoCedis({ solicitud_preparacion: { modalidad_entrega: 'municipal' }, almacen: { nombre: 'CEDIS Norte' }, paqueteria: { nombre: 'Taxi Tacotalpa' }, sucursal_destino: { nombre: 'PDV' } }))
        .toMatchObject({ etiqueta: 'Envío:', nombre: 'MUNICIPAL', origen: 'CEDIS Norte', destino: 'Taxi Tacotalpa', tipoDestino: 'terminal' });
});

it('no inventa almacén ni terminal cuando solo se conoce el área', () => {
    expect(presentacionPedidoCedis({ solicitud_preparacion: { modalidad_entrega: 'municipal', consulta_municipal: { area: 'cedis' } } }))
        .toMatchObject({ origen: 'Por confirmar', destino: 'Por confirmar' });
});

it('Nacional no usa sucursal como destino; Tienda conserva su etiqueta', () => {
    expect(presentacionPedidoCedis({
        solicitud_preparacion: { modalidad_entrega: 'nacional' },
        origen: { requiere_logistica: true },
        almacen: { nombre: 'Salida' },
        sucursal_destino: { nombre: 'Destino' },
    })).toMatchObject({ etiqueta: 'Envío:', nombre: 'NACIONAL', origen: 'Salida', destino: 'Domicilio por confirmar', tipoDestino: 'domicilio' });
    expect(presentacionPedidoCedis({ solicitud_preparacion: { modalidad_entrega: 'tienda' }, origen: { requiere_logistica: true, nombre: 'TIENDA' } }))
        .toMatchObject({ etiqueta: 'Tipo de pedido', nombre: 'TIENDA' });
});

it('distingue el municipal histórico y el traslado interno sin inferir por nombres comerciales', () => {
    expect(presentacionPedidoCedis({ solicitud_preparacion: { codigo_modalidad: 'ENVIO_MUNICIPIO' } }).nombre).toBe('MUNICIPAL');
    expect(presentacionPedidoCedis({ origen: { nombre: 'Transporte', requiere_logistica: true } }).nombre).toBe('NACIONAL');
    expect(presentacionPedidoCedis({ origen: { nombre: 'Tienda', requiere_logistica: true }, es_traslado_interno_pdv: true }).etiqueta).toBe('Tipo de pedido');
});

it('Nacional con domicilio capturado muestra quien recibe y el lugar, no sucursal', () => {
    const base = { solicitud_preparacion: { modalidad_entrega: 'nacional' }, almacen: { nombre: 'CEDIS' } };
    expect(presentacionPedidoCedis({ ...base, direccion_vigente: { municipio: 'Cuauhtémoc', estado: 'Ciudad de México' } }).destino)
        .toBe('Cuauhtémoc, Ciudad de México');
    expect(presentacionPedidoCedis({
        ...base,
        sucursal_destino: { nombre: 'PDV' },
        direccion_vigente: { nombre_destinatario: 'Ana Ruiz', municipio: 'Cuauhtémoc', estado: 'Ciudad de México' },
    }).destino).toBe('Ana Ruiz · Cuauhtémoc, Ciudad de México');
    expect(presentacionPedidoCedis(base).destino).toBe('Domicilio por confirmar');
});

it('Nacional con recolección en sucursal sí muestra la sucursal', () => {
    expect(presentacionPedidoCedis({
        solicitud_preparacion: { modalidad_entrega: 'nacional', cambio_a_tienda: { sucursal_destino_id: 4 } },
        sucursal_destino: { nombre: 'Sucursal centro' },
        almacen: { nombre: 'CEDIS' },
    })).toMatchObject({ destino: 'Sucursal centro', tipoDestino: 'sucursal' });
});

it('el subtítulo de Ventas antepone Recibe cuando hay nombre', () => {
    const pedido = {
        solicitud_preparacion: { modalidad_entrega: 'nacional' },
        direccion_vigente: { nombre_destinatario: 'Ana Ruiz', municipio: 'Centro', estado: 'Tabasco' },
    };
    expect(destinoDomicilioNacional(pedido, { prefijoRecibe: true })).toBe('Recibe: Ana Ruiz · Centro, Tabasco');
    expect(destinoDomicilioNacional({ solicitud_preparacion: { modalidad_entrega: 'nacional' } }, { prefijoRecibe: true }))
        .toBe('Domicilio por confirmar');
});
