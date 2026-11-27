// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import PanelMultienvioNacional, { centavos, multienvioBloqueaCotizacion } from './PanelMultienvioNacional';
import useMultienvioNacional from './useMultienvioNacional';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }));

const cajas = [
    { uuid_operativo: 'c1', orden: 1, tipo: 'Caja chica', peso_cobrado_kg: '5.000', largo: 40, ancho: 30, alto: 20, evidencias: [{ id: 70, url: '/docs/70' }] },
    { uuid_operativo: 'c2', orden: 2, tipo: 'Caja chica', peso_cobrado_kg: '4.000', largo: 40, ancho: 30, alto: 20, evidencias: [] },
    { uuid_operativo: 'c3', orden: 3, tipo: 'Caja mediana', peso_cobrado_kg: '7.500', largo: 50, ancho: 40, alto: 30, evidencias: [] },
];
const evaluacion = (extra = {}) => ({ elegible: true, motivo: null, n_cajas: 3, revision: 'rev-1', estado: null, multienvio_id: null, ...extra });
const proyeccion = (extra = {}) => ({
    evaluacion: evaluacion(), grupo: null, envios: [], totales: {}, progreso: {}, parcial: false,
    cajas_origen: null, cajas_resumen: { n: 3, tipos: ['Caja chica', 'Caja mediana'], peso_total_kg: 16.5 }, ...extra,
});
const configurando = (borrador = null, extra = {}) => proyeccion({
    evaluacion: evaluacion({ estado: 'configurando', multienvio_id: 4 }),
    grupo: { id: 4, estado: 'configurando', decision: 'activar', borrador, regularizacion: null },
    cajas_origen: cajas, cajas_resumen: null, ...extra,
});
const pedido = { id: 9, total_mercancia: '1500.00' };

let host;
let root;
let estado;
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    vi.useRealTimers();
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
    vi.resetAllMocks();
});
afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
    vi.useRealTimers();
});

function Prueba({ onDirtyChange, onVer }) {
    const multienvio = useMultienvioNacional({ pedidoId: 9, habilitado: true });
    estado = multienvio;

    return <PanelMultienvioNacional pedido={pedido} multienvio={multienvio} onDirtyChange={onDirtyChange} onVer={onVer} />;
}

async function render(lectura, props = {}) {
    axios.get.mockResolvedValue({ data: lectura });
    await act(async () => root.render(<Prueba {...props} />));
}

