// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import PrepararApartadoRecoge from './PrepararApartadoRecoge';

const post = vi.hoisted(() => vi.fn());
const get = vi.hoisted(() => vi.fn());
vi.mock('axios', () => ({ default: { get } }));
vi.mock('@inertiajs/react', () => ({ router: { post } }));
vi.mock('../../../../Components/Almacenes/SelectorProducto', () => ({ default: ({ onChange }) => <button onClick={() => onChange(7, { id: 7, sku: 'CREMA-7', descripcion: 'Crema' })}>Elegir crema</button> }));
let root;
let container;
const tarea = { id: 1, estado: 'EN_ATENCION', responsable: { id: 10 }, apartado: { version: 2, ubicacion: 'Anaquel A' }, productos: [] };
const auth = { user: { id: 10, permissions: ['control_pedidos.tienda.apartado.separar', 'control_pedidos.tienda.reportar_error'] } };
const button = (label) => [...container.querySelectorAll('button')].find((b) => b.textContent === label);
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = (name, id) => `${name}/${id}`;
    post.mockReset();
    get.mockReset();
    container = document.createElement('div'); document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
const mount = (props = {}) => act(() => root.render(<PrepararApartadoRecoge tarea={tarea} auth={auth} {...props} />));
const click = (label) => act(() => button(label).click());
it('confirma productos seleccionados con una sola solicitud, sin captura manual de SKU', async () => {
    await mount();
    await click('Confirmar apartado');
    expect(container.textContent).toContain('Agregue los productos preparados o reporte el problema');
    expect(post).not.toHaveBeenCalled();
    await click('Elegir crema'); await click('Agregar pieza'); await click('Confirmar apartado');
    expect(post).toHaveBeenCalledTimes(1);
    expect(post.mock.calls[0][0]).toBe('control_pedidos.tienda.apartado.confirmar/1');
    expect(post.mock.calls[0][1]).toMatchObject({ version: 2, ubicacion: 'Anaquel A', lineas: [{ producto_id: 7, sku: 'CREMA-7', cantidad: 1, estado_fisico: 'bueno' }] });
    expect(container.querySelector('[placeholder="SKU"]')).toBeNull();
});
it('permite guardar sin confirmar y suma selecciones repetidas', async () => {
    await mount(); await click('Elegir crema'); await click('Agregar pieza');
    await click('Elegir crema'); await click('Agregar pieza'); await click('Guardar y continuar después');
    expect(post.mock.calls[0][0]).toBe('control_pedidos.tienda.apartado.guardar/1');
    expect(post.mock.calls[0][1].lineas).toHaveLength(1);
    expect(post.mock.calls[0][1].lineas[0].cantidad).toBe(2);
});
it('un apartado confirmado muestra el resultado sin permitir edición', async () => {
    await mount({ tarea: { ...tarea, estado: 'RESPONDIDA', apartado: { ...tarea.apartado, estado: 'APARTADO_CONFIRMADO' }, productos: [{ id: 8, sku: 'CREMA-7', descripcion_snapshot: 'Crema', cantidad_encontrada: 3, estado_fisico: 'bueno' }] } });
    expect(container.textContent).toContain('Total preparado: 3 piezas');
    expect(container.textContent).toContain('Apartado confirmado');
    expect(button('Confirmar apartado')).toBeUndefined();
});

it('muestra las acciones autorizadas por el servidor aunque no aparezcan en la lista de permisos', async () => {
    await mount({ auth: { user: { id: 10, permissions: [] } }, tarea: { ...tarea, puede_preparar: true } });
    expect(button('Confirmar apartado')).toBeDefined();
    expect(button('Guardar y continuar después')).toBeDefined();
});
it('explica la asignación cuando el usuario no puede responder', async () => {
    await mount({ tarea: { ...tarea, puede_preparar: false, responsable: { id: 99, name: 'Auxiliar de PDV' } } });
    expect(button('Confirmar apartado')).toBeUndefined();
    expect(container.textContent).toContain('Solo su responsable puede responder');
});

const scan = (value) => act(() => {
    const input = container.querySelector('#tienda-escanear');
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
    setter.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
});
it('resuelve lecturas consecutivas sin perder piezas y bloquea el envío mientras consulta', async () => {
    let resolver;
    get.mockReturnValueOnce(new Promise((resolve) => { resolver = resolve; }))
        .mockResolvedValue({ data: { data: [{ id: 7, sku: 'CREMA-7', descripcion: 'Crema' }] } });
    await mount({ tarea: { ...tarea, almacen: { id: 2 } } });
    await scan('CREMA-7'); await scan('CREMA-7');
    expect(button('Confirmar apartado').disabled).toBe(true);
    expect(get).toHaveBeenCalledTimes(1);
    await act(async () => resolver({ data: { data: [{ id: 7, sku: 'CREMA-7', descripcion: 'Crema' }] } }));
    expect(get).toHaveBeenCalledTimes(2);
    expect(container.querySelector('[aria-label="Cantidad de Crema"]')?.value || container.querySelector('#tienda-cantidad-0').value).toBe('2');
    await click('Confirmar apartado');
    expect(post.mock.calls[0][1].lineas[0].cantidad).toBe(2);
});
it('un error de lectura permite seguir escaneando y muestra el código que no se registró', async () => {
    get.mockRejectedValueOnce(new Error('Sin conexión')).mockResolvedValue({ data: { data: [{ id: 7, sku: 'CREMA-7', descripcion: 'Crema' }] } });
    await mount({ tarea: { ...tarea, almacen: { id: 2 } } });
    await scan('INVALIDO'); await scan('CREMA-7');
    await act(async () => {});
    expect(container.textContent).toContain('INVALIDO: Sin conexión');
    expect(container.querySelector('#tienda-cantidad-0').value).toBe('1');
    expect(button('Confirmar apartado').disabled).toBe(false);
});
it('señala requisitos de pesaje antes de enviar y acepta evidencias ya guardadas', async () => {
    const pesaje = { ...tarea, modalidad: { codigo: 'RECOGE_TIENDA_TRANSFERENCIA' }, puede_responder: true, productos: [{ id: 8, sku: 'CREMA-7', descripcion_snapshot: 'Crema', cantidad_encontrada: 1 }], peso_real_kg: '1.250' };
    const requisitos = { peso_real_obligatorio: true, evidencia_general_obligatoria: true, evidencia_por_producto: true };
    await mount({ tarea: pesaje, requisitos });
    await click('Responder preparación');
    expect(post).not.toHaveBeenCalled();
    expect(document.activeElement.id).toBe('tienda-fotos-0');
    expect(container.textContent).toContain('Adjunte al menos una evidencia');
    await mount({ tarea: pesaje, requisitos, documentosTarea: [{ id: 50, tipo_evidencia: 'evidencia_general', inmutable: false }, { id: 51, tipo_evidencia: 'evidencia_producto', pedido_bma_tarea_producto_id: 8 }] });
    await click('Responder preparación');
    expect(post.mock.calls[0][0]).toBe('control_pedidos.tienda.responder/1');
    expect(post.mock.calls[0][1].peso_real_kg).toBe('1.250');
});
it('al confirmar sin ubicación lleva el foco al campo pendiente', async () => {
    await mount({ tarea: { ...tarea, apartado: { version: 2, ubicacion: '' } } });
    await click('Elegir crema'); await click('Agregar pieza'); await click('Confirmar apartado');
    expect(document.activeElement.id).toBe('tienda-ubicacion');
    expect(document.activeElement.getAttribute('aria-invalid')).toBe('true');
    expect(post).not.toHaveBeenCalled();
});
