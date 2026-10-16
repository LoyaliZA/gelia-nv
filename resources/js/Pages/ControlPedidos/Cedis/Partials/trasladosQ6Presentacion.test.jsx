// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import ModalDetalleCedis from './ModalDetalleCedis';
import { empacarRequiereModalBultos } from '../../Partials/pedidosBmaStyles';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { auth: { user: { permissions: [], roles: [] } } } }),
    router: { post: vi.fn() },
}));
let root;
let container;
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    vi.stubGlobal('route', () => '/q6');
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
});
const base = { id: 6, folio: 'Q6', origen: { requiere_logistica: true }, estatus: { fase_ciclo: 'EN_CEDIS' },
    documentos: [], cajas: [], numero_cajas: 2, cliente: { nombre: 'Cliente Q6' },
    revisiones_producto: [{ id: 1, descripcion_producto: 'Pieza revisada', estado_fisico: 'bueno', cantidad_solicitada: 3, cantidad_encontrada: 2, comentario: 'Falta una' }] };
it('detalle interno muestra cantidades y bultos y no presenta peso ni guía comercial', async () => {
    const pedido = { ...base, es_traslado_interno_pdv: true, requiere_bultos_empaque: true };
    await act(() => root.render(<ModalDetalleCedis abierto pedido={pedido} onClose={() => {}} />));
    expect(document.body.textContent).toContain('Traslado interno');
    expect(document.body.textContent).toContain('Solicitadas: 3 · Encontradas: 2 · Faltantes: 1');
    expect(document.body.textContent).toContain('Bultos a preparar');
    expect(document.body.textContent).not.toContain('Origen de la guía');
    expect(document.body.textContent).not.toContain('Peso real');
    expect(empacarRequiereModalBultos(pedido)).toBe(true);
});
it('detalle muestra badges de única pieza y mejor ejemplar', async () => {
    const pedido = { ...base, revisiones_producto: [{ id: 2, descripcion_producto: 'Pieza marcada', estado_fisico: 'bueno', unica_pieza: true, mejor_ejemplar: true }] };
    await act(() => root.render(<ModalDetalleCedis abierto pedido={pedido} onClose={() => {}} />));
    expect(document.body.textContent).toContain('Única pieza');
    expect(document.body.textContent).toContain('Mejor ejemplar');
});
it('detalle externo conserva campos de peso y guía', async () => {
    await act(() => root.render(<ModalDetalleCedis abierto pedido={{ ...base, es_traslado_interno_pdv: false }} onClose={() => {}} />));
    expect(document.body.textContent).toContain('Origen de la guía');
    expect(document.body.textContent).toContain('Peso real');
    expect(empacarRequiereModalBultos(base)).toBe(false);
});
