// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/react';
import Modal from './ModalMarcarEmpacadoBultos';
vi.mock('@inertiajs/react', () => ({ router: { post: vi.fn() } }));
const pedido = { id: 81, folio: 'PBMA-EJEMPLO', folio_remision: '2', requiere_bultos_empaque: true, solicitud_preparacion: { modalidad_entrega: 'tienda', cobertura_tienda: { tipo: 'transferencia' } } };
let root, container;
const button = texto => [...document.querySelectorAll('button')].find(b => b.textContent.trim() === texto);
async function foto(numero = 0, archivo = new File(['foto'], 'bolsa.jpg', { type: 'image/jpeg' })) {
    await act(() => {
        const input = document.querySelectorAll('input[capture]')[numero];
        Object.defineProperty(input, 'files', { configurable: true, value: [archivo] });
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
}
beforeEach(() => {
    vi.clearAllMocks(); globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = (nombre, id) => `/${nombre}/${id}`;
    vi.spyOn(URL, 'createObjectURL').mockImplementation(() => `blob:${Math.random()}`);
    vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
    container = document.createElement('div'); document.body.append(container); root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount()); container.remove(); vi.restoreAllMocks(); });
it('transferencia y crédito muestran solo bolsas y envían únicamente su foto', async () => {
    for (const tipo of ['transferencia', 'credito']) {
        await act(() => root.render(<Modal abierto pedido={{ ...pedido, id: tipo === 'credito' ? 82 : 81, solicitud_preparacion: { ...pedido.solicitud_preparacion, cobertura_tienda: { tipo } } }} onClose={vi.fn()} />));
        expect(document.body.textContent).not.toMatch(/ticket/i);
        expect(document.querySelectorAll('input[capture="environment"]')).toHaveLength(1);
        await act(() => button('Marcar empacado').click());
        expect(router.post).not.toHaveBeenCalled();
        expect(document.querySelector('[role="alert"]').textContent).toContain('Toma la foto de la bolsa 1');
        await foto();
        await act(() => button('Marcar empacado').click());
        const [, datos, opciones] = router.post.mock.lastCall;
        expect([...datos.keys()]).toEqual([`bultos_por_pedido[${tipo === 'credito' ? 82 : 81}][0][foto_bulto]`]);
        expect(document.querySelectorAll('button:disabled').length).toBeGreaterThan(0);
        await act(() => opciones.onFinish());
        router.post.mockClear();
    }
});
it('agrega y retira bolsas sin invalidar previews de las bolsas conservadas', async () => {
    await act(() => root.render(<Modal abierto pedido={pedido} onClose={vi.fn()} />));
    await foto();
    const url = document.querySelector('.gelia-empaque-camara img').src;
    await act(() => button('Agregar otra bolsa').click());
    expect(URL.revokeObjectURL).not.toHaveBeenCalledWith(url);
    await foto(1);
    await act(() => document.querySelector('[aria-label="Quitar bolsa 2"]').click());
    expect(document.querySelectorAll('.gelia-empaque-camara img')).toHaveLength(1);
    expect(URL.revokeObjectURL).not.toHaveBeenCalledWith(url);
    await act(() => button('Marcar empacado').click());
    expect([...router.post.mock.lastCall[1].keys()]).toHaveLength(1);
});
it('rechaza un archivo distinto de imagen y conserva los requisitos del empaque legado', async () => {
    await act(() => root.render(<Modal abierto pedido={pedido} onClose={vi.fn()} />));
    await foto(0, new File(['pdf'], 'ticket.pdf', { type: 'application/pdf' }));
    expect(document.querySelector('[role="alert"]').textContent).toContain('Usa una foto');
    expect(document.querySelector('.gelia-empaque-camara img')).toBeNull();
    await act(() => root.render(<Modal abierto pedido={{ ...pedido, id: 83, solicitud_preparacion: null }} onClose={vi.fn()} />));
    expect(document.body.textContent).toContain('Foto del ticket');
    await foto();
    await act(() => button('Marcar empacado').click());
    expect(router.post).not.toHaveBeenCalled();
});
