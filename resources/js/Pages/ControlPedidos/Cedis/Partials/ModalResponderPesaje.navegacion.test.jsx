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
vi.mock('../../../../Components/Almacenes/SelectorProducto', () => ({
    default: ({ onChange, onEscanear, inputId }) => (
        <>
            <input id={inputId} readOnly />
            <button type="button" onClick={() => onChange(7, { id: 7, sku: 'SKU-7', descripcion: 'Perfume de catálogo' })}>Elegir perfume</button>
            <button type="button" onClick={() => onEscanear?.('SKU-7')}>Escanear producto</button>
        </>
    ),
}));

let root, host, draft, saved;
const pedido = { id: 900, folio: 'QA-NACIONAL', cantidad_piezas: 0,
    solicitud_preparacion: { modalidad_entrega: 'nacional' }, documentos: [] };
const tiposCaja = [{ id: 1, nombre: 'Caja de prueba', largo: 10, ancho: 10, alto: 10, peso_volumetrico: 0.2 }];
const button = text => [...document.querySelectorAll('button')].find(el => !el.classList.contains('gelia-pesaje-nacional-desplegar') && el.textContent.trim() === text);
const panel = index => document.getElementById(`pesaje-nacional-seccion-${index}`);
const tick = () => act(() => vi.advanceTimersByTimeAsync(20));
const click = async el => { await act(async () => el.click()); await tick(); };
const elegirEstado = async texto => {
    const etiqueta = [...document.querySelectorAll('label')].find(el => el.textContent.trim() === texto);
    await click(etiqueta.querySelector('input') || etiqueta);
};
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

it('muestra radios 2.0, oculta la lista Bueno y bloquea Siguiente sin estado', async () => {
    await mount();
    expect(document.querySelector('[aria-label="Pasos para responder"]')).toBeNull();
    expect(document.querySelectorAll('.gelia-pesaje-nacional-footer')).toHaveLength(1);
    expect(document.body.textContent).toContain('Todo OK');
    expect(document.body.textContent).toContain('Con detalle o faltante');
    expect(document.body.textContent).toContain('Ver pedido');
    expect(document.body.textContent).not.toContain('Cada pieza inicia en Bueno');
    expect(document.querySelector('iframe')).toBeNull();
    expect(panel(0).hidden).toBe(false); expect(panel(1).hidden).toBe(false);
    await click(button('Siguiente'));
    expect(panel(2).hidden).toBe(true);
    expect(document.body.textContent).toContain('Indica el estado del pedido.');
    expect(router.post).not.toHaveBeenCalled();
});

it('con Todo OK avanza a Cajas y permite regresar al estado', async () => {
    await mount();
    await elegirEstado('Todo OK');
    await click(button('Siguiente'));
    expect(panel(0).hidden).toBe(true);
    expect(panel(1).hidden).toBe(true);
    expect(panel(2).hidden).toBe(false);
    expect(button('Enviar respuesta')).toBeTruthy();
    await click(button('Atrás'));
    expect(panel(0).hidden).toBe(false);
    expect(panel(1).hidden).toBe(false);
    expect(document.activeElement.name).toBe('estado-pesaje-nacional');
    expect(router.post).not.toHaveBeenCalled();
});

it('Con detalle muestra captura de afectadas y Siguiente exige una unidad', async () => {
    await mount();
    await elegirEstado('Con detalle o faltante');
    expect(document.body.textContent).toContain('Registra solo las piezas afectadas');
    expect(document.body.textContent).toContain('Capturar sin catálogo');
    expect(document.body.textContent).not.toContain('Almacén del catálogo');
    expect(document.body.textContent).not.toContain('Seleccione almacén');
    await click(button('Siguiente'));
    expect(panel(2).hidden).toBe(true);
    expect(document.body.textContent).toContain('Agrega las piezas con detalle o faltante.');
    expect(router.post).not.toHaveBeenCalled();
});

