// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
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
