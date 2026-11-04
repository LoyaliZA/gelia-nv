// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ModalFormPedido from './ModalFormPedido';
vi.mock('./ModalFormPedidoLegado', () => ({ default: ({ pedido }) => <div data-form="legado">{pedido?.id}</div>, hayBorradorPedidoLocal: () => false }));
vi.mock('./FormularioRegistrarTienda', () => ({ default: ({ pedido, onContinuarEnvio }) => <div data-form="actual">{pedido?.id}<button onClick={() => onContinuarEnvio({ ...pedido, solicitud_preparacion: { modalidad_entrega: 'nacional' } })}>Continuar envío</button></div> }));
let root, container;
beforeEach(() => { globalThis.IS_REACT_ACT_ENVIRONMENT = true; container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
describe('Modal de edición según la captura del pedido', () => {
    it('abre el borrador de la captura en el registro actual conservando su ID', async () => {
        await act(() => root.render(<ModalFormPedido abierto pedido={{ id: 125, estatus: { fase_ciclo: 'BORRADOR' }, solicitud_preparacion: null, origen: null }} />));
        expect(container.querySelector('[data-form="actual"]').textContent).toContain('125');
        await act(() => container.querySelector('button').click());
        expect(container.querySelector('[data-form="legado"]').textContent).toBe('125');
    });
    it('mantiene recuperación del borrador local anterior', async () => {
        await act(() => root.render(<ModalFormPedido abierto recuperarBorrador />));
        expect(container.querySelector('[data-form="legado"]')).toBeTruthy();
    });
    it('mantiene la edición de un pedido emitido del piloto', async () => {
        await act(() => root.render(<ModalFormPedido abierto pedido={{ id: 9, estatus: { fase_ciclo: 'EN_CEDIS' }, solicitud_preparacion: null }} />));
        expect(container.querySelector('[data-form="legado"]').textContent).toBe('9');
    });
});
