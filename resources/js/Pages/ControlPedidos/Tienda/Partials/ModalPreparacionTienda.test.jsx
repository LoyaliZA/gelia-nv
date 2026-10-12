// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import ModalPreparacionTienda from './ModalPreparacionTienda';

const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
let importarFotos;
vi.mock('./ModalSesionEvidenciaTienda', () => ({ default: ({ onImportado }) => { importarFotos = onImportado; return null; } }));
vi.mock('axios', () => ({ default: api }));
vi.mock('../../Partials/VisorPdfPaginas', () => ({ default: ({ url, titulo }) => <div data-preview={url}>{titulo}</div> }));
vi.mock('../../../../Components/Almacenes/SelectorProducto', () => ({ default: ({ onChange }) => <button onClick={() => onChange(7, { id: 7, sku: 'CREMA-7', descripcion: 'Crema' })}>Elegir crema</button> }));
let root;
let container;
let actualizar;
const auth = { user: { id: 10, permissions: [], roles: ['Super Admin'] } };
const data = (estado = 'EN_ATENCION', version = 1) => ({
    tarea: { id: 13, estado, estado_label: estado, version, puede_preparar: true, puede_tomar: true,
        responsable: { id: 10 }, pedido: { folio: 'PBMA-2026-00032' }, apartado: { version, ubicacion: 'Anaquel A' }, productos: [] },
    documentos_solicitud: [{ id: 4, nombre_original: 'Pedido.pdf', mime_type: 'application/pdf' }], documentos: [],
});
const button = (label) => [...document.querySelectorAll('button')].find((b) => b.textContent === label);
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = (name, id) => `${name}/${Array.isArray(id) ? id.join('/') : id}`;
    api.get.mockReset().mockResolvedValue({ data: data() });
    api.post.mockReset().mockResolvedValue({ data: { message: 'Apartado confirmado.' } });
    actualizar = vi.fn();
    container = document.createElement('div'); document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
const mount = async (props = {}) => act(async () => { root.render(<ModalPreparacionTienda tareaId={13} auth={auth} onClose={vi.fn()} onActualizado={actualizar} {...props} />); });
const click = async (label) => act(async () => { button(label).click(); });
it('abre el pedido y su PDF dentro del modal con las acciones de respuesta', async () => {
    await mount();
    expect(document.querySelector('[role="dialog"]').textContent).toContain('PBMA-2026-00032');
    expect(document.querySelector('[data-preview]').getAttribute('data-preview')).toBe('control_pedidos.tienda.solicitud.documento/13/4');
    expect(button('Confirmar apartado')).toBeDefined();
    expect(document.querySelector('a[target="_blank"]')).toBeNull();
    expect(api.post).not.toHaveBeenCalled();
});
it('atiende y prepara sin navegar a una página nueva', async () => {
    api.get.mockResolvedValueOnce({ data: data('PENDIENTE') }).mockResolvedValue({ data: data('EN_ATENCION', 2) });
    await mount({ atender: true });
    expect(api.post).toHaveBeenCalledWith('control_pedidos.tienda.tomar/13', { version: 1 }, expect.objectContaining({ headers: { Accept: 'application/json' } }));
    expect(button('Confirmar apartado')).toBeDefined();
    expect(api.get).toHaveBeenCalledTimes(2);
});
it('guarda la respuesta y conserva el modal abierto con el resultado', async () => {
    await mount(); await click('Elegir crema'); await click('Agregar pieza'); await click('Confirmar apartado');
    expect(api.post).toHaveBeenCalledWith('control_pedidos.tienda.apartado.confirmar/13', expect.objectContaining({ lineas: [expect.objectContaining({ sku: 'CREMA-7', cantidad: 1 })] }), expect.anything());
    expect(actualizar).toHaveBeenCalled();
    expect(document.querySelector('[role="dialog"]')).not.toBeNull();
    expect(document.querySelector('[role="status"]').textContent).toContain('Apartado confirmado.');
});
it('permite cerrar mientras se está cargando el pedido', async () => {
    api.get.mockImplementation(() => new Promise(() => {}));
    const cerrar = vi.fn();
    await mount({ onClose: cerrar });
    const control = document.querySelector('[aria-label="Cerrar preparación"]');
    expect(control.disabled).toBe(false);
    await act(async () => control.click());
    expect(cerrar).toHaveBeenCalledOnce();
});

it('actualiza evidencias importadas sin descartar productos capturados ni la protección al cerrar', async () => {
    const cerrar = vi.fn();
    await mount({ onClose: cerrar });
    await click('Elegir crema'); await click('Agregar pieza');
    const nuevaData = data('EN_ATENCION', 3);
    nuevaData.documentos = [{ id: 50, tipo_evidencia: 'evidencia_general', nombre_original: 'foto.jpg', mime_type: 'image/jpeg', url: '/foto.jpg' }];
    api.get.mockResolvedValue({ data: nuevaData });
    await act(async () => importarFotos());
    expect(document.querySelector('#tienda-cantidad-0').value).toBe('1');
    expect(document.body.textContent).toContain('1 evidencias ya guardadas');
    await act(async () => document.querySelector('[aria-label="Cerrar preparación"]').click());
    expect(cerrar).not.toHaveBeenCalled();
    expect(button('Cerrar sin guardar')).toBeDefined();
});
