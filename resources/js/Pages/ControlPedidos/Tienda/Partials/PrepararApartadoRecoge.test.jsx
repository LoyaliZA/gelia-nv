// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import PrepararApartadoRecoge from './PrepararApartadoRecoge';

const post = vi.hoisted(() => vi.fn());
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
