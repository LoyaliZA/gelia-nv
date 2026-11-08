// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import ModalPreparacionTienda from './ModalPreparacionTienda';
import { TarjetaTarea } from './TarjetasTienda';

const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
let importarFotos;
vi.mock('../../Partials/SeguimientoTransferenciaTienda', () => ({ default: () => <div>Seguimiento de transferencia y custodia</div> }));
vi.mock('./ModalSesionEvidenciaTienda', () => ({ default: ({ onImportado }) => { importarFotos = onImportado; return null; } }));
vi.mock('axios', () => ({ default: api }));
vi.mock('@inertiajs/react', async importOriginal => ({ ...await importOriginal(), Link: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>, usePage: () => ({ props: { flash: {} } }) }));
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
    vi.stubGlobal('localStorage', { getItem: () => null, setItem: vi.fn() });
    globalThis.route = (name, id) => `${name}/${Array.isArray(id) ? id.join('/') : id}`;
    api.get.mockReset().mockResolvedValue({ data: data() });
    api.post.mockReset().mockResolvedValue({ data: { message: 'Apartado confirmado.' } });
    actualizar = vi.fn();
    container = document.createElement('div'); document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount()); container.remove(); vi.unstubAllGlobals(); });
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

it('recorre respuesta, espera de Ventas y confirmación física única sin recapturar piezas', async () => {
 const inicial = data(); inicial.tarea.pedido = { ...inicial.tarea.pedido, area_revision: 'preparacion_tienda' };
 const respondida = structuredClone(inicial); respondida.tarea.pedido.revision_disponibilidad = { estado: 'sin_excepciones' };
 api.get.mockResolvedValueOnce({ data: inicial }).mockResolvedValue({ data: respondida });
 await mount();
 expect(button('Confirmar apartado')).toBeUndefined();
 expect(button('Guardar y continuar después')).toBeUndefined();
 await act(() => document.querySelector('input[type=radio]').click());
 const foto = document.querySelector('input[aria-label="Adjuntar: Captura del pedido"]');
 await act(() => { Object.defineProperty(foto, 'files', { configurable: true, value: [new File(['foto'], 'conjunto.jpg', { type: 'image/jpeg' })] }); foto.dispatchEvent(new Event('change', { bubbles: true })); });
 await click('Enviar respuesta');
 expect(api.post.mock.lastCall[0]).toBe('control_pedidos.tienda.disponibilidad/13');
 expect(button('Confirmar pedido separado')).toBeUndefined();
 expect(document.body.textContent).toContain('Espere el cierre de la consulta');
 // Al reabrir el modal se adopta la confirmación vigente enviada por Ventas.
 const aprobada = structuredClone(respondida);
 aprobada.tarea.pedido.consulta_cerrada = true;
 aprobada.tarea.pedido.cobertura_tienda = { tipo: 'apartado', requiere_confirmacion_pedido: true, confirmacion_enviada_at: '2026-11-08T10:00:00Z' };
 aprobada.tarea.pedido.seguimiento_entrega_pdv_habilitado = true;
 aprobada.tarea.pedido.anexos_por_separar = [{ indice: 0, estado: 'revisado_conjunto' }];
 api.get.mockResolvedValue({ data: aprobada });
 await act(() => root.unmount()); root = createRoot(container); await mount();
 expect(button('Confirmar separación del anexo')).toBeUndefined();
 expect(button('Elegir crema')).toBeUndefined();
 const separada = structuredClone(aprobada); separada.tarea.estado = 'RESPONDIDA'; separada.tarea.apartado.estado = 'APARTADO_CONFIRMADO';
 api.get.mockResolvedValue({ data: separada });
 await click('Confirmar pedido separado');
 expect(api.post.mock.lastCall[0]).toBe('control_pedidos.tienda.apartado.confirmar/13');
 expect(api.post.mock.lastCall[1]).toEqual({ version: 1, lineas: [] });
 expect(button('Confirmar pedido separado')).toBeUndefined();
 expect(button('Confirmar apartado')).toBeUndefined();
});

it('una consulta cerrada por transferencia usa seguimiento en lugar de volver a preparar piezas', async () => {
 const transferencia = data(); transferencia.tarea.modalidad = { codigo: 'RECOGE_TIENDA_TRANSFERENCIA' };
 transferencia.tarea.pedido = { ...transferencia.tarea.pedido, area_revision: 'preparacion_tienda', consulta_cerrada: true, es_custodia_documental: true };
 api.get.mockResolvedValue({ data: transferencia });
 await mount();
 expect(document.body.textContent).toContain('Seguimiento de transferencia y custodia');
 expect(button('Responder preparación')).toBeUndefined();
 expect(button('Confirmar apartado')).toBeUndefined();
});

it('el acceso posterior aparece solo al habilitarlo y navega fuera de la consulta', async () => {
 const abrir = vi.fn();
 const tarea = { ...data('RESPONDIDA').tarea, pedido: { ...data().tarea.pedido, es_t01_documental: true }, apartado: { estado: 'APARTADO_CONFIRMADO' } };
 await act(() => root.render(<TarjetaTarea tarea={tarea} onAbrir={abrir} />));
 expect(button('Pago y entrega')).toBeUndefined();
 expect([...container.querySelectorAll('a')].some(a => a.textContent === 'Pago y entrega')).toBe(false);
 await act(() => root.render(<TarjetaTarea tarea={{ ...tarea, pedido: { ...tarea.pedido, seguimiento_entrega_pdv_habilitado: true } }} onAbrir={abrir} />));
 const acceso = [...container.querySelectorAll('a')].find(a => a.textContent === 'Pago y entrega');
 expect(acceso.getAttribute('href')).toBe('control_pedidos.tienda.show/13');
 expect(abrir).not.toHaveBeenCalled();
});

it('permite separar con horarios apagados aunque la fecha conservada esté vencida', async () => {
 const aprobada = data();
 aprobada.tarea.fecha_limite = '2020-11-04T17:00:00-06:00';
 aprobada.tarea.pedido = { ...aprobada.tarea.pedido, area_revision: 'preparacion_tienda', horarios_activos: false,
  consulta_cerrada: true, revision_disponibilidad: { estado: 'sin_excepciones' },
  cobertura_tienda: { tipo: 'apartado', requiere_confirmacion_pedido: true, confirmacion_enviada_at: '2020-11-04T10:00:00-06:00' } };
 api.get.mockResolvedValue({ data: aprobada });
 await mount();
 expect(button('Confirmar pedido separado').disabled).toBe(false);
 expect(container.textContent).not.toContain('Vigencia terminada');
 expect(container.textContent).not.toContain('vigencia del apartado terminó');
});
