// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/react';
import ModalResponderPesaje from './ModalResponderPesaje';

vi.mock('@inertiajs/react', () => ({ router: { post: vi.fn() } }));
vi.mock('../../../Activos/Partials/useDispositivoCampo', () => ({
    default: () => ({ esCampo: false, esMovil: false }), esDispositivoCampo: () => false,
}));

let root, host, draft, saved;
const pedido = { id: 900, folio: 'QA-NACIONAL', cantidad_piezas: 0,
    solicitud_preparacion: { modalidad_entrega: 'nacional' }, documentos: [] };
const tiposCaja = [{ id: 1, nombre: 'Caja de prueba', largo: 10, ancho: 10, alto: 10, peso_volumetrico: 0.2 }];
const button = text => [...document.querySelectorAll('button')].find(el => !el.classList.contains('gelia-pesaje-nacional-desplegar') && el.textContent.trim() === text);
const panel = index => document.getElementById(`pesaje-nacional-seccion-${index}`);
const tick = () => act(() => vi.advanceTimersByTimeAsync(20));
const click = async el => { await act(async () => el.click()); await tick(); };
const change = (el, value) => act(async () => {
    Object.getOwnPropertyDescriptor(Object.getPrototypeOf(el), 'value').set.call(el, value);
    el.dispatchEvent(new Event(el.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true }));
});
async function mount(extra = {}) {
    await act(async () => root.render(<ModalResponderPesaje abierto onClose={vi.fn()}
        pedido={{ ...pedido, ...extra }} tiposCaja={tiposCaja} almacenesBusqueda={[{ id: 1, nombre: 'CEDIS' }]} />));
    await act(() => vi.advanceTimersByTimeAsync(250));
}

beforeEach(() => {
    vi.useFakeTimers();
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    vi.stubGlobal('route', (name, id) => `${name}/${id}`);
    vi.stubGlobal('requestAnimationFrame', fn => setTimeout(fn, 0));
    vi.stubGlobal('indexedDB', { open: () => {
        const request = {};
        queueMicrotask(() => {
            request.result = { transaction: () => {
                const tx = { objectStore: () => ({
                    get: () => { const r = {}; queueMicrotask(() => { r.result = draft; r.onsuccess?.(); }); return r; },
                    put: value => { saved = value; queueMicrotask(() => tx.oncomplete?.()); }, delete: vi.fn(),
                }) };
                return tx;
            } };
            request.onsuccess?.();
        });
        return request;
    } });
    HTMLElement.prototype.scrollIntoView = vi.fn();
    HTMLElement.prototype.scrollTo = vi.fn();
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:qa-evidencia');
    vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
    router.post.mockReset();
    draft = null; saved = null;
    host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});

afterEach(async () => {
    await act(() => root.unmount()); host.remove(); vi.useRealTimers(); vi.restoreAllMocks(); vi.unstubAllGlobals();
});

it('permite abrir Cajas sin completar Pedido ni Productos y mantiene un solo pie', async () => {
    await mount();
    expect(document.querySelector('[aria-label="Pasos para responder"]')).toBeNull();
    expect(document.querySelectorAll('.gelia-pesaje-nacional-footer')).toHaveLength(1);
    expect(panel(0).hidden).toBe(false); expect(panel(1).hidden).toBe(true);
    await click(document.getElementById('pesaje-nacional-seccion-2-titulo'));
    expect(panel(2).hidden).toBe(false);
    expect(button('Registrar pesaje')).toBeTruthy();
    expect(router.post).not.toHaveBeenCalled();
});

it('mantiene la acción de Productos cuando se pliega la referencia del pedido', async () => {
    await mount();
    await click(button('Revisar productos'));
    await click(document.getElementById('pesaje-nacional-seccion-0-titulo'));
    expect(panel(0).hidden).toBe(true);
    expect(panel(1).hidden).toBe(false);
    expect(document.querySelector('footer').textContent).toContain('Cajas y evidencias');
    expect(document.querySelector('footer').textContent).toContain('Atrás');
});

