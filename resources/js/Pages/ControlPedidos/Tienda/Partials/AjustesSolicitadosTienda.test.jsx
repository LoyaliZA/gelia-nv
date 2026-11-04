// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, it, expect, vi } from 'vitest';
import AjustesSolicitadosTienda from './AjustesSolicitadosTienda';
const post = vi.hoisted(() => vi.fn());
vi.mock('@inertiajs/react', () => ({ router: { post } }));
vi.mock('../../../../Components/Almacenes/SelectorProducto', () => ({ default: () => <span>Buscar producto</span> }));
let root, container;
const tarea = { id: 4, estado: 'RESPONDIDA', puede_preparar: true, pedido: { retiros: [{ version: 2, estado: 'pendiente', motivo: 'Petición del cliente', piezas: [{ sku: 'A', descripcion: 'Aroma', cantidad: 1 }] }], anexos_pendientes: [] } };
const render = (value, props = {}) => act(() => root.render(<AjustesSolicitadosTienda tarea={value} auth={{ user: { id: 2 } }} {...props} />));
beforeEach(() => { globalThis.IS_REACT_ACT_ENVIRONMENT = true; globalThis.route = (name, id) => `${name}/${id}`; post.mockReset(); container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
it('permite atender el retiro solicitado después de responder el apartado', async () => {
    await render(tarea);
    const aplicar = [...container.querySelectorAll('button')].find((b) => b.textContent === 'Aplicar retiro');
    await act(() => aplicar.click());
    expect(post).toHaveBeenCalledWith('control_pedidos.tienda.retiro.atender/4', expect.objectContaining({ version: 2, decision: 'aplicar' }), expect.any(Object));
});
it('sin ajustes no ofrece acciones y nunca abre una nueva respuesta general', async () => {
    await render({ ...tarea, pedido: { retiros: [], anexos_pendientes: [] } });
    expect(container.querySelector('button')).toBeNull();
});
it('una tarea sustituida o un auxiliar sin facultad no puede responder ajustes', async () => {
    await render({ ...tarea, estado: 'SUSTITUIDA' });
    expect(container.querySelector('button')).toBeNull();
    await render({ ...tarea, puede_preparar: false, puede_responder: false });
    expect(container.querySelector('button')).toBeNull();
});
it('el anexo pendiente tiene respuesta propia y valida el faltante antes de enviar', async () => {
    await render({ ...tarea, pedido: { anexos_pendientes: [{ indice: 0, entrada: 'documento' }] } });
    const responder = [...container.querySelectorAll('button')].find((b) => b.textContent === 'Reportar faltante o detalle');
    await act(() => responder.click());
    expect(post).not.toHaveBeenCalled();
    expect(container.textContent).toContain('Indique el faltante');
    await act(() => {
        const nota = container.querySelector('textarea');
        Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set.call(nota, 'No disponible');
        nota.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await act(() => responder.click());
    expect(post.mock.calls[0][0]).toBe('control_pedidos.tienda.anexo.atender/4');
    expect(post.mock.calls[0][1].get('nota')).toBe('No disponible');
});

it('avisa de una captura pendiente y deja de bloquear la actualización cuando se resuelve el ajuste', async () => {
    const onDirtyChange = vi.fn();
    const pendiente = { ...tarea, pedido: { anexos_pendientes: [{ indice: 0, entrada: 'documento' }] } };
    await render(pendiente, { onDirtyChange });
    expect(onDirtyChange).toHaveBeenLastCalledWith(false);
    await act(() => {
        const nota = container.querySelector('textarea');
        Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set.call(nota, 'No disponible');
        nota.dispatchEvent(new Event('input', { bubbles: true }));
    });
    expect(onDirtyChange).toHaveBeenLastCalledWith(true);
    await render({ ...tarea, pedido: { anexos_pendientes: [], retiros: [] } }, { onDirtyChange });
    expect(onDirtyChange).toHaveBeenLastCalledWith(false);
});
