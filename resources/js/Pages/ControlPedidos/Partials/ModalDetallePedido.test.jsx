// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, expect, it, vi } from 'vitest';
import ModalDetallePedido from './ModalDetallePedido';
vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: { auth: { user: { id: 1, permissions: [] } } } }) }));
vi.mock('./ModalCambiarDireccion', () => ({ default: () => null }));
vi.mock('./ConsultaSolicitudTienda', () => ({ default: () => <div>Consulta vigente</div> }));
vi.mock('./ReferenciaPedidoCompartido', () => ({ default: () => <div>Pedido compartido</div> }));
let root, container;
afterEach(async () => { if (root) await act(() => root.unmount()); container?.remove(); });
it('muestra los datos de recolección y conserva los campos logísticos solo para pedidos anteriores', async () => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = name => name;
    container = document.createElement('div'); document.body.append(container); root = createRoot(container);
    const pedido = { id: 1, folio: 'EJEMPLO', cliente: { nombre: 'Cliente' }, documentos: [], solicitud_preparacion: { modalidad_entrega: 'tienda', area_revision: 'control_pedidos', cobertura_tienda: { tipo: 'transferencia' } }, sucursal_destino: { nombre: 'Carmen Cadena' } };
    await act(() => root.render(<ModalDetallePedido abierto pedido={pedido} />));
    expect(document.body.textContent).toContain('Recolección en tienda');
    expect(document.body.textContent).toContain('Carmen Cadena');
    expect(document.body.textContent).toContain('Transferencia');
    for (const label of ['Peso real', 'Paquetería', 'Entrega y dirección', 'Reexpedición', 'Tipo caja']) expect(document.body.textContent).not.toContain(label);
    await act(() => root.render(<ModalDetallePedido abierto pedido={{ ...pedido, solicitud_preparacion: null }} />));
    expect(document.body.textContent).toContain('Peso real');
    expect(document.body.textContent).toContain('Entrega y dirección');
});
