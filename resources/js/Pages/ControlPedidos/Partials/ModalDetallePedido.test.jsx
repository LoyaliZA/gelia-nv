// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, expect, it, vi } from 'vitest';
import ModalDetallePedido from './ModalDetallePedido';
vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: { auth: { user: { id: 1, permissions: [] } } } }) }));
vi.mock('./ModalCambiarDireccion', () => ({ default: () => null }));
vi.mock('./ConsultaSolicitudTienda', () => ({ default: () => <div>Consulta vigente</div> }));
vi.mock('./ReferenciaPedidoCompartido', () => ({ default: () => <div>Pedido compartido</div> }));
vi.mock('./SeccionRevisionFisicaPedido', () => ({ default: () => <div>Revisión física</div> }));
let root, container;
afterEach(async () => { if (root) await act(() => root.unmount()); container?.remove(); });

function montar(pedido) {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = name => name;
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
    return act(() => root.render(<ModalDetallePedido abierto pedido={pedido} />));
}

it('muestra los datos de recolección y conserva los campos logísticos solo para pedidos anteriores', async () => {
    const pedido = {
        id: 1, folio: 'EJEMPLO', cliente: { nombre: 'Cliente' }, documentos: [],
        solicitud_preparacion: { modalidad_entrega: 'tienda', area_revision: 'control_pedidos', cobertura_tienda: { tipo: 'transferencia' } },
        sucursal_destino: { nombre: 'Carmen Cadena' },
    };
    await montar(pedido);
    expect(document.body.textContent).toContain('Recolección en tienda');
    expect(document.body.textContent).toContain('Carmen Cadena');
    expect(document.body.textContent).toContain('Transferencia');
    expect(document.body.querySelector('.gelia-pedidos-navegacion')).toBeNull();
    for (const label of ['Peso real', 'Paquetería', 'Entrega y dirección', 'Reexpedición', 'Tipo caja']) {
        expect(document.body.textContent).not.toContain(label);
    }
    await act(() => root.render(<ModalDetallePedido abierto pedido={{ ...pedido, solicitud_preparacion: null }} />));
    expect(document.body.textContent).toContain('Peso real');
    expect(document.body.textContent).toContain('Entrega y dirección');
});

it('en un envío municipal muestra consignatario, remisión y terminal del snapshot', async () => {
    const pedido = {
        id: 26560, folio: '26560', numero_remision: 'WIZ-26560', paqueteria: { nombre: 'Jaguar' },
        cliente: { nombre: 'Cliente', numero_cliente: 'C-1' }, documentos: [],
        solicitud_preparacion: {
            modalidad_entrega: 'municipal',
            consulta_municipal: { area: 'tienda', estado: 'cerrada' },
            expediente: { nombre: 'Persona receptora', telefono: '9931112233' },
        },
    };
    await montar(pedido);
    expect(document.body.textContent).toContain('Persona receptora');
    expect(document.body.textContent).toContain('9931112233');
    expect(document.body.textContent).toContain('WIZ-26560');
    expect(document.body.textContent).toContain('Jaguar');
    expect(document.body.textContent).toContain('Envío municipal');
    expect(document.body.querySelector('.gelia-pedidos-navegacion')).toBeNull();
    for (const label of ['Peso real', 'Tipo caja', 'Entrega y dirección', 'Reexpedición', 'Peso cobrado', 'Apartado CEDIS', 'C.P.']) {
        expect(document.body.textContent).not.toContain(label);
    }
});

it('en un envío nacional omite terminal municipal y campos vacíos de logística', async () => {
    const pedido = {
        id: 9, folio: 'N-9', cliente: { nombre: 'Cliente' }, documentos: [],
        solicitud_preparacion: { modalidad_entrega: 'nacional', captura_nacional: 'compartida' },
        paqueteria: { nombre: 'DHL' },
        total_a_cobrar: 1200,
    };
    await montar(pedido);
    expect(document.body.textContent).toContain('Envío nacional');
    expect(document.body.textContent).toContain('DHL');
    expect(document.body.textContent).toContain('Entrega y dirección');
    expect(document.body.textContent).not.toContain('Terminal destino');
    expect(document.body.textContent).not.toContain('Consignatario');
    expect(document.body.textContent).not.toContain('Tipo caja');
    expect(document.body.textContent).not.toContain('Peso real');
    expect(document.body.querySelector('.gelia-pedidos-navegacion')).toBeNull();
});
