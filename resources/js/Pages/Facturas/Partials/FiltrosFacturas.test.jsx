// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import FiltrosFacturas from './FiltrosFacturas';
let container, root;
beforeEach(() => { globalThis.IS_REACT_ACT_ENVIRONMENT = true; container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
const cambiar = (id, value) => act(() => { const input = document.getElementById(id); if (input.tagName === 'INPUT') Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, value); else input.value = value; input.dispatchEvent(new Event(input.tagName === 'INPUT' ? 'input' : 'change', { bubbles: true })); });
it('aplica departamento y área junto a la búsqueda y reinicia la página', async () => {
    const apply = vi.fn(); await act(() => root.render(<FiltrosFacturas filtros={{ q: 'ABC' }} departamentos={[{ id: 2, nombre: 'Ventas' }]} areas={[{ id: 4, nombre: 'Mayoreo' }]} onAplicarFiltros={apply} />));
    await cambiar('facturas-departamento_id', '2'); await cambiar('facturas-area_id', '4');
    await act(() => container.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    expect(apply).toHaveBeenCalledWith(expect.objectContaining({ q: 'ABC', departamento_id: '2', area_id: '4', page: 1 }));
});
it('rechaza un rango invertido y permite fechas de un solo extremo', async () => {
    const apply = vi.fn(); await act(() => root.render(<FiltrosFacturas onAplicarFiltros={apply} />));
    await cambiar('facturas-fecha_inicio', '2026-10-12'); await cambiar('facturas-fecha_fin', '2026-10-01');
    await act(() => container.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    expect(apply).not.toHaveBeenCalled(); expect(container.querySelector('[role="alert"]')).not.toBeNull();
    await cambiar('facturas-fecha_fin', ''); await act(() => container.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    expect(apply).toHaveBeenCalledWith(expect.objectContaining({ fecha_inicio: '2026-10-12', fecha_fin: undefined }));
});
it('limpia todas las facetas explícitamente sin reutilizar valores anteriores', async () => {
    const apply = vi.fn(); await act(() => root.render(<FiltrosFacturas filtros={{ departamento_id: '2', area_id: '4', vendedor_id: '1', q: 'ABC', fecha_inicio: '2026-10-01' }} onAplicarFiltros={apply} />));
    await act(() => [...container.querySelectorAll('button')].find((b) => b.textContent === 'Limpiar filtros').click());
    expect(apply).toHaveBeenCalledWith({ departamento_id: undefined, area_id: undefined, vendedor_id: undefined, q: undefined, fecha_inicio: undefined, fecha_fin: undefined, page: 1 });
});
