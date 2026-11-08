// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import FiltrosBandejaOperativa from './FiltrosBandejaOperativa';
import useFiltrosOperacion from './useFiltrosOperacion';
const cargar = vi.fn();
let root, container;
function Bandeja() {
    const { valores, cambiar, limpiar, params } = useFiltrosOperacion({}, cargar);
    return <><FiltrosBandejaOperativa id="qa" fechaHoy="2026-11-08" valores={valores} onCambiar={cambiar} onLimpiar={limpiar} onActualizar={() => cargar(params({ page: 2 }))} /><button onClick={() => cambiar({ tab: 'ATENDIDOS' })}>Atendidos</button></>;
}
beforeEach(async () => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    vi.useFakeTimers(); cargar.mockReset();
    container = document.createElement('div'); document.body.append(container); root = createRoot(container);
    await act(async () => root.render(<Bandeja />));
});
afterEach(async () => { await act(() => root.unmount()); container.remove(); vi.useRealTimers(); });
const boton = texto => [...container.querySelectorAll('button')].find(b => b.textContent === texto);
it('Hoy usa la fecha operativa y los filtros se conservan al cambiar bandeja y página', async () => {
    await act(async () => boton('Hoy').click());
    expect(cargar).toHaveBeenLastCalledWith(expect.objectContaining({ tab: 'TODOS', desde: '2026-11-08', hasta: '2026-11-08', page: 1 }));
    await act(async () => boton('Atendidos').click());
    await act(async () => container.querySelector('[aria-label="Actualizar pedidos"]').click());
    expect(cargar).toHaveBeenLastCalledWith(expect.objectContaining({ tab: 'ATENDIDOS', desde: '2026-11-08', hasta: '2026-11-08', page: 2 }));
});
it('al cambiar bandeja cancela la búsqueda diferida conservando el nombre escrito', async () => {
    const campo = container.querySelector('#qa-vendedora');
    await act(async () => {
        Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(campo, 'Ana');
        campo.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await act(async () => boton('Atendidos').click());
    await act(async () => vi.advanceTimersByTime(400));
    expect(cargar).toHaveBeenCalledTimes(1);
    expect(cargar).toHaveBeenLastCalledWith(expect.objectContaining({ tab: 'ATENDIDOS', vendedora: 'Ana' }));
});
it('Más antiguos quita el rango de Hoy y Limpiar restaura Todos', async () => {
    await act(async () => boton('Hoy').click());
    await act(async () => boton('Más antiguos').click());
    expect(cargar).toHaveBeenLastCalledWith(expect.objectContaining({ desde: '', hasta: '', orden: 'antiguos' }));
    await act(async () => boton('Atendidos').click());
    await act(async () => boton('Limpiar filtros').click());
    expect(cargar).toHaveBeenLastCalledWith(expect.objectContaining({ tab: 'TODOS', q: '', vendedora: '', orden: 'antiguos', page: 1 }));
});
