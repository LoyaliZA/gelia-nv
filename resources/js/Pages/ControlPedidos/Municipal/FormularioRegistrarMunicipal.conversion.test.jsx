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
    window.axios = {
        get: vi.fn(async () => ({ data: pedido })),
        post: vi.fn(async () => ({ data: { ok: true } })),
    };
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
});
afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

const button = (text) => [...document.querySelectorAll('button')].find((el) => el.textContent === text);
const select = async (label, value) => act(() => {
    const el = [...document.querySelectorAll('label')].find((n) => n.textContent.startsWith(label)).querySelector('select');
    el.value = value;
    el.dispatchEvent(new Event('change', { bubbles: true }));
});

it('en modo conversion usa el callback y no registra un alta M-01', async () => {
    const onConfirmarConversion = vi.fn(async () => ({ ok: true, modalidad_entrega: 'municipal' }));
    await act(() => root.render(
        <Formulario
            abierto
            modo="conversion"
            onConfirmarConversion={onConfirmarConversion}
            pedido={pedido}
            catalogos={{ paqueterias: [terminal], paqueterias_municipio: [] }}
        >
            {({ contenido, pie }) => <>{contenido}{pie}</>}
        </Formulario>
    ));
    await select('Terminal destino', '2');
    await act(() => button('Elegir persona ficticia').click());
    await select('Costo de transporte', 'diferido');
    const confirmar = button('Confirmar cambio') || button('Guardar solicitud') || button('Enviar consulta');
    await act(async () => confirmar.click());
    expect(onConfirmarConversion).toHaveBeenCalled();
    const urls = window.axios.post.mock.calls.map((c) => String(c[0]));
    expect(urls.some((url) => url.includes('municipal.solicitud') || url.includes('municipal/44/solicitud'))).toBe(false);
    expect(urls.some((url) => url.includes('municipal.consulta') || url.includes('municipal/44/consulta'))).toBe(false);
});