it('conserva los controles, el peso y el archivo al plegar, avanzar y retroceder', async () => {
    await mount();
    await click(document.getElementById('pesaje-nacional-seccion-2-titulo'));
    const peso = document.querySelector('input[id^="pesaje-peso-"]');
    await change(peso, '2.75');
    const file = new File(['foto'], 'contenido.jpg', { type: 'image/jpeg' });
    const input = panel(2).querySelector('input[type="file"]');
    await act(async () => {
        Object.defineProperty(input, 'files', { configurable: true, value: [file] });
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await click(document.getElementById('pesaje-nacional-seccion-2-titulo'));
    expect(panel(2).hidden).toBe(true);
    await click(button('Atrás'));
    expect(document.activeElement.id).toBe('cedis-sku-pesaje');
    await click(button('Cajas y evidencias'));
    expect(document.querySelector('input[id^="pesaje-peso-"]')).toBe(peso);
    expect(peso.value).toBe('2.75');
    expect(panel(2).textContent).toContain('contenido.jpg');
    await act(() => vi.advanceTimersByTimeAsync(700));
    expect(saved.envios[0].peso_real_kg).toBe('2.75');
    expect(saved.evidenciasPorEnvio[0].archivos[0]).toBe(file);
    await change(panel(2).querySelector('select[id^="pesaje-caja-"]'), '1');
    await click(button('Registrar pesaje'));
    expect(router.post).not.toHaveBeenCalled();
    await click(document.querySelector('[aria-label="Confirmar acción"] button'));
    await act(() => vi.advanceTimersByTimeAsync(100));
    expect(router.post).toHaveBeenCalledOnce();
    const [url, payload] = router.post.mock.calls[0];
    expect(url).toBe('control_pedidos.cedis.responder_pesaje/900');
    expect(payload.get('cajas[0][peso_real_kg]')).toBe('2.75');
    expect(payload.get('cajas[0][catalogo_tipo_caja_id]')).toBe('1');
    expect(payload.get('evidencias_envios[0][0]')).toBe(file);
});

it('abre Cajas plegada y enfoca el campo con error sin enfocar el escáner', async () => {
    await mount();
    await click(document.getElementById('pesaje-nacional-seccion-2-titulo'));
    await click(document.getElementById('pesaje-nacional-seccion-2-titulo'));
    await click(button('Registrar pesaje'));
    expect(panel(2).hidden).toBe(false);
    expect(document.activeElement.id).toMatch(/^pesaje-caja-/);
    expect(document.activeElement.getAttribute('aria-invalid')).toBe('true');
    await act(() => vi.advanceTimersByTimeAsync(250));
    expect(document.activeElement.id).toMatch(/^pesaje-caja-/);
    expect(router.post).not.toHaveBeenCalled();
});

it('recupera el borrador y despliega las secciones con captura sin cambiar sus UUID', async () => {
    draft = { envios: [{ client_uuid: 'ab463593-230f-4a18-8d69-38177cd871e5',
        catalogo_tipo_caja_id: 1, largo: 10, ancho: 10, alto: 10, peso_real_kg: '3', peso_volumetrico_kg: 0.2 }],
        revisiones: [], evidenciasPorEnvio: [] };
    await mount();
    expect(panel(2).hidden).toBe(false);
    expect(document.getElementById('pesaje-peso-ab463593-230f-4a18-8d69-38177cd871e5').value).toBe('3');
    expect(document.body.textContent).toContain('Borrador recuperado');
});

it('abre Productos y su detalle al faltar comentario y mantiene allí el foco', async () => {
    draft = { envios: [{ client_uuid: 'ab463593-230f-4a18-8d69-38177cd871e5',
        catalogo_tipo_caja_id: 1, largo: 10, ancho: 10, alto: 10, peso_real_kg: '3', peso_volumetrico_kg: 0.2 }],
        revisiones: [{ client_uuid: '2422bb99-9437-4f1c-aa84-76c32facfb63', descripcion_producto: 'Producto de prueba',
            estado_fisico: 'sin_existencia', comentario: '', evidencias: [], previews: [] }],
        evidenciasPorEnvio: [{ archivos: [new File(['foto'], 'caja.jpg', { type: 'image/jpeg' })] }] };
    await mount();
    await click(button('Revisar productos'));
    await click(button('Cajas y evidencias'));
    await click(document.getElementById('pesaje-nacional-seccion-1-titulo'));
    expect(panel(1).hidden).toBe(true);
    await click(button('Registrar pesaje'));
    expect(panel(1).hidden).toBe(false);
    expect(document.activeElement.id).toBe('pesaje-comentario-2422bb99-9437-4f1c-aa84-76c32facfb63');
    await act(() => vi.advanceTimersByTimeAsync(250));
    expect(document.activeElement.id).toBe('pesaje-comentario-2422bb99-9437-4f1c-aa84-76c32facfb63');
    expect(router.post).not.toHaveBeenCalled();
});

it.each([
    { modalidad_entrega: 'municipal' },
    { modalidad_entrega: 'tienda', codigo_modalidad: 'RECOGE_TIENDA' },
    { modalidad_entrega: 'nacional', captura_nacional: 'compartida' },
])('mantiene los tres pasos fuera del recorrido nacional: %j', async solicitud => {
    await mount({ solicitud_preparacion: solicitud, consulta_cedis_modo_revision_mercancia: solicitud.captura_nacional === 'compartida' });
    expect(document.querySelector('[aria-label="Pasos para responder"]')).toBeTruthy();
    expect(document.querySelector('.gelia-pesaje-nacional-footer')).toBeNull();
    expect(document.querySelector('.gelia-pesaje-nacional-seccion')).toBeNull();
});
