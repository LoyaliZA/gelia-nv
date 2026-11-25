// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import SeguimientoSolicitudNacional from './SeguimientoSolicitudNacional';

vi.mock('axios', () => ({ default: { post: vi.fn(), get: vi.fn() } }));
vi.mock('../Partials/AjustePiezas', () => ({ AjustePiezas: () => <p>Editor de ajuste</p> }));

const pedido = {
    id: 9,
    pesaje_respondido_at: '2026-11-20T12:00:00Z',
    puede_cerrar_consulta: true,
    version_consulta: 15,
    revisiones_producto: [{ id: 1, sku: 'SKU-NAC', descripcion_producto: 'Aroma', cantidad_encontrada: 1 }],
};
let root;
let host;

beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
    axios.post.mockReset();
    axios.get.mockReset();
});

afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

function botonCerrar() {
    return [...document.querySelectorAll('button')].find((boton) => boton.textContent.includes('Cerrar consulta'));
}

async function render(onPedidoActualizado = vi.fn(), extra = {}) {
    await act(async () => {
        root.render(<SeguimientoSolicitudNacional pedido={{ ...pedido, ...extra }} onPedidoActualizado={onPedidoActualizado} />);
    });

    return onPedidoActualizado;
}

it('cierra la consulta con el contexto nacional y publica la lectura nueva', async () => {
    const lectura = { id: 9, consulta_cerrada: true, consulta_cerrada_at: '2026-11-20T13:00:00Z', version_consulta: 16 };
    axios.post.mockResolvedValue({ data: { ok: true, consulta_cerrada: true } });
    axios.get.mockResolvedValue({ data: lectura });
    const onPedidoActualizado = await render();
    expect(botonCerrar().disabled).toBe(false);
    await act(async () => { botonCerrar().click(); });
    expect(axios.post).toHaveBeenCalledWith(
        '/control-pedidos/9/cerrar-consulta',
        { modalidad_esperada: 'nacional', version_consulta: 15 },
        expect.anything(),
    );
    expect(axios.get).toHaveBeenCalledWith('/control-pedidos/9/consulta-nacional', expect.anything());
    expect(onPedidoActualizado).toHaveBeenCalledWith(lectura);
});

it('registra la salida del vínculo físico', async () => {
    axios.post.mockResolvedValue({ data: { ok: true } });
    axios.get.mockResolvedValue({ data: { id: 9, vinculo_resguardo: { estado: 'en_traslado' } } });
    await render(vi.fn(), {
        vinculo_resguardo: { estado: 'vinculado' },
        vinculo_pedido_id: 22,
        puede_cerrar_consulta: false,
    });
    await act(async () => {
        [...document.querySelectorAll('button')].find((boton) => boton.textContent.includes('Registrar salida')).click();
    });
    expect(axios.post).toHaveBeenCalledWith(
        '/control-pedidos/22/custodia-nacional',
        { accion: 'salida' },
        expect.anything(),
    );
});

it('muestra el conflicto y no da la consulta por cerrada', async () => {
    axios.post.mockRejectedValue({ response: { status: 409, data: { message: 'La consulta cambió. Actualice para continuar.', codigo: 'vista_atrasada' } } });
    const onPedidoActualizado = await render();
    await act(async () => { botonCerrar().click(); });
    expect(document.body.textContent).toContain('La consulta cambió. Actualice para continuar.');
    expect(document.body.textContent).not.toContain('Ventas registró el cierre');
    expect(onPedidoActualizado).not.toHaveBeenCalled();
    expect(axios.get).not.toHaveBeenCalled();
});

const respondido = {
    pesaje_respondido_at: '2026-11-20T12:00:00Z',
    pesaje_respondido_por: { name: 'Auxiliar CEDIS' },
    numero_cajas: 1,
    peso_real_kg: 1.2,
    peso_cobrado_guia_kg: 2,
    revisiones_producto: [
        { id: 1, sku: 'SKU-ANX-1', descripcion_producto: 'Perfume anexo de prueba', cantidad_solicitada: 1, cantidad_encontrada: 1, estado_fisico: 'bueno' },
    ],
};

it('muestra la respuesta de CEDIS como una card con estado, pieza y resumen de pesaje', async () => {
    await render(vi.fn(), respondido);
    const respuesta = document.querySelector('[aria-label="Resultado de la consulta en CEDIS"]');
    expect(respuesta).not.toBeNull();
    expect(respuesta.textContent).toContain('Respuesta de CEDIS');
    expect(respuesta.textContent).toContain('Auxiliar CEDIS');
    expect(respuesta.textContent).toContain('SKU-ANX-1');
    expect(respuesta.textContent).toContain('Perfume anexo de prueba');
    expect(respuesta.textContent).toContain('Todo OK');
    expect(respuesta.textContent).toContain('Unidades encontradas');
    expect(respuesta.textContent).not.toContain('Piezas afectadas');
    const pesaje = document.querySelector('[aria-label="Resumen del pesaje"]');
    expect(pesaje.textContent).toMatch(/Cajas\s*1/);
    expect(pesaje.textContent).toMatch(/Peso real\s*1\.2 kg/);
    expect(pesaje.textContent).toMatch(/Peso a cobrar\s*2 kg/);
    expect(document.body.textContent).toContain('Consulta respondida');
});

it('marca con detalles o faltantes cuando CEDIS reporta una pieza con detalle', async () => {
    await render(vi.fn(), { ...respondido, revisiones_producto: [{ ...respondido.revisiones_producto[0], estado_fisico: 'regular', comentario: 'Caja maltratada' }] });
    const respuesta = document.querySelector('[aria-label="Resultado de la consulta en CEDIS"]');
    expect(respuesta.textContent).toContain('Con detalles o faltantes');
    expect(respuesta.textContent).toContain('Caja maltratada');
});