it('recupera Sin existencias y no pide retiro si el borrador no cubre cajas guardadas', async () => {
    const uuid = 'ab463593-230f-4a18-8d69-38177cd871e5';
    draft = {
        estadoPedido: 'detalle',
        excepciones: [{ descripcion: 'C ABERCROMBIE', sku: '85715166708', condicion: 'faltante', detalle: 'Sin existencias' }],
        envios: [],
        revisiones: [],
        evidenciasPorEnvio: [],
    };
    await mount({
        cajas: [{
            id: 11, uuid_operativo: uuid, catalogo_tipo_caja_id: 1,
            largo: 10, ancho: 10, alto: 10, peso_real_kg: 1.1, peso_volumetrico_kg: 0.2,
        }],
    });
    expect(document.body.textContent).toContain('Sin existencias');
    expect(document.body.textContent).toContain('C ABERCROMBIE');
    await click(button('Siguiente'));
    expect(panel(2).hidden).toBe(false);
    expect(document.getElementById(`pesaje-peso-${uuid}`).value).toBe('1.1');
    expect(document.body.textContent).not.toContain('Motivo de retiro');
    const file = new File(['foto'], 'caja.jpg', { type: 'image/jpeg' });
    const input = panel(2).querySelector('input[type="file"]');
    await act(async () => {
        Object.defineProperty(input, 'files', { configurable: true, value: [file] });
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await click(button('Enviar respuesta'));
    expect(document.body.textContent).not.toContain('Motivo de retiro');
    await click(document.querySelector('[aria-label="Confirmar acción"] button'));
    await act(() => vi.advanceTimersByTimeAsync(100));
    expect(router.post).toHaveBeenCalledOnce();
    const payload = router.post.mock.calls[0][1];
    expect(payload.get('excepciones[0][condicion]')).toBe('faltante');
    expect(payload.get('excepciones[0][sku]')).toBe('85715166708');
    expect(payload.get('almacen_id')).toBeNull();
});

it('conserva los controles, el peso y el archivo al avanzar y retroceder', async () => {
    await mount();
    await elegirEstado('Todo OK');
    await click(button('Siguiente'));
    const peso = document.querySelector('input[id^="pesaje-peso-"]');
    await change(peso, '2.75');
    const file = new File(['foto'], 'contenido.jpg', { type: 'image/jpeg' });
    const input = panel(2).querySelector('input[type="file"]');
    await act(async () => {
        Object.defineProperty(input, 'files', { configurable: true, value: [file] });
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await click(button('Atrás'));
    expect(panel(2).hidden).toBe(true);
    expect(document.activeElement.name).toBe('estado-pesaje-nacional');
    await click(button('Siguiente'));
    expect(document.querySelector('input[id^="pesaje-peso-"]')).toBe(peso);
    expect(peso.value).toBe('2.75');
    expect(panel(2).textContent).toContain('contenido.jpg');
    await act(() => vi.advanceTimersByTimeAsync(700));
    expect(saved.envios[0].peso_real_kg).toBe('2.75');
    expect(saved.evidenciasPorEnvio[0].archivos[0]).toBe(file);
    expect(saved.estadoPedido).toBe('correcto');
    await change(panel(2).querySelector('select[id^="pesaje-caja-"]'), '1');
    await click(button('Enviar respuesta'));
    expect(router.post).not.toHaveBeenCalled();
    await click(document.querySelector('[aria-label="Confirmar acción"] button'));
    await act(() => vi.advanceTimersByTimeAsync(100));
    expect(router.post).toHaveBeenCalledOnce();
    const [url, payload] = router.post.mock.calls[0];
    expect(url).toBe('control_pedidos.cedis.responder_pesaje/900');
    expect(payload.get('cajas[0][peso_real_kg]')).toBe('2.75');
    expect(payload.get('cajas[0][catalogo_tipo_caja_id]')).toBe('1');
    expect(payload.get('evidencias_envios[0][0]')).toBe(file);
    expect(payload.get('confirmacion_explicita')).toBe('1');
    expect(payload.get('almacen_id')).toBeNull();
});

it('muestra Cajas y enfoca el campo con error sin enfocar el escáner', async () => {
    await mount();
    await elegirEstado('Todo OK');
    await click(button('Siguiente'));
    await click(button('Enviar respuesta'));
    expect(panel(2).hidden).toBe(false);
    expect(document.activeElement.id).toMatch(/^pesaje-caja-/);
    expect(document.activeElement.getAttribute('aria-invalid')).toBe('true');
    await act(() => vi.advanceTimersByTimeAsync(250));
    expect(document.activeElement.id).toMatch(/^pesaje-caja-/);
    expect(router.post).not.toHaveBeenCalled();
});

it('recupera el borrador sin cambiar la vista inicial sin cambiar sus UUID', async () => {
    draft = { estadoPedido: 'correcto', envios: [{ client_uuid: 'ab463593-230f-4a18-8d69-38177cd871e5',
        catalogo_tipo_caja_id: 1, largo: 10, ancho: 10, alto: 10, peso_real_kg: '3', peso_volumetrico_kg: 0.2 }],
        revisiones: [], evidenciasPorEnvio: [] };
    await mount();
    expect(panel(2).hidden).toBe(true);
    await click(button('Siguiente'));
    expect(document.getElementById('pesaje-peso-ab463593-230f-4a18-8d69-38177cd871e5').value).toBe('3');
    expect(document.body.textContent).toContain('Borrador recuperado');
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
    expect(document.querySelector('input[name="estado-pesaje-nacional"]')).toBeNull();
    expect(document.body.textContent).toContain('Cada pieza inicia en Bueno');
});
