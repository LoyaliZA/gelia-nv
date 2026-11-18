// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { accionesPedidoLista, agruparAccionesPedido, GRUPOS_ACCIONES_PEDIDO } from './pedidosListadoUi';
import { MenuAccionesPedido } from './TablaPedidos';

vi.mock('@inertiajs/react', () => ({ router: { post: vi.fn() } }));

let root;
let host;

beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
});

afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

const pedidoTienda = {
    id: 4,
    folio: 'PBMA-4',
    puede_mutar: true,
    solicitud_preparacion: { modalidad_entrega: 'tienda' },
};

it('agrupa consulta, operaciones y administración con encabezados', async () => {
    const acciones = [
        { key: 'ver', label: 'Ver', onClick: () => {} },
        { key: 'bitacora', label: 'Bitácora', onClick: () => {} },
        { key: 'editar', label: 'Editar', onClick: () => {} },
        { key: 'conversion-modalidad', label: 'Cambiar modalidad', onClick: () => {} },
        { key: 'cancelar', label: 'Cancelar', onClick: () => {} },
        { key: 'eliminar-registro', label: 'Eliminar registro', onClick: () => {} },
    ];
    const grupos = agruparAccionesPedido(acciones);
    expect(grupos.map((g) => g.label)).toEqual([
        GRUPOS_ACCIONES_PEDIDO.consulta,
        GRUPOS_ACCIONES_PEDIDO.operaciones,
        GRUPOS_ACCIONES_PEDIDO.administracion,
    ]);
    await act(() => root.render(
        <MenuAccionesPedido acciones={acciones} primaria={acciones[2]} idBase="pedido-4" />,
    ));
    await act(() => document.getElementById('pedido-4-mas').click());
    const menu = document.querySelector('[role="menu"]');
    expect(menu.textContent).toContain('Consulta y seguimiento');
    expect(menu.textContent).toContain('Operaciones');
    expect(menu.textContent).toContain('Administración');
    expect(menu.textContent).toContain('Cambiar modalidad');
    expect(menu.textContent).not.toContain('Editar');
    const items = [...menu.querySelectorAll('[role="menuitem"]')];
    expect(items.every((item) => item.tagName === 'BUTTON')).toBe(true);
    await act(() => items[0].dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true })));
    expect(document.activeElement).toBe(items[1]);
});

it('ofrece cambiar modalidad con flags de servidor sin exigir custodia', () => {
    const onCambiarModalidad = vi.fn();
    const acciones = accionesPedidoLista({
        can: () => true,
        onCambiarModalidad,
        pedido: {
            ...pedidoTienda,
            conversion_modalidad: { puede_iniciar: true, puede_continuar: false },
        },
    });
    expect(acciones.map((a) => a.label)).toContain('Cambiar modalidad');
    acciones.find((a) => a.key === 'conversion-modalidad').onClick();
    expect(onCambiarModalidad).toHaveBeenCalledWith(expect.objectContaining({ id: 4 }));
    expect(accionesPedidoLista({
        can: () => true,
        onCambiarModalidad,
        pedido: pedidoTienda,
    }).some((a) => a.key === 'conversion-modalidad')).toBe(false);
});