const boton = (texto) => [...host.querySelectorAll('button')].find((b) => b.textContent.includes(texto));
const campo = (uuid, nombre) => host.querySelector(`#multienvio-${uuid}-${nombre}`);
const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
async function escribir(input, valor) {
    await act(async () => {
        setter.call(input, valor);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
}
async function salir(input) {
    await act(async () => { input.dispatchEvent(new FocusEvent('focusout', { bubbles: true })); });
}
async function capturar(valores) {
    for (const [uuid, folio, valor] of valores) {
        await escribir(campo(uuid, 'folio'), folio);
        await escribir(campo(uuid, 'valor'), valor);
    }
}
const error422 = (errors) => Object.assign(new Error('422'), { response: { status: 422, data: { message: 'Error', errors } } });

it('convierte montos a centavos enteros sin errores de redondeo', () => {
    expect(centavos('600')).toBe(60000);
    expect(centavos('0.1')).toBe(10);
    expect(centavos('1500.00')).toBe(150000);
    expect(centavos(1500)).toBe(150000);
    expect(centavos('12.345')).toBeNull();
    expect(centavos('')).toBeNull();
});

it('muestra el aviso con la decisión pendiente y las dos acciones', async () => {
    await render(proyeccion());
    expect(axios.get).toHaveBeenCalledWith('/control-pedidos/9/multienvio', expect.anything());
    expect(host.textContent).toContain('Multienvío detectado');
    expect(host.textContent).toContain('CEDIS determinó que este pedido requiere 3 cajas. Si el cliente requiere seguro de envío, cada caja deberá contar con su propio pedido, pago y remisión.');
    expect(host.textContent).toContain('Podrás activar el multienvío posteriormente si el cliente cambia de opinión.');
    expect(boton('Activar multienvío con seguro')).toBeTruthy();
    expect(boton('Continuar sin seguro')).toBeTruthy();
    expect(host.textContent).not.toMatch(/T-0\d/);
});

it('no muestra el aviso si no es elegible (una caja o consulta abierta) ni si ya se decidió', async () => {
    await render(proyeccion({ evaluacion: evaluacion({ elegible: false, motivo: 'Se necesitan al menos dos cajas activas.', n_cajas: 1 }), cajas_resumen: null }));
    expect(host.textContent).not.toContain('Multienvío detectado');
    expect(host.querySelector('[data-panel-multienvio]')).toBeNull();
});

it('envía la decisión con la revisión vigente', async () => {
    await render(proyeccion());
    axios.post.mockResolvedValue({ data: proyeccion({ evaluacion: evaluacion({ estado: 'estandar_sin_seguro' }), grupo: { id: 4, estado: 'estandar_sin_seguro', decision: 'sin_seguro' }, cajas_resumen: null }) });
    await act(async () => boton('Continuar sin seguro').click());
    expect(axios.post).toHaveBeenCalledWith('/control-pedidos/9/multienvio/decision', { decision: 'sin_seguro', revision: 'rev-1' }, expect.anything());
    expect(host.textContent).not.toContain('Multienvío detectado');
    // Acción posterior en el pedido estándar multicaja mientras siga siendo elegible.
    expect(boton('Activar multienvío con seguro')).toBeTruthy();
});

it('configura 3 cajas 600/500/400 sobre 1500 y habilita confirmar; un centavo de diferencia lo bloquea', async () => {
    await render(configurando());
    expect(host.textContent).toContain('Caja chica · 5 kg · 40 × 30 × 20 cm');
    expect(host.querySelector('img[src*="/docs/70"]')).not.toBeNull();
    expect(boton('Confirmar multienvío').disabled).toBe(true);

    await capturar([['c1', 'WZ-1', '600'], ['c2', 'WZ-2', '500'], ['c3', 'WZ-3', '400']]);
    expect(host.querySelector('[data-diferencia-multienvio]').textContent).toContain('Cuadra');
    expect(boton('Confirmar multienvío').disabled).toBe(false);

    await escribir(campo('c3', 'valor'), '399.99');
    expect(boton('Confirmar multienvío').disabled).toBe(true);
    expect(host.querySelector('[data-diferencia-multienvio]').textContent).toMatch(/Faltan\s*\$0\.01/);

    await escribir(campo('c3', 'valor'), '400,01');
    expect(campo('c3', 'valor').value).toBe('400.01');
    expect(host.querySelector('[data-diferencia-multienvio]').textContent).toMatch(/Sobran\s*\$0\.01/);
});

it('bloquea confirmar con folios repetidos', async () => {
    await render(configurando());
    await capturar([['c1', 'WZ-1', '600'], ['c2', 'wz-1', '500'], ['c3', 'WZ-3', '400']]);
    expect(boton('Confirmar multienvío').disabled).toBe(true);
    expect(campo('c2', 'folio').getAttribute('aria-invalid')).toBe('true');
});

it('autoguarda el borrador al salir del campo y marca la captura como pendiente mientras tanto', async () => {
    const onDirtyChange = vi.fn();
    await render(configurando(), { onDirtyChange });
    axios.put.mockResolvedValue({ data: configurando([{ uuid_operativo: 'c1', folio_remision: 'WZ-1', total_mercancia: 0, contenido: null }]) });
    await escribir(campo('c1', 'folio'), 'WZ-1');
    expect(onDirtyChange).toHaveBeenLastCalledWith(true);
    await salir(campo('c1', 'folio'));
    expect(axios.put).toHaveBeenCalledWith('/control-pedidos/9/multienvio/borrador', { envios: [
        { uuid_operativo: 'c1', folio_remision: 'WZ-1', total_mercancia: 0, contenido: null },
        { uuid_operativo: 'c2', folio_remision: null, total_mercancia: 0, contenido: null },
        { uuid_operativo: 'c3', folio_remision: null, total_mercancia: 0, contenido: null },
    ] }, expect.anything());
    expect(onDirtyChange).toHaveBeenLastCalledWith(false);
    expect(campo('c1', 'folio').value).toBe('WZ-1');
});

it('autoguarda con espera tras escribir', async () => {
    vi.useFakeTimers();
    axios.get.mockResolvedValue({ data: configurando() });
    await act(async () => root.render(<Prueba />));
    axios.put.mockResolvedValue({ data: configurando() });
    await escribir(campo('c1', 'valor'), '600');
    expect(axios.put).not.toHaveBeenCalled();
    await act(async () => { vi.advanceTimersByTime(650); });
    expect(axios.put).toHaveBeenCalledTimes(1);
});

it('restaura el borrador al reabrir', async () => {
    await render(configurando([
        { uuid_operativo: 'c1', folio_remision: 'WZ-1', total_mercancia: 600, contenido: 'Perfumes' },
        { uuid_operativo: 'c2', folio_remision: 'WZ-2', total_mercancia: 500, contenido: null },
        { uuid_operativo: 'c3', folio_remision: null, total_mercancia: 0, contenido: null },
    ]));
    expect(campo('c1', 'folio').value).toBe('WZ-1');
    expect(campo('c1', 'valor').value).toBe('600.00');
    expect(campo('c1', 'contenido').value).toBe('Perfumes');
    expect(campo('c3', 'valor').value).toBe('');
    expect(host.querySelector('[data-suma-multienvio]').textContent).toMatch(/1,100\.00/);
});

it('una lectura nueva no pisa lo que se está capturando', async () => {
    await render(configurando());
    await escribir(campo('c1', 'folio'), 'WZ-LOCAL');
    axios.get.mockResolvedValue({ data: configurando([{ uuid_operativo: 'c1', folio_remision: 'WZ-SERVIDOR', total_mercancia: 0 }, { uuid_operativo: 'c2', folio_remision: 'WZ-2', total_mercancia: 500 }]) });
    await act(async () => { await estado.recargar(); });
    expect(campo('c1', 'folio').value).toBe('WZ-LOCAL');
    expect(campo('c2', 'folio').value).toBe('WZ-2');
});

it('un 422 de folio_remision marca el campo de esa caja', async () => {
    await render(configurando());
    axios.put.mockRejectedValue(error422({ folio_remision: ['El folio WZ-2 está repetido.'] }));
    await capturar([['c1', 'WZ-1', '600'], ['c2', 'WZ-2', '500']]);
    await salir(campo('c2', 'folio'));
    expect(campo('c2', 'folio').getAttribute('aria-invalid')).toBe('true');
    expect(campo('c1', 'folio').getAttribute('aria-invalid')).toBeNull();
    const descripcion = campo('c2', 'folio').getAttribute('aria-describedby');
    expect(host.querySelector(`#${descripcion}`).textContent).toContain('El folio WZ-2 está repetido.');
});

it('una revisión obsoleta recarga la proyección, avisa y conserva lo capturado', async () => {
    await render(configurando());
    await capturar([['c1', 'WZ-1', '600'], ['c2', 'WZ-2', '500'], ['c3', 'WZ-3', '400']]);
    axios.put.mockResolvedValue({ data: configurando() });
    axios.post.mockRejectedValue(error422({ revision: ['La consulta cambió desde que se tomó la decisión; recarga la pantalla y vuelve a revisarla.'] }));
    axios.get.mockClear();
    await act(async () => boton('Confirmar multienvío').click());
    expect(axios.post).toHaveBeenCalledWith('/control-pedidos/9/multienvio/confirmar', { revision: 'rev-1' }, expect.anything());
    expect(axios.get).toHaveBeenCalledTimes(1);
    expect(host.textContent).toContain('Las cajas de la consulta cambiaron');
    expect(boton('Actualizar con las cajas vigentes')).toBeTruthy();
    expect(campo('c1', 'folio').value).toBe('WZ-1');
    expect(campo('c3', 'valor').value).toBe('400');
});

it('doble clic en confirmar envía una sola vez', async () => {
    await render(configurando());
    await capturar([['c1', 'WZ-1', '600'], ['c2', 'WZ-2', '500'], ['c3', 'WZ-3', '400']]);
    axios.put.mockResolvedValue({ data: configurando() });
    let resolver;
    axios.post.mockImplementation(() => new Promise((ok) => { resolver = ok; }));
    await act(async () => {
        boton('Confirmar multienvío').click();
        boton('Confirmar multienvío').click();
    });
    await act(async () => { boton('Confirmando multienvío')?.click(); });
    expect(axios.post).toHaveBeenCalledTimes(1);
    await act(async () => resolver({ data: proyeccion({
        evaluacion: evaluacion({ elegible: false, estado: 'activo' }),
        grupo: { id: 4, estado: 'activo', decision: 'activar' },
        envios: [
            { id: 9, orden: 1, folio: 'PBMA-9', folio_remision: 'WZ-1', estado_pago: 'sin_pago', estatus: 'Pendiente de pago' },
            { id: 10, orden: 2, folio: 'PBMA-10', folio_remision: 'WZ-2', estado_pago: 'validado', estatus: 'Pago validado' },
            { id: 11, orden: 3, folio: 'PBMA-11', folio_remision: 'WZ-3', estado_pago: 'sin_pago', estatus: 'Pendiente de pago' },
        ],
        progreso: { pagos_validados: '1/3', remisiones: '0/3', guias: '0/3', enviados: '0/3' },
        cajas_resumen: null,
    }) }));
    expect(host.textContent).toContain('Esta compra se repartió en 3 envíos');
    expect(host.textContent).toContain('Folio Wizerp WZ-2');
    expect(host.textContent).toContain('Pagos validados 1 de 3');
    expect(host.querySelector('[aria-current="true"]').textContent).toContain('Envío 1');
});

it('muestra el banner de regularización con hechos legibles y sin acciones destructivas', async () => {
    await render(proyeccion({
        evaluacion: evaluacion({ elegible: false, estado: 'regularizacion_pendiente' }),
        grupo: { id: 4, estado: 'regularizacion_pendiente', decision: 'activar', regularizacion: { hechos: ['pago_validado', 'remision', 'guia'] } },
        cajas_resumen: null,
    }));
    expect(host.textContent).toContain('Multienvío pendiente de regularización');
    expect(host.textContent).toContain('Pago validado');
    expect(host.textContent).toContain('Remisión registrada');
    expect(host.textContent).toContain('Guía registrada');
    expect(host.textContent).not.toContain('pago_validado');
    expect(host.querySelectorAll('[data-panel-multienvio] button')).toHaveLength(0);
});

it('un envío visible parcialmente no revela el total', async () => {
    await render(proyeccion({
        evaluacion: evaluacion({ elegible: false, estado: 'activo' }),
        grupo: { id: 4, estado: 'activo' },
        envios: [{ id: 9, orden: 2, folio: 'PBMA-9', folio_remision: 'WZ-2', estado_pago: 'sin_pago' }],
        parcial: true, cajas_resumen: null,
    }));
    expect(host.textContent).toContain('Solo ves los envíos a los que tienes acceso');
    expect(host.textContent).not.toMatch(/repartió en/);
});

it('la cotización queda retenida mientras la decisión está pendiente o se configura', () => {
    const base = { habilitado: true, resumen: null, cajasActivas: 3 };
    const listo = (datos) => ({ cargado: true, datos, errores: {} });
    expect(multienvioBloqueaCotizacion({ ...base, multienvio: { cargado: false, errores: {} } })).toBe(true);
    expect(multienvioBloqueaCotizacion({ ...base, cajasActivas: 1, multienvio: { cargado: false, errores: {} } })).toBe(false);
    expect(multienvioBloqueaCotizacion({ ...base, multienvio: listo(proyeccion()) })).toBe(true);
    expect(multienvioBloqueaCotizacion({ ...base, multienvio: listo(configurando()) })).toBe(true);
    expect(multienvioBloqueaCotizacion({ ...base, multienvio: listo(proyeccion({ grupo: { estado: 'estandar_sin_seguro' } })) })).toBe(false);
    expect(multienvioBloqueaCotizacion({ ...base, multienvio: listo(proyeccion({ evaluacion: evaluacion({ elegible: false }) })) })).toBe(false);
    expect(multienvioBloqueaCotizacion({ ...base, habilitado: false, multienvio: listo(proyeccion()) })).toBe(false);
});
