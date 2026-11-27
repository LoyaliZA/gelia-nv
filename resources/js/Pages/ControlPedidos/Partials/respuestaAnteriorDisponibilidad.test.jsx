// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import ConsultaSolicitudTienda from './ConsultaSolicitudTienda';
import SeguimientoSolicitudMunicipal from '../Municipal/SeguimientoSolicitudMunicipal';
import ResumenCambiosDisponibilidad from './ResumenCambiosDisponibilidad';
import RespuestaPreparacionTienda from './RespuestaPreparacionTienda';
import PanelRespuestaDisponibilidad from './PanelRespuestaDisponibilidad';
vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
let root, container;
beforeEach(() => { globalThis.IS_REACT_ACT_ENVIRONMENT = true; container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
const base = (extra = {}) => ({
    id: 1,
    solicitud_preparacion: {
        modalidad_entrega: 'tienda', area_revision: 'preparacion_tienda',
        revision_disponibilidad: { estado: 'excepciones_pendientes', respondida_at: '2026-11-01T10:00:00Z', actualizacion_pendiente: true, actualizacion_id: 'ciclo-2' },
        anexo_sin_remision: [{ solicitud_id: 'sol-1', entrada: 'catalogo', sku: 'B-2', descripcion: 'Parcial B', cantidad: 1, pendiente_revision: true,
            unidades: [{ unidad_id: 'anexo:sol-1:sku:B-2', clase: 'sku', resultado: 'incidencia', revision_ids: [22], respondida_at: '2026-11-02T09:00:00Z', respondida_por: 'Auxiliar capturando' }] }],
    },
    revisiones_producto: [
        { id: 11, estado_fisico: 'regular', descripcion_producto: 'Pieza de la respuesta anterior', cantidad_solicitada: 1 },
        { id: 22, estado_fisico: 'regular', descripcion_producto: 'Pieza revisada a medias', cantidad_solicitada: 1 },
    ],
    ...extra,
});
const estadosCambios = () => [...container.querySelectorAll('[aria-label="Productos de la respuesta"] [data-origen="cambio"] .tienda-respuesta-estado')].map(e => e.textContent);
it('Tienda: una pieza guardada por persistir_unidad no se mezcla con la respuesta anterior', async () => {
    await act(() => root.render(<ConsultaSolicitudTienda pedido={base()} />));
    expect(container.textContent).toContain('Pieza de la respuesta anterior');
    expect(container.textContent).not.toContain('Pieza revisada a medias');
    expect(container.textContent).toContain('Respuesta anterior de disponibilidad');
});
it('Tienda: al responder el ciclo la pieza aparece bajo la respuesta vigente', async () => {
    const pedido = base();
    pedido.solicitud_preparacion.revision_disponibilidad.actualizacion_pendiente = false;
    pedido.solicitud_preparacion.anexo_sin_remision[0].pendiente_revision = false;
    pedido.solicitud_preparacion.anexo_sin_remision[0].respondida_at = '2026-11-02T10:00:00Z';
    await act(() => root.render(<ConsultaSolicitudTienda pedido={pedido} />));
    expect(container.textContent).toContain('Pieza revisada a medias');
    expect(container.textContent).not.toContain('Respuesta anterior de disponibilidad');
});
it('Municipal: una pieza guardada por persistir_unidad no se mezcla con la respuesta anterior', async () => {
    const pedido = base();
    pedido.solicitud_preparacion.modalidad_entrega = 'municipal';
    await act(() => root.render(<SeguimientoSolicitudMunicipal pedido={pedido} />));
    expect(container.textContent).toContain('Pieza de la respuesta anterior');
    expect(container.textContent).not.toContain('Pieza revisada a medias');
});

it.each(['Tienda', 'CEDIS'])('Municipal en %s muestra la pieza original junto al SKU de reemplazo', async origen => {
    const pedido = base();
    const solicitud = pedido.solicitud_preparacion;
    solicitud.modalidad_entrega = 'municipal';
    solicitud.consulta_municipal = { area: origen === 'Tienda' ? 'tienda' : 'cedis' };
    pedido.revisiones_producto[0].descripcion_producto = 'Pieza con detalle 5';
    solicitud.acciones_disponibilidad = { 11: { revision_id: 11, accion: 'sustituir', descripcion: 'Pieza con detalle 5' } };
    solicitud.anexo_sin_remision[0].sustituye_revision_id = 11;

    await act(() => root.render(<PanelRespuestaDisponibilidad pedido={pedido} revision={solicitud.revision_disponibilidad} origen={origen} />));

    const sustitucion = container.querySelector('[data-sustitucion]');
    expect(sustitucion.textContent).toContain('Pieza a sustituirPieza con detalle 1');
    expect(sustitucion.textContent).toContain('Reemplazo solicitadoParcial B · B-2 · 1 pza(s)');
});

it('la respuesta de la tarea de Tienda tampoco publica las piezas ni fotos pendientes', async () => {
 const pedido = base({ tarea_preparacion: { estado: 'EN_ATENCION', respuesta: { documental: true, respondida: true,
  revision: { estado: 'excepciones_pendientes' }, productos: [], excepciones: [
   { id: 11, descripcion: 'Pieza anterior confirmada', estado_fisico: 'regular' },
   { id: 22, descripcion: 'Pieza pendiente', estado_fisico: 'regular', documentos: [{ url: '/foto-pendiente.png' }] },
  ] } } });
 await act(() => root.render(<ConsultaSolicitudTienda pedido={pedido} />));
 expect(container.textContent).toContain('Pieza anterior confirmada');
 expect(container.textContent).not.toContain('Pieza pendiente');
 expect(container.querySelector('img[src="/foto-pendiente.png"]')).toBeNull();
});

it.each(['correcto', 'incidencia'])('el resumen de Ventas conserva pendiente el avance %s hasta el envío', async resultado => {
 const solicitud = base().solicitud_preparacion;
 solicitud.anexo_sin_remision[0].unidades[0].resultado = resultado;
 await act(() => root.render(<ResumenCambiosDisponibilidad solicitud={solicitud} />));
 expect(container.textContent).toContain('Pendiente de revisión');
 expect(container.textContent).not.toContain('Todo OK');
 expect(container.textContent).not.toContain('Con piezas reportadas');
 solicitud.anexo_sin_remision[0].pendiente_revision = false;
 solicitud.anexo_sin_remision[0].respondida_at = '2026-11-02T10:00:00Z';
 await act(() => root.render(<ResumenCambiosDisponibilidad solicitud={solicitud} />));
 expect(container.textContent).toContain(resultado === 'correcto' ? 'Todo OK' : 'Con piezas reportadas');
});

it.each(['correcto', 'incidencia'])('municipal a tienda conserva pendiente el avance %s hasta el envío', async resultado => {
 const solicitud = base().solicitud_preparacion;
 solicitud.modalidad_entrega = 'municipal';
 solicitud.consulta_municipal = { area: 'tienda', estado: 'excepciones_pendientes', enviada_at: '2026-11-01T09:00:00Z' };
 delete solicitud.area_revision;
 solicitud.anexo_sin_remision[0].unidades[0].resultado = resultado;
 await act(() => root.render(<ResumenCambiosDisponibilidad solicitud={solicitud} />));
 expect(container.textContent).toContain('Pendiente de revisión');
 expect(container.textContent).not.toContain('Todo OK');
 expect(container.textContent).not.toContain('Con piezas reportadas');
 solicitud.anexo_sin_remision[0].pendiente_revision = false;
 solicitud.anexo_sin_remision[0].respondida_at = '2026-11-02T10:00:00Z';
 await act(() => root.render(<ResumenCambiosDisponibilidad solicitud={solicitud} />));
 expect(container.textContent).toContain(resultado === 'correcto' ? 'Todo OK' : 'Con piezas reportadas');
});

it('varias piezas guardadas conservan pendientes, evidencias y fecha anterior al recargar y hasta enviar', async () => {
 const pedido = base();
 const solicitud = pedido.solicitud_preparacion;
 const fechaAnterior = solicitud.revision_disponibilidad.respondida_at;
 const generalAnterior = { id: 91, tipo: 'evidencia_condicion', relacion_tipo: 'revision_general', url: '/conjunto-anterior.png', mime_type: 'image/png' };
 pedido.documentos = [generalAnterior, { id: 92, tipo: 'evidencia_condicion', relacion_tipo: 'revision_producto', relacion_id: 22, url: '/foto-borrador.png', mime_type: 'image/png' }];
 solicitud.revision_disponibilidad.evidencia_actual_ids = [91];
 solicitud.anexo_sin_remision.push(...['correcto', 'incidencia', null].map((resultado, i) => ({
  solicitud_id: `sol-${i + 2}`, entrada: 'catalogo', sku: `SKU-${i}`, descripcion: ['Revisión correcta', 'Pieza faltante', 'Pieza sin revisar'][i], cantidad: 1, pendiente_revision: true,
  unidades: [{ unidad_id: `anexo:sol-${i + 2}:sku:SKU-${i}`, resultado, revision_ids: resultado === 'incidencia' ? [23] : [],
   ...(resultado ? { respondida_at: '2026-11-02T09:00:00Z', respondida_por: 'Auxiliar capturando' } : {}) }],
 })));
 pedido.revisiones_producto.push({ id: 23, descripcion_producto: 'Faltante todavía no enviado', estado_fisico: 'sin_existencia', cantidad_solicitada: 1 });
 const render = async p => act(() => root.render(<ConsultaSolicitudTienda pedido={p} />));
 for (const lectura of [pedido, structuredClone(pedido)]) {
  await render(lectura);
  expect(estadosCambios()).toEqual(Array(4).fill('Por revisar'));
  expect(container.textContent).not.toContain('Cambios del pedido');
  expect(container.textContent).toContain('Pieza de la respuesta anterior');
  expect(container.textContent).not.toContain('Pieza revisada a medias');
  expect(container.textContent).not.toContain('Faltante todavía no enviado');
  expect(container.querySelector('img[src="/foto-borrador.png"]')).toBeNull();
  expect(container.querySelector('img[src="/conjunto-anterior.png"]')).not.toBeNull();
  expect(container.querySelector('[aria-label="Resultado de la revisión en tienda"]').textContent).toContain('2026-11-01');
 }
 expect(solicitud.revision_disponibilidad.respondida_at).toBe(fechaAnterior);
 // Después del envío ya se muestra el resultado de cada unidad, con la fecha del anexo.
 const enviada = structuredClone(pedido);
 const s = enviada.solicitud_preparacion;
 s.anexo_sin_remision[3].unidades[0].resultado = 'correcto';
 s.anexo_sin_remision.forEach(a => { a.pendiente_revision = false; a.respondida_at = '2026-11-03T10:00:00Z'; });
 s.revision_disponibilidad = { ...s.revision_disponibilidad, actualizacion_pendiente: false, respondida_at: '2026-11-03T10:00:00Z' };
 await render(enviada);
 expect(estadosCambios()).toEqual(['Con piezas reportadas', 'Con piezas reportadas']);
 expect([...container.querySelectorAll('.tienda-respuesta-oks .consulta-cambio-estado')].map(e => e.textContent)).toEqual(['Todo OK', 'Todo OK']);
 expect(container.querySelector('img[src="/foto-borrador.png"]')).not.toBeNull();
 expect(container.textContent).toContain('Faltante todavía no enviado');
});

it('una foto documental guardada no acredita una respuesta y conserva la presentación legada', async () => {
 const tarea = { estado: 'EN_ATENCION', respuesta: { documental: true, respondida: false, documentos: [{ url: '/guardada.jpg' }] } };
 await act(() => root.render(<RespuestaPreparacionTienda tarea={tarea} />));
 expect(container.textContent).toBe('');
 tarea.respuesta.documental = false;
 await act(() => root.render(<RespuestaPreparacionTienda tarea={tarea} />));
 expect(container.textContent).toContain('Evidencias registradas');
});
