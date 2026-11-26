// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/react';
import EmpaqueMunicipioTienda from './EmpaqueMunicipioTienda';

vi.mock('@inertiajs/react', () => ({ router: { post: vi.fn() } }));

let root;
let container;
const apartado = { estado: 'LISTA_PARA_SALIDA', version: 1, bultos_salida: 1 };
const auth = { user: { permissions: ['control_pedidos.tienda.empacar_municipio'] } };

beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = (name, id) => `${name}/${id}`;
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
});

const mount = (requisitos) => act(() => root.render(
    <EmpaqueMunicipioTienda tarea={{ id: 9 }} apartado={apartado} auth={auth} requisitos={requisitos} documentos={[]} />,
));

it('explica que la báscula no bloquea cuando el transporte no exige peso', async () => {
    await mount({ peso_real_obligatorio: false });
    expect(container.textContent).toContain('no exige peso');
    expect(container.textContent).not.toContain('Báscula / peso');
    expect(container.textContent).toContain('Bulto');
});

it('pide la báscula cuando el transporte exige peso', async () => {
    await mount({ peso_real_obligatorio: true });
    expect(container.textContent).toContain('exige peso');
    expect(container.textContent).toContain('Báscula / peso');
});


it('envía cajas y bolsas junto con su total en el recorrido existente', async () => {
    await mount({ peso_real_obligatorio: false });
    const cambiar = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
    const [cajas, bolsas] = container.querySelectorAll('input[type="number"]');
    await act(() => {
        cambiar.call(cajas, '2');
        cajas.dispatchEvent(new Event('input', { bubbles: true }));
        cambiar.call(bolsas, '1');
        bolsas.dispatchEvent(new Event('input', { bubbles: true }));
    });
    expect(container.textContent).toContain('Total de bultos: 3');
    await act(() => container.querySelector('form').requestSubmit());
    expect(router.post).toHaveBeenCalledWith('control_pedidos.tienda.municipio.empacar/9', { cajas: 2, bolsas: 1, bultos: 3, version: 1 }, undefined);
});