it('avisa que se espera la respuesta de CEDIS mientras la consulta no se contesta', async () => {
    await render(vi.fn(), { pesaje_respondido_at: null, puede_cerrar_consulta: false, revisiones_producto: [] });
    expect(document.body.textContent).toContain('Consulta pendiente: CEDIS');
    expect(document.body.textContent).toContain('Esperando respuesta de CEDIS');
    expect(document.querySelector('[aria-label="Resultado de la consulta en CEDIS"]')).toBeNull();
});

it('al cerrar la consulta muestra el aviso de cierre y la sección Mercancía explica por qué ya no se modifica', async () => {
    await render(vi.fn(), { ...respondido, consulta_cerrada_at: '2026-11-20T13:00:00Z', puede_cerrar_consulta: false });
    expect(document.body.textContent).toContain('Consulta cerrada');
    expect(document.body.textContent).toContain('Ventas registró el cierre');
    const mercancia = document.querySelector('[aria-labelledby="mercancia-nacional-titulo"]');
    expect(mercancia.textContent).toContain('ya no se modifica desde aquí');
    expect(mercancia.querySelector('.tienda-ajustes-desplegar')).toBeNull();
});

it('con pago o remisión explica que los anexos van por «Anexo extraordinario»', async () => {
    await render(vi.fn(), { ...respondido, consulta_cerrada_at: '2026-11-20T13:00:00Z', puede_cerrar_consulta: false, numero_remision: 'REM-1' });
    expect(document.body.textContent).toContain('Anexo extraordinario');
});

it('los ajustes de piezas viven en un desplegable «Modificar pedido» y no ocupan la vista de entrada', async () => {
    await render(vi.fn(), respondido);
    const abrir = [...document.querySelectorAll('button')].find((boton) => boton.textContent.includes('Modificar pedido'));
    expect(abrir).toBeDefined();
    expect(abrir.getAttribute('aria-expanded')).toBe('false');
    expect(document.querySelector('[hidden]').textContent).toContain('Editor de ajuste');
    await act(async () => abrir.click());
    expect(abrir.getAttribute('aria-expanded')).toBe('true');
    expect(document.querySelector('[hidden]')).toBeNull();
});

const conDetalle = {
    revision_registro: 4,
    numero_cajas: 1,
    peso_real_kg: '8.0000',
    peso_cobrado_guia_kg: '11.0000',
    base_captura: 'a'.repeat(64),
    revisiones_producto: [
        { id: 1, sku: 'SKU-A', descripcion_producto: 'Aroma con detalle', cantidad_solicitada: 1, cantidad_encontrada: 1, estado_fisico: 'regular' },
        { id: 2, sku: 'SKU-B', descripcion_producto: 'Aroma agotado', cantidad_solicitada: 1, cantidad_encontrada: 0, estado_fisico: 'sin_existencia' },
    ],
    cajas: [{ id: 5, tipo_caja: 'CAJA #202', largo: '46.00', ancho: '32.00', alto: '35.00', peso_real_kg: '8.0000', peso_cobrado_kg: '11.0000' }],
    documentos: [{ id: 70, tipo: 'evidencia_condicion', relacion_tipo: 'envio_caja', relacion_id: 5, activo: true, url: '/x.jpg', mime_type: 'image/jpeg', nombre_original: 'x.jpg' },
        { id: 71, tipo: 'evidencia_condicion', relacion_tipo: 'envio_caja', relacion_id: 5, activo: false, url: '/vieja.jpg', mime_type: 'image/jpeg', nombre_original: 'vieja.jpg' }],
};

it('ofrece acciones rápidas por pieza con detalle o sin existencias, sin Confirmar en faltantes', async () => {
    await render(vi.fn(), conDetalle);
    const detalle = document.querySelector('button[aria-label="Acciones de Aroma con detalle"]');
    const faltante = document.querySelector('button[aria-label="Acciones de Aroma agotado"]');
    const textos = () => [...document.querySelectorAll('[role="menuitem"]')].map(b => b.textContent);
    await act(() => detalle.click());
    expect(textos()).toEqual(['Confirmar estado', 'Retirar', 'Sustituir']);
    await act(() => detalle.click());
    await act(() => faltante.click());
    expect(textos()).toEqual(['Retirar', 'Sustituir']);
});

it('registra el retiro rápido como ajuste nacional de retiro con la pieza exacta', async () => {
    axios.post.mockResolvedValue({ data: { id: 9 } });
    await render(vi.fn(), conDetalle);
    await act(() => document.querySelector('button[aria-label="Acciones de Aroma con detalle"]').click());
    const retirar = [...document.querySelectorAll('[role="menuitem"]')].find((b) => b.textContent === 'Retirar');
    await act(async () => { retirar.click(); });
    const confirmar = [...document.querySelectorAll('button')].find((b) => b.textContent.includes('Confirmar retiro'));
    await act(async () => { confirmar.click(); });
    const [url, form] = axios.post.mock.calls[0];
    expect(url).toBe('/control-pedidos/9/ajuste-nacional');
    expect(form.get('tipo')).toBe('retiro');
    expect(form.get('piezas[0][revision_id]')).toBe('1');
    expect(form.get('revision_esperada')).toBe('4');
});

it('muestra tipo de caja, medidas y la evidencia vigente por envío', async () => {
    await render(vi.fn(), conDetalle);
    const resumen = document.querySelector('[aria-label="Envíos registrados por CEDIS"]');
    expect(resumen.textContent).toContain('CAJA #202');
    expect(resumen.textContent).toContain('46 × 32 × 35 cm');
    expect(resumen.querySelector('img')).not.toBeNull();
    expect(resumen.querySelectorAll('img')).toHaveLength(1);
});
