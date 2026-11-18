// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Formulario from './FormularioRegistrarMunicipal';

vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: { auth: { user: { id: 1 } } } }) }));
vi.mock('./ExpedienteConsignatario', () => ({ default: ({ onSeleccionar }) => <button onClick={() => onSeleccionar(12)}>Elegir persona ficticia</button> }));
vi.mock('../Partials/ModalVistaPreviaDocumento', () => ({ default: () => null }));
vi.mock('../Partials/AccionesRapidasDisponibilidad', () => ({ default: () => null }));

let root;
let host;
const terminal = { id: 2, nombre: 'Terminal ficticia', categoria: 'local_regional', sin_logistica: false, permite_costo_diferido: true, permite_por_cobrar: true };
const pedido = { id: 44, folio: 'PBMA-44', solicitud_preparacion: { modalidad_entrega: 'tienda' } };

beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    sessionStorage.clear();
    window.route = (name, id) => `${name}/${id}`;
    window.axios = { get: vi.fn(), post: vi.fn() };
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
});

afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

it('en conversión reusa el formulario, llama persistirSolicitud y no da de alta M-01', async () => {
    const persistirSolicitud = vi.fn(async (payload) => payload);
    let captura;
    await act(() => root.render(
        <Formulario
            abierto
            modo="conversion"
            persistirSolicitud={persistirSolicitud}
            pedido={pedido}
            catalogos={{ paqueterias: [terminal], almacenes: [] }}
        >
            {({ contenido, pie, captura: campos }) => {
                captura = campos;
                return <>{contenido}{pie}</>;
            }}
        </Formulario>,
    ));
    expect(document.body.textContent).toContain('Terminal destino');
    expect(document.body.textContent).not.toContain('Enviar consulta');
    expect(document.body.textContent).not.toContain('Guardar solicitud');
    expect(document.body.textContent).not.toContain('Seguimiento · Envío municipal');
    const select = [...document.querySelectorAll('label')].find((el) => el.textContent.startsWith('Terminal destino')).querySelector('select');
    await act(() => { select.value = '2'; select.dispatchEvent(new Event('change', { bubbles: true })); });
    await act(() => [...document.querySelectorAll('button')].find((el) => el.textContent === 'Elegir persona ficticia').click());
    expect(captura).toMatchObject({ catalogo_paqueteria_id: 2, consignatario_id: 12 });
    expect(window.axios.post).not.toHaveBeenCalled();
});
