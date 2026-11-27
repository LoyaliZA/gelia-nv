// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import FormularioRegistrarNacional from './FormularioRegistrarNacional';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }));
vi.mock('../../../Components/Almacenes/SelectorProducto', () => ({ default: () => null }));
vi.mock('../Partials/GaleriaEvidenciasPedido', () => ({ default: () => null }));
vi.mock('./SeguimientoSolicitudNacional', () => ({ default: () => <p>Seguimiento</p> }));
vi.mock('./ContinuacionEnvioNacional', () => ({ default: () => <p>Cotización y pago</p> }));

const catalogos = { paqueterias: [{ id: 8, nombre: 'DHL', categoria: 'comercial', activo: true }] };
const caja = (n) => ({ id: n, uuid_operativo: `c${n}`, orden: n, estado_operativo: 'activa' });
const cerrado = (extra = {}) => ({
    id: 9, folio: 'PBMA-9', folio_remision: 'WIZ-1', total_mercancia: '1500.00', catalogo_paqueteria_id: 8,
    pesaje_solicitado_at: '2026-11-27T10:00:00Z', pesaje_respondido_at: '2026-11-27T11:00:00Z',
    consulta_cerrada_at: '2026-11-27T12:00:00Z', estatus: { fase_ciclo: 'PESAJE_RESPONDIDO' },
    cajas: [caja(1), caja(2), caja(3)], revision_registro: 2, base_captura: 'a'.repeat(64), ...extra,
});
const evaluacion = (extra = {}) => ({ elegible: true, motivo: null, n_cajas: 3, revision: 'rev-1', estado: null, multienvio_id: null, ...extra });
const proyeccion = (extra = {}) => ({ evaluacion: evaluacion(), grupo: null, envios: [], totales: {}, progreso: {}, parcial: false, cajas_origen: null, cajas_resumen: null, ...extra });

let host;
let root;
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
    vi.resetAllMocks();
});
afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

async function render(pedido, props = {}) {
    await act(async () => root.render(
        <FormularioRegistrarNacional pedido={pedido} catalogos={catalogos} guardar={vi.fn()} onPedido={vi.fn()} {...props}>
            {({ contenido }) => <div>{contenido}</div>}
        </FormularioRegistrarNacional>,
    ));
}
const boton = (texto) => [...host.querySelectorAll('button')].find((b) => b.textContent.includes(texto));

it('retiene la cotización mientras la decisión está pendiente y la muestra tras continuar sin seguro', async () => {
    axios.get.mockResolvedValue({ data: proyeccion() });
    await render(cerrado());
    expect(axios.get).toHaveBeenCalledWith('/control-pedidos/9/multienvio', expect.anything());
    expect(host.textContent).toContain('Multienvío detectado');
    expect(host.textContent).not.toContain('Cotización y pago');

    axios.post.mockResolvedValue({ data: proyeccion({ evaluacion: evaluacion({ estado: 'estandar_sin_seguro' }), grupo: { id: 4, estado: 'estandar_sin_seguro', decision: 'sin_seguro' } }) });
    await act(async () => boton('Continuar sin seguro').click());
    expect(host.textContent).toContain('Cotización y pago');
    expect(host.textContent).not.toContain('Multienvío detectado');
    expect(boton('Activar multienvío con seguro')).toBeTruthy();
});

it('con una sola caja no consulta el multienvío ni muestra el aviso', async () => {
    await render(cerrado({ cajas: [caja(1)] }));
    expect(axios.get).not.toHaveBeenCalled();
    expect(host.textContent).not.toContain('Multienvío detectado');
    expect(host.textContent).toContain('Cotización y pago');
});

it('con la consulta abierta no consulta el multienvío', async () => {
    await render(cerrado({ consulta_cerrada_at: null }));
    expect(axios.get).not.toHaveBeenCalledWith('/control-pedidos/9/multienvio', expect.anything());
    expect(host.textContent).not.toContain('Multienvío detectado');
});

it('al confirmar relee la consulta nacional, la adopta y muestra los envíos', async () => {
    const cajasOrigen = [1, 2, 3].map((n) => ({ uuid_operativo: `c${n}`, orden: n, tipo: 'Caja', evidencias: [] }));
    const activo = proyeccion({
        evaluacion: evaluacion({ elegible: false, estado: 'activo' }),
        grupo: { id: 4, estado: 'activo', decision: 'activar' },
        envios: [1, 2, 3].map((n) => ({ id: 8 + n, orden: n, folio: `PBMA-${8 + n}`, folio_remision: `WZ-${n}`, estado_pago: 'sin_pago' })),
    });
    const lecturaConsulta = { id: 9, total_mercancia: '600.00', cajas: [caja(1)], multienvio: { id: 4, orden: 1, n_total: 3, estado: 'activo', parcial: false, hermanos: [] } };
    axios.get.mockImplementation(async (url) => (url.endsWith('/consulta-nacional')
        ? { data: lecturaConsulta }
        : { data: axios.post.mock.calls.length ? activo : proyeccion({ evaluacion: evaluacion({ estado: 'configurando' }), grupo: { id: 4, estado: 'configurando', borrador: [
            { uuid_operativo: 'c1', folio_remision: 'WZ-1', total_mercancia: 600 },
            { uuid_operativo: 'c2', folio_remision: 'WZ-2', total_mercancia: 500 },
            { uuid_operativo: 'c3', folio_remision: 'WZ-3', total_mercancia: 400 },
        ] }, cajas_origen: cajasOrigen }) }));
    axios.post.mockResolvedValue({ data: activo });
    const onPedido = vi.fn();
    await render(cerrado(), { onPedido });
    expect(host.textContent).not.toContain('Cotización y pago');
    expect(boton('Confirmar multienvío').disabled).toBe(false);

    await act(async () => boton('Confirmar multienvío').click());
    expect(axios.post).toHaveBeenCalledWith('/control-pedidos/9/multienvio/confirmar', { revision: 'rev-1' }, expect.anything());
    expect(axios.get).toHaveBeenCalledWith('/control-pedidos/9/consulta-nacional', expect.anything());
    expect(onPedido).toHaveBeenLastCalledWith(expect.objectContaining({ total_mercancia: '600.00', multienvio: expect.objectContaining({ estado: 'activo' }) }));
    expect(host.textContent).toContain('Folio Wizerp WZ-3');
    expect(host.textContent).toContain('Cotización y pago');
});

it('suma la captura sin guardar del multienvío a la suciedad del formulario', async () => {
    const cajasOrigen = [1, 2].map((n) => ({ uuid_operativo: `c${n}`, orden: n, evidencias: [] }));
    axios.get.mockResolvedValue({ data: proyeccion({ evaluacion: evaluacion({ estado: 'configurando' }), grupo: { id: 4, estado: 'configurando', borrador: null }, cajas_origen: cajasOrigen }) });
    const onDirtyChange = vi.fn();
    await render(cerrado({ cajas: [caja(1), caja(2)] }), { onDirtyChange });
    const input = host.querySelector('#multienvio-c1-folio');
    await act(async () => {
        Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set.call(input, 'WZ-9');
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    expect(onDirtyChange).toHaveBeenLastCalledWith(true);
});
