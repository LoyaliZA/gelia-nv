// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import PanelRespuestaDisponibilidad from './PanelRespuestaDisponibilidad';
import axios from 'axios';
vi.mock('axios', () => ({ default: { get: vi.fn() } }));
vi.mock('../../../Components/Almacenes/SelectorProducto', () => ({ default: ({ onChange, onEscanear, incluirSinInventario }) => <><button disabled={!incluirSinInventario} onClick={() => onChange(7, { id: 7, sku: 'SKU-7', descripcion: 'Perfume de catálogo' })}>Elegir perfume</button><button onClick={() => onEscanear('SKU-7')}>Escanear producto</button></> }));
let root, container;
const onEnviar = vi.fn();
const envioFinal = () => [...onEnviar.mock.calls].reverse().find(c => c[0].get('persistir_unidad') !== '1')?.[0];
const montar = (props = {}) => act(() => root.render(<PanelRespuestaDisponibilidad tomada puedeResponder onEnviar={onEnviar} {...props} />));
const click = texto => act(async () => [...container.querySelectorAll('button')].find(b => b.textContent.trim() === texto).click());
const pulsarIndice = (indice, texto) => act(() => [...container.querySelector(`[data-indice="${indice}"]`).querySelectorAll('button')].find(b => b.textContent === texto).click());
const estado = indice => act(() => container.querySelectorAll('input[type=radio]')[indice].click());
const interruptor = (nombre, indice = 0) => act(() => [...container.querySelectorAll(`[role=switch][aria-label="${nombre}"]`)][indice].click());
const foto = async (label = 'Captura del pedido', cantidad = 1, indice = 0) => {
 const input = container.querySelectorAll(`input[aria-label="Adjuntar: ${label}"]`)[indice];
 Object.defineProperty(input, 'files', { configurable: true, value: Array.from({length:cantidad},(_,i)=>new File(['foto'],`evidencia-${i}.png`,{type:'image/png'})) });
 await act(() => input.dispatchEvent(new Event('change', { bubbles: true })));
};
const fotosModal = async (modal, cantidad) => {
 const entrada = modal.querySelector('input[aria-label="Adjuntar: Fotos del producto"]');
 Object.defineProperty(entrada, 'files', { configurable: true, value: Array.from({ length: cantidad }, (_, i) => new File(['foto'], `pieza-${i}.png`, { type: 'image/png' })) });
 await act(() => entrada.dispatchEvent(new Event('change', { bubbles: true })));
};
const agregarPieza = async (cantidad = 1) => {
 await click('Agregar productos con detalle');
 const modal = document.querySelector('[aria-label="Registrar detalle de productos"]');
 await fotosModal(modal, cantidad);
 await act(() => [...modal.querySelectorAll('button')].find(b => b.textContent === `Agregar al pedido (${cantidad})`).click());
};
const detalle = async (texto = 'Caja rota', indice = 0) => act(() => {
 const textarea=container.querySelectorAll('textarea')[indice];
 Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype,'value').set.call(textarea,texto);
 textarea.dispatchEvent(new Event('input',{bubbles:true}));
});

it('reconcilia la identidad tras guardar sin perder foto, texto ni duplicar la pieza al recuperarla', async () => {
 const revision = { estado: 'sin_excepciones', actualizacion_pendiente: true, actualizacion_id: 'misma-consulta' };
 const original = { id: 4, documentos: [{ id: 90, tipo: 'evidencia_condicion', relacion_tipo: 'revision_general', url: '/conjunto.png' }],
  solicitud_preparacion: { anexo_sin_remision: [{ entrada: 'catalogo', sku: 'A', descripcion: 'Pieza de prueba', cantidad: 1, pendiente_revision: true }] } };
 await montar({ origen: 'Tienda', pedido: original, revision });
 await click('Con detalle'); await foto('Fotos del producto'); await detalle('Tapa dañada');
 const campo = container.querySelector('textarea');
 const fotoLocal = container.querySelector('.gelia-cedis-producto img').getAttribute('src');
 await act(async () => { await new Promise(r => setTimeout(r, 500)); });
 expect(onEnviar.mock.lastCall[0].get('excepciones[0][origen_unidad_id]')).toBe('anexo:legacy:0:A');
 const pedido = { ...original, excepciones: [{ id: 12, sku: 'A', descripcion: 'Pieza de prueba', cantidad: 1, estado_fisico: 'regular', detalle: 'Tapa dañada', documentos: [{ url: '/guardada.png' }] }],
  solicitud_preparacion: { anexo_sin_remision: [{ ...original.solicitud_preparacion.anexo_sin_remision[0], linea_id: 'linea-definitiva',
   unidades: [{ unidad_id: 'anexo:linea-definitiva:sku:A', resultado: 'incidencia', revision_ids: [12] }] }] } };
 await montar({ origen: 'Tienda', pedido, revision });
 expect(container.querySelectorAll('textarea')).toHaveLength(1);
 expect(container.querySelector('textarea')).toBe(campo);
 expect(campo.value).toBe('Tapa dañada');
 expect(container.querySelector('.gelia-cedis-producto img').getAttribute('src')).toBe(fotoLocal);
 await click('Enviar respuesta');
 expect(envioFinal().get('excepciones[0][origen_unidad_id]')).toBe('anexo:linea-definitiva:sku:A');
 expect(envioFinal().get('cambios_revisados[0][unidad_id]')).toBe('anexo:linea-definitiva:sku:A');
 expect(envioFinal().get('excepciones[1][descripcion]')).toBeNull();
});

it('incorpora piezas repetidas de una captura, quita una selección y envía cada detalle con su unidad', async () => {
 const unidad = 'anexo:linea-unica:doc:21';
 await montar({ origen: 'Tienda', revision: { estado: 'sin_excepciones', actualizacion_pendiente: true, actualizacion_id: 'consulta' }, pedido: {
  documentos: [{ id: 21, url: '/anexo.png', mime_type: 'image/png' }, { id: 90, tipo: 'evidencia_condicion', relacion_tipo: 'revision_general', url: '/conjunto.png' }],
  solicitud_preparacion: { anexo_sin_remision: [{ entrada: 'documento', linea_id: 'linea-unica', documento_id: 21,
   unidades: [{ unidad_id: unidad, documento_id: 21, resultado: null }] }] },
 } });
 await click('Registrar detalle');
 const modal = document.querySelector('[aria-label="Registrar detalle de productos"]');
 const entrada = modal.querySelector('input[aria-label="Adjuntar: Fotos del producto"]');
 Object.defineProperty(entrada, 'files', { configurable: true, value: Array.from({ length: 3 }, (_, i) => new File(['foto'], `pieza-${i}.png`, { type: 'image/png' })) });
 await act(() => entrada.dispatchEvent(new Event('change', { bubbles: true })));
 expect(modal.querySelectorAll('[aria-label="Piezas seleccionadas"] li')).toHaveLength(3);
 await act(() => [...modal.querySelectorAll('button')].find(b => b.textContent === 'Agregar al pedido (3)').click());
 await act(() => container.querySelector('[aria-label="Quitar pieza 2"]').click());
 expect(container.querySelectorAll('.gelia-cedis-producto')).toHaveLength(2);
 await detalle('Defecto primero', 0); await detalle('Defecto tercero', 1);
 await click('Enviar respuesta');
 expect(envioFinal().get('excepciones[0][detalle]')).toBe('Defecto primero');
 expect(envioFinal().get('excepciones[1][detalle]')).toBe('Defecto tercero');
 for (let i = 0; i < 2; i++) {
  expect(envioFinal().get(`excepciones[${i}][origen_unidad_id]`)).toBe(unidad);
  expect(envioFinal().getAll(`excepciones[${i}][evidencias][]`)).toHaveLength(1);
 }
 expect(envioFinal().get('excepciones[2][descripcion]')).toBeNull();
});

it('las piezas de un cambio ya respondido pasan al historial y no abruman al empacador', async () => {
 await montar({ origen: 'Tienda', revision: { estado: 'sin_excepciones', actualizacion_pendiente: true, actualizacion_id: 'consulta' }, pedido: { id: 4, documentos: [],
  excepciones: [{ id: 12, sku: 'A', descripcion: 'Pieza ya respondida', cantidad: 1, estado_fisico: 'regular', detalle: 'Tapa', documentos: [] }],
  solicitud_preparacion: { anexo_sin_remision: [{ entrada: 'catalogo', sku: 'A', descripcion: 'Pieza ya respondida', cantidad: 1, linea_id: 'l1', pendiente_revision: true,
   unidades: [{ unidad_id: 'anexo:l1:sku:A', resultado: 'incidencia', revision_ids: [12] }] }] } } });
 expect(container.querySelectorAll('.gelia-cedis-producto')).toHaveLength(0);
 expect(container.querySelector('.gelia-cedis-archivo summary').textContent).toBe('Ya respondidas · 1');
 expect(container.querySelector('.gelia-cedis-archivo').textContent).toContain('Pieza ya respondida');
});

it('Tienda no intenta guardar de nuevo una unidad de una respuesta ya enviada y el envío final no se bloquea', async () => {
 await montar({ origen: 'Tienda', revision: { estado: 'sin_excepciones', actualizacion_pendiente: true, actualizacion_id: 'ciclo' }, pedido: { id: 4,
  documentos: [{ id: 90, tipo: 'evidencia_condicion', relacion_tipo: 'revision_general', url: '/conjunto.png' }],
  excepciones: [{ id: 12, sku: 'V', descripcion: 'Ya enviado', cantidad: 1, estado_fisico: 'sin_existencia', documentos: [] }],
  solicitud_preparacion: { anexo_sin_remision: [
   { solicitud_id: 'viejo', entrada: 'catalogo', sku: 'V', descripcion: 'Ya enviado', cantidad: 1, pendiente_revision: false, estado: 'con_detalle', respondida_at: '2026-11-01T10:00:00Z',
     unidades: [{ unidad_id: 'anexo:viejo:sku:V', resultado: 'incidencia', revision_ids: [12] }] },
   { solicitud_id: 'nuevo', entrada: 'catalogo', sku: 'N', descripcion: 'Nuevo', cantidad: 1, pendiente_revision: true },
  ] } } });
 await pulsarIndice(1, 'Todo OK');
 await click('Enviar respuesta');
 const unidades = onEnviar.mock.calls.filter(c => c[0].get('persistir_unidad') === '1').map(c => c[0].get('cambios_revisados[0][unidad_id]'));
 expect(unidades.some(id => String(id).includes('viejo'))).toBe(false);
 expect(envioFinal()).toBeTruthy();
 expect(container.textContent).not.toContain('ya pertenece a una respuesta enviada');
});

it('responde dos ciclos del mismo pedido sin desmontar ni reutilizar la captura anterior', async () => {
    const pedido = { id: 4, documentos: [] };
    await montar({ pedido });
    await estado(0); await foto(); await click('Enviar respuesta');
    expect(container.textContent).toContain('Respuesta enviada a Ventas.');
    await montar({ pedido, revision: { respuesta_id: 'primera', estado: 'sin_excepciones' } });
    await montar({ pedido, revision: { respuesta_id: 'primera', estado: 'sin_excepciones', actualizacion_id: 'nueva', actualizacion_pendiente: true } });
    expect(container.textContent).toContain('Actualización de consulta');
    expect(container.textContent).not.toContain('Respuesta enviada a Ventas.');
    await click('Enviar respuesta');
    expect(onEnviar).toHaveBeenCalledOnce();
    expect(container.textContent).toContain('Adjunta evidencia en Captura del pedido.');
    await foto(); await click('Enviar respuesta');
    expect(onEnviar).toHaveBeenCalledTimes(2);
    expect(onEnviar.mock.lastCall[0].get('actualizacion_id')).toBe('nueva');
});

it('una revisión recibida conserva comentario y archivos pendientes', async () => {
    const pedido = { id: 4, documentos: [] };
    await montar({ pedido });
    await estado(1); await agregarPieza();
    await detalle('Captura pendiente');
    await foto();
    const archivos = container.querySelector('input[aria-label="Adjuntar: Captura del pedido"]').files;
    await montar({ pedido, revision: { actualizacion_id: 'recibida', actualizacion_pendiente: true } });
    expect(container.querySelector('.gelia-cedis-producto textarea').value).toBe('Captura pendiente');
    expect(container.textContent).toContain('evidencia-0.png');
    expect(archivos.length).toBe(1);
});

it('conserva las miniaturas enviadas sin descargar documentos fuera de etapa y limpia el siguiente ciclo', async () => {
    const pedido = { id: 4, documentos: [] };
    await montar({ pedido });
    await estado(1); await agregarPieza(); await interruptor('Única pieza');
    await foto('Fotos del producto'); await foto(); await click('Enviar respuesta');
    const guardada = { ...pedido, documentos: [{ id: 21, tipo: 'evidencia_condicion', relacion_tipo: 'revision_general', mime_type: 'image/png', url: '/etapa-terminada.png' }] };
    const revision = { respuesta_id: 'guardada', estado: 'excepciones_pendientes', evidencia_actual_ids: [21] };
    await montar({ pedido: guardada, revision, puedeLeerDocumentos: false,
        piezas: [{ id: 7, descripcion: 'Perfume de catálogo', estado_fisico: 'regular' }], documentos: [{ id: 1, url: '/wizerp.pdf' }] });
    const imagenes = [...container.querySelectorAll('img')];
    expect(imagenes.length).toBeGreaterThanOrEqual(2);
    expect(imagenes.every(img => img.src.startsWith('blob:'))).toBe(true);
    expect([...container.querySelectorAll('button')].find(b => b.textContent.startsWith('Ver pedido')).disabled).toBe(true);
    expect(URL.revokeObjectURL).not.toHaveBeenCalled();
    await montar({ pedido: guardada, revision: { ...revision, actualizacion_id: 'siguiente', actualizacion_pendiente: true }, puedeLeerDocumentos: true });
    expect(container.textContent).toContain('Captura vigente del pedido');
    expect(container.querySelector('img[src="/etapa-terminada.png"]')).not.toBeNull();
    expect(container.textContent).toContain('Nueva captura (opcional)');
});
beforeEach(() => {
 globalThis.IS_REACT_ACT_ENVIRONMENT = true;
 globalThis.route = name => name;
 let secuencia=0;
 URL.createObjectURL = vi.fn(() => `blob:foto-${++secuencia}`); URL.revokeObjectURL = vi.fn();
 onEnviar.mockReset();onEnviar.mockResolvedValue({});
 container = document.createElement('div');document.body.append(container);root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount());container.remove(); });

it('recupera atributos, detalle y fotos de una unidad de Tienda y envía sin recapturarlos', async () => {
 const recuperar = vi.fn().mockResolvedValue({ ok: true, blob: async () => new Blob(['foto'], { type: 'image/png' }) });
 vi.stubGlobal('fetch', recuperar);
 try {
  await montar({ origen: 'Tienda', revision: { estado: 'sin_excepciones', actualizacion_pendiente: true, actualizacion_id: 'ciclo' },
   pedido: { excepciones: [{ id: 12, descripcion: 'Pieza recuperada', cantidad: 1, estado_fisico: 'regular', detalle: 'Tapa rota', unica_pieza: true,
    documentos: [{ id: 99, url: '/foto-guardada.png', nombre_original: 'foto.png', mime_type: 'image/png' }] }],
    documentos: [{ id: 90, tipo: 'evidencia_condicion', relacion_tipo: 'revision_general', url: '/conjunto.png' }],
    solicitud_preparacion: { anexo_sin_remision: [{ solicitud_id: 's', entrada: 'catalogo', descripcion: 'Pieza recuperada', pendiente_revision: true,
     unidades: [{ unidad_id: 'anexo:s:sku:', resultado: 'incidencia', revision_ids: [12] }] }] } } });
  expect(container.querySelector('.gelia-cedis-archivo summary').textContent).toBe('Ya respondidas · 1');
  expect(container.querySelectorAll('.gelia-cedis-producto')).toHaveLength(0);
  await click('Enviar respuesta');
  expect(envioFinal().get('excepciones[0][unica_pieza]')).toBe('1');
  expect(envioFinal().get('excepciones[0][detalle]')).toBe('Tapa rota');
  expect(envioFinal().getAll('excepciones[0][evidencias][]')).toHaveLength(1);
  expect(recuperar).toHaveBeenCalledOnce();
 } finally { vi.unstubAllGlobals(); }
});

it('espera y reintenta el avance fallido antes de permitir el envío final de Tienda', async () => {
 const props = { origen: 'Tienda', revision: { estado: 'sin_excepciones', actualizacion_pendiente: true }, pedido: {
  documentos: [{ id: 90, tipo: 'evidencia_condicion', relacion_tipo: 'revision_general', url: '/conjunto.png' }],
  solicitud_preparacion: { anexo_sin_remision: [{ entrada: 'catalogo', descripcion: 'Pieza pendiente', sku: 'A', cantidad: 1, pendiente_revision: true }] },
 } };
 await montar(props); await click('Todo OK');
 onEnviar.mockRejectedValue({ response: { data: { message: 'Error de prueba al guardar' } } });
 await click('Enviar respuesta');
 expect(envioFinal()).toBeUndefined();
 expect(container.textContent).toContain('Error de prueba al guardar');
 onEnviar.mockResolvedValue({}); await click('Enviar respuesta');
 expect(envioFinal()).toBeDefined();
 expect(envioFinal().get('cambios_revisados[0][resultado]')).toBe('correcto');
});
it('en municipal reutiliza origen y destino con almacén y terminal persistidos', async () => {
    await montar({ pedido: { almacen: { nombre: 'CEDIS Norte' }, solicitud_preparacion: { modalidad_entrega: 'municipal', consulta_municipal: { area: 'cedis' } }, paqueteria: { nombre: 'Terminal ficticia' } }, documentos: [{ tipo: 'pdf_pedido', url: '/wizerp.pdf' }] });
    expect(container.textContent).toContain('Terminal ficticia');
    expect(container.textContent).toContain('CEDIS');
    expect(container.textContent).toContain('Ver pedido');
    expect(container.textContent).not.toContain('Por confirmar');
});
it('exige estado y captura y responde el conjunto sin transcribir piezas correctas', async () => {
 await montar();await click('Enviar respuesta');expect(container.textContent).toContain('Indica el estado');
 await estado(0);await click('Enviar respuesta');expect(container.textContent).toContain('Adjunta evidencia');
 await foto();await click('Enviar respuesta');
 expect(onEnviar).toHaveBeenCalledTimes(1);
 expect(onEnviar.mock.lastCall[0].get('confirmacion_explicita')).toBe('1');
 expect([...onEnviar.mock.lastCall[0].keys()].some(k => k.startsWith('excepciones'))).toBe(false);
});
it('inicia una unidad con los tres interruptores apagados, sin cantidad ni resultado y contraída', async () => {
 await montar();await estado(1);await agregarPieza();
 expect(container.querySelectorAll('[role=switch]')).toHaveLength(3);
 expect([...container.querySelectorAll('[role=switch]')].every(b=>b.getAttribute('aria-checked')==='false')).toBe(true);
 expect(container.querySelector('input[type=number],select')).toBeNull();
 expect(container.querySelector('details').open).toBe(false);
 expect(container.querySelector('[aria-label="Agregar evidencia del producto 1"]').disabled).toBe(false);
});
it('envía faltante sin detalle o foto de producto y conserva la captura general', async () => {
 await montar();await estado(1);await agregarPieza();await interruptor('Sin existencias');
 expect(container.querySelector('textarea')).toBeNull();
 expect(container.querySelector('[aria-label="Agregar evidencia del producto 1"]').disabled).toBe(true);
 await foto();await click('Enviar respuesta');
 const form=onEnviar.mock.lastCall[0];
 expect(form.get('excepciones[0][descripcion]')).toBe('Pieza con detalle 1');
 expect(form.get('excepciones[0][cantidad]')).toBe('1');
 expect(form.get('excepciones[0][detalle]')).toBe('Sin existencias');
 expect(form.get('excepciones[0][evidencias][]')).toBeNull();
});
it('quita una unidad sin desplegarla y no manda excepciones si cambia a conjunto correcto', async () => {
 await montar();await estado(1);await agregarPieza();
 expect(container.querySelector('details').open).toBe(false);
 await act(() => container.querySelector('[aria-label="Quitar pieza 1"]').click());
 expect(container.querySelectorAll('.gelia-cedis-producto')).toHaveLength(0);
 await agregarPieza();await estado(0);await foto();await click('Enviar respuesta');
 expect(onEnviar.mock.lastCall[0].get('excepciones[0][sku]')).toBeNull();
});
it('cada lectura repetida del diálogo crea una pieza sin existencias independiente, numerada y con retiro individual', async () => {
 axios.get.mockResolvedValue({data:{data:[{id:7,sku:'SKU-7',descripcion:'Perfume escaneado'}]}});
 await montar();await estado(1);await click('Agregar productos con detalle');
 const modal=document.querySelector('[aria-label="Registrar detalle de productos"]');
 const escanear=()=>act(async()=>[...modal.querySelectorAll('button')].find(b=>b.textContent==='Escanear producto').click());
 await escanear();await escanear();
 await act(()=>[...modal.querySelectorAll('button')].find(b=>b.textContent==='Agregar al pedido (2)').click());
 expect(container.querySelectorAll('.gelia-cedis-producto')).toHaveLength(2);
 expect([...container.querySelectorAll('.gelia-cedis-unidad')].map(el=>el.textContent)).toEqual(['1/2','2/2']);
 await foto();await click('Enviar respuesta');
 const form=onEnviar.mock.lastCall[0];
 expect(form.get('excepciones[0][cantidad]')).toBe('1');expect(form.get('excepciones[1][cantidad]')).toBe('1');
 expect(form.get('excepciones[0][condicion]')).toBe('faltante');expect(form.get('excepciones[1][condicion]')).toBe('faltante');
 expect(axios.get.mock.lastCall[1].params.almacen_id).toBeUndefined();
});
it('separa fotos y captura y conserva ambos atributos independientes por unidad', async () => {
 await montar();await estado(1);await agregarPieza();await detalle();
 await interruptor('Única pieza');await interruptor('Mejor ejemplar');await foto();
 await foto('Fotos del producto',1);await click('Enviar respuesta');
 const form=onEnviar.mock.lastCall[0];
 expect(form.getAll('excepciones[0][evidencias][]')).toHaveLength(2);
 expect(form.get('evidencias[]').name).toBe('evidencia-0.png');
 expect(form.get('excepciones[0][unica_pieza]')).toBe('1');expect(form.get('excepciones[0][mejor_ejemplar]')).toBe('1');
});
it('la cámara acciona el input de captura, admite fotos sucesivas sin tope', async () => {
 await montar();await estado(1);await agregarPieza();
 const input=container.querySelector('input[aria-label="Tomar foto: Fotos del producto"]');
 const activar=vi.spyOn(input,'click');
 await act(()=>container.querySelector('[aria-label="Agregar evidencia del producto 1"]').click());
 expect(activar).toHaveBeenCalledOnce();
 expect(container.querySelector('.gelia-cedis-producto .gelia-pedidos-adjuntos-control')).toBeNull();
 await foto('Fotos del producto',2);await foto('Fotos del producto',4);
 expect(container.querySelectorAll('.gelia-cedis-producto .gelia-pedidos-evidencia')).toHaveLength(7);
 expect(container.querySelector('[aria-label="Agregar evidencia del producto 1"]').disabled).toBe(false);
 expect(container.textContent).not.toContain('hasta 5 fotos');
});
it('sin existencias borra detalle, fotos y atributos, que vuelven apagados al desactivarlo', async () => {
 await montar();await estado(1);await agregarPieza();await detalle();await foto('Fotos del producto');await interruptor('Única pieza');await interruptor('Mejor ejemplar');
 await interruptor('Sin existencias');
 expect(container.querySelector('[aria-label="Adjuntar: Fotos del producto"]')).toBeNull();
 expect(container.querySelector('[aria-label="Única pieza"]').getAttribute('aria-checked')).toBe('false');
 expect(container.querySelector('[aria-label="Mejor ejemplar"]').disabled).toBe(true);
 await interruptor('Sin existencias');
 expect(container.querySelector('textarea').value).toBe('');
 expect(container.querySelector('[aria-label="Única pieza"]').disabled).toBe(false);
 expect(container.querySelectorAll('.gelia-pedidos-evidencia')).toHaveLength(0);
});

it.each(['Única pieza','Mejor ejemplar'])('permite reportar %s con foto sin observación escrita ni cantidad capturada', async atributo => {
 await montar();await estado(1);await agregarPieza();await interruptor(atributo);
 expect(container.textContent).toContain('Observaciones (opcional)');
 await foto('Fotos del producto');await foto();await click('Enviar respuesta');
 expect(onEnviar).toHaveBeenCalledOnce();
 const form=onEnviar.mock.lastCall[0];
 expect(form.get('excepciones[0][detalle]')).toBe('');
 expect(form.get('excepciones[0][cantidad]')).toBe('1');
});
it('una pieza sin estado no se envía aunque tenga fotos, y el comentario es opcional', async () => {
 await montar();await estado(1);await agregarPieza();await foto('Fotos del producto');await foto();await click('Enviar respuesta');
 expect(onEnviar).not.toHaveBeenCalled();expect(container.textContent).toContain('Indica el estado de cada pieza');
});
it('una actualización por captura conserva la captura vigente y la nueva es opcional', async () => {
 const revision={estado:'sin_excepciones',actualizacion_pendiente:true,actualizacion_id:'123e4567-e89b-42d3-a456-426614174000'};
 await montar({revision,pedido:{documentos:[{id:1,tipo:'evidencia_condicion',relacion_tipo:'revision_general',url:'/anterior.jpg'}],solicitud_preparacion:{anexo_sin_remision:[{entrada:'documento',documento_id:2}]}}});
 expect(container.textContent).toContain('Actualización de consulta');
 expect(container.textContent).not.toContain('Confirmar disponibilidad');
 expect(container.querySelector('img[src="/anterior.jpg"]')).not.toBeNull();
 expect(container.textContent).toContain('Captura vigente del pedido');
 expect(container.querySelector('input[type=radio]')).toBeNull();
 await click('Enviar respuesta');expect(onEnviar).not.toHaveBeenCalled();
 await click('Todo OK');
 await foto('Nueva captura (opcional)');await click('Enviar respuesta');
 expect(envioFinal().get('actualizacion_id')).toBe(revision.actualizacion_id);
 expect(envioFinal().get('evidencias[]').name).toBe('evidencia-0.png');
 expect([...envioFinal().keys()].some(k=>k.startsWith('excepciones'))).toBe(false);
});
it('Tienda responde una actualización con faltantes manuales usando la misma captura', async () => {
 await montar({origen:'Tienda',revision:{estado:'sin_excepciones',actualizacion_pendiente:true},pedido:{solicitud_preparacion:{anexo_sin_remision:[{entrada:'documento',descripcion:'Piezas en captura'}]}}});
 expect(container.textContent).toContain('Actualización de consulta · Tienda');
 await click('Registrar detalle');
 const modal=document.querySelector('[aria-label="Registrar detalle de productos"]');
 await act(()=>[...modal.querySelectorAll('button')].find(b=>b.textContent==='Sin catálogo').click());
 const input=modal.querySelector('input[maxlength="255"]');
 await act(()=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(input,'Pieza sin catálogo');input.dispatchEvent(new Event('input',{bubbles:true}));});
 await act(()=>[...modal.querySelectorAll('button')].find(b=>b.textContent==='Agregar pieza').click());
 await act(()=>[...modal.querySelectorAll('button')].find(b=>b.textContent==='Agregar al pedido (1)').click());
 expect(document.querySelector('[aria-label="Registrar detalle de productos"]')).toBeNull();
 await foto();await click('Enviar respuesta');
 expect(onEnviar.mock.lastCall[0].get('excepciones[0][descripcion]')).toBe('Pieza sin catálogo');
 expect(onEnviar.mock.lastCall[0].get('excepciones[0][condicion]')).toBe('faltante');
});
it('precarga SKU repetidos como solicitudes distintas y reporta el faltante sin buscarlos otra vez', async () => {
 await montar({revision:{estado:'sin_excepciones',actualizacion_pendiente:true},pedido:{solicitud_preparacion:{anexo_sin_remision:[
  {entrada:'catalogo',sku:'SKU-A',descripcion:'Aroma nuevo',cantidad:2},
  {entrada:'catalogo',sku:'SKU-A',descripcion:'Aroma nuevo',cantidad:1},
 ]}}});
 expect(container.querySelector('input[type=radio]')).toBeNull();
 expect(container.querySelectorAll('article[data-tipo="anexo"]')).toHaveLength(2);
 await pulsarIndice(0,'Sin existencias');
 await pulsarIndice(1,'Todo OK');
 await foto();await click('Enviar respuesta');
 const form=envioFinal();
 expect(form.get('excepciones[0][sku]')).toBe('SKU-A');
 expect(form.get('excepciones[0][cantidad]')).toBe('2');
 expect(form.get('cambios_revisados[0][resultado]')).toBe('incidencia');
 expect(form.get('cambios_revisados[1][resultado]')).toBe('correcto');
});
it('una solicitud con SKU y dos capturas se responde por archivo', async () => {
 await montar({revision:{estado:'sin_excepciones',actualizacion_pendiente:true},pedido:{
  documentos:[{id:10,url:'/a.png',mime_type:'image/png',nombre_original:'a.png'},{id:11,url:'/b.png',mime_type:'image/png',nombre_original:'b.png'}],
  solicitud_preparacion:{anexo_sin_remision:[{solicitud_id:'sol-a',entrada:'catalogo',sku:'SKU-1',descripcion:'Aroma',documentos:[{id:10},{id:11}]}]},
 }});
 expect(container.querySelectorAll('[data-clase="captura"]')).toHaveLength(2);
 await pulsarIndice(0,'Todo OK');
 await foto();await click('Enviar respuesta');
 expect(envioFinal()).toBeUndefined();
 expect(container.textContent).toContain('Revise cada captura');
});
it('persiste la incidencia de una captura cuando el detalle queda completo', async () => {
 await montar({revision:{estado:'sin_excepciones',actualizacion_pendiente:true},pedido:{
  documentos:[{id:10,url:'/a.png',mime_type:'image/png',nombre_original:'a.png'}],
  solicitud_preparacion:{anexo_sin_remision:[{solicitud_id:'sol-a',entrada:'documento',documento_id:10,documentos:[{id:10}]}]},
 }});
 await click('Registrar detalle');
 const modal=document.querySelector('[aria-label="Registrar detalle de productos"]');
 await act(()=>[...modal.querySelectorAll('button')].find(b=>b.textContent==='Sin catálogo').click());
 const input=modal.querySelector('input[maxlength="255"]');
 await act(()=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(input,'Pieza de la captura');input.dispatchEvent(new Event('input',{bubbles:true}));});
 await act(()=>[...modal.querySelectorAll('button')].find(b=>b.textContent==='Agregar pieza').click());
 await act(()=>[...modal.querySelectorAll('button')].find(b=>b.textContent==='Agregar al pedido (1)').click());
 const parcial=onEnviar.mock.calls.find(c => c[0].get('persistir_unidad') === '1');
 expect(parcial).toBeTruthy();
 expect(parcial[0].get('cambios_revisados[0][resultado]')).toBe('incidencia');
 expect(parcial[0].get('excepciones[0][descripcion]')).toBe('Pieza de la captura');
});
it('permite marcar Todo OK desde la captura abierta y exige revisar cada documento pendiente', async () => {
 await montar({revision:{estado:'sin_excepciones',actualizacion_pendiente:true},pedido:{documentos:[{id:21,url:'/captura.png',mime_type:'image/png',nombre_original:'captura.png'}],solicitud_preparacion:{anexo_sin_remision:[{entrada:'documento',documento_id:21}]}}});
 await foto();await click('Enviar respuesta');expect(onEnviar).not.toHaveBeenCalled();
 await act(() => container.querySelector('.consulta-cambio-miniatura').click());
 expect(document.querySelector('[aria-label="Visor de evidencia"]')).not.toBeNull();
 await act(()=>[...document.querySelector('[aria-label="Visor de evidencia"]').querySelectorAll('button')].find(b=>b.textContent==='Todo OK').click());
 expect(document.querySelector('[aria-label="Visor de evidencia"]')).toBeNull();
 await click('Enviar respuesta');
 expect(onEnviar.mock.calls.some(c => c[0].get('persistir_unidad') === '1')).toBe(true);
 expect(envioFinal().get('cambios_revisados[0][resultado]')).toBe('correcto');
 expect(envioFinal().get('persistir_unidad')).toBeNull();
});
it('volver a Todo OK retira solo las incidencias de esa solicitud', async () => {
 await montar({revision:{estado:'sin_excepciones',actualizacion_pendiente:true},pedido:{solicitud_preparacion:{anexo_sin_remision:[{entrada:'catalogo',sku:'A',descripcion:'Aroma',cantidad:1},{entrada:'catalogo',sku:'B',descripcion:'Crema',cantidad:1}]}}});
 await pulsarIndice(0,'Sin existencias');await pulsarIndice(1,'Sin existencias');await pulsarIndice(0,'Todo OK');
 await foto();await click('Enviar respuesta');
 expect(envioFinal().get('excepciones[0][sku]')).toBe('B');
 expect(envioFinal().get('excepciones[1][sku]')).toBeNull();
});
it('selecciona varias piezas en una ventana rápida y las incorpora juntas para completar detalle y fotos', async () => {
 await montar({revision:{estado:'sin_excepciones',actualizacion_pendiente:true},pedido:{solicitud_preparacion:{anexo_sin_remision:[{entrada:'documento'}]}}});
 await click('Registrar detalle');
 const modal=document.querySelector('[aria-label="Registrar detalle de productos"]');
 await fotosModal(modal,2);
 expect(container.querySelectorAll('.gelia-cedis-producto')).toHaveLength(0);
 expect(modal.querySelectorAll('[aria-label="Piezas seleccionadas"] li')).toHaveLength(2);
 await act(()=>[...modal.querySelectorAll('button')].find(b=>b.textContent==='Agregar al pedido (2)').click());
 expect(container.querySelectorAll('.gelia-cedis-producto')).toHaveLength(2);
 expect(container.querySelectorAll('textarea')).toHaveLength(2);
 expect(container.querySelectorAll('input[aria-label="Adjuntar: Fotos del producto"]')).toHaveLength(2);
 expect(container.textContent.toLowerCase()).not.toMatch(/\bincidencias?\b/);
 await foto();await click('Enviar respuesta');
 expect(onEnviar).not.toHaveBeenCalled();expect(container.textContent).toContain('Indica el estado de cada pieza');
 await interruptor('Única pieza',0);await interruptor('Mejor ejemplar',1);await click('Enviar respuesta');
 expect(envioFinal()).toBeTruthy();
 expect(envioFinal().getAll('excepciones[0][evidencias][]')).toHaveLength(1);
 expect(envioFinal().getAll('excepciones[1][evidencias][]')).toHaveLength(1);
 expect(envioFinal().get('excepciones[0][descripcion]')).toBe('Pieza con detalle 1');
 expect(envioFinal().get('cambios_revisados[0][resultado]')).toBe('incidencia');
});
it('cancelar la selección rápida conserva el pedido sin piezas agregadas ni respuesta a la captura', async () => {
 const onDirtyChange=vi.fn();
 await montar({onDirtyChange,revision:{estado:'sin_excepciones',actualizacion_pendiente:true},pedido:{solicitud_preparacion:{anexo_sin_remision:[{entrada:'documento'}]}}});
 await click('Registrar detalle');
 const modal=document.querySelector('[aria-label="Registrar detalle de productos"]');
 await act(()=>[...modal.querySelectorAll('button')].find(b=>b.textContent==='Elegir perfume').click());
 expect(onDirtyChange).toHaveBeenLastCalledWith(true);
 await act(()=>[...modal.querySelectorAll('button')].find(b=>b.textContent==='Cancelar').click());
 expect(container.querySelectorAll('.gelia-cedis-producto')).toHaveLength(0);
 expect(onDirtyChange).toHaveBeenLastCalledWith(false);
 await foto();await click('Enviar respuesta');
 expect(onEnviar).not.toHaveBeenCalled();expect(container.textContent).toContain('Revise cada captura');
});
it('un retiro informado no exige recaptura ni reconfirmación', async () => {
 await montar({revision:{estado:'sin_excepciones'},pedido:{documentos:[{id:50,tipo:'evidencia_condicion',relacion_tipo:'revision_general',url:'/conjunto.jpg',mime_type:'image/jpeg'}],solicitud_preparacion:{retiro_piezas:[{estado:'aplicada',piezas:[{descripcion:'Faltante retirado',sku:'SKU-8',cantidad:1}]}]}}});
 expect(container.textContent).toContain('Consulta respondida');
 expect(container.textContent).not.toContain('Actualización de consulta');
 expect(container.textContent).not.toContain('Retiro revisado');
 expect(container.querySelector('.consulta-cambios-actuales').textContent).toContain('Faltante retirado');
 expect(container.querySelector('.consulta-cambios-historial')).toBeNull();
});

it('los cambios respondidos se muestran con Mostrar todos los cambios y la primera consulta no usa incidencia', async () => {
 await montar({revision:{estado:'sin_excepciones',actualizacion_pendiente:true},pedido:{solicitud_preparacion:{revision_disponibilidad:{estado:'sin_excepciones',respondida_at:'2026-11-06T10:00:00Z'},anexo_sin_remision:[{entrada:'catalogo',sku:'ANT',descripcion:'Pieza anterior',solicitado_at:'2026-11-06T09:00:00Z',respondida_at:'2026-11-06T10:00:00Z'}]}}});
 expect(container.querySelector('summary').textContent).toBe('Movimientos (1)');
 await montar({revision:null,pedido:{}});
 expect(container.textContent).toContain('Todo OK');
 expect(container.textContent.toLowerCase()).not.toMatch(/\bincidencias?\b/);
});

it('despliega los cambios del ciclo actual y deja los anteriores en Movimientos', async () => {
 await montar({revision:{estado:'sin_excepciones',actualizacion_pendiente:true},pedido:{solicitud_preparacion:{
  revision_disponibilidad:{estado:'sin_excepciones',respondida_at:'2026-11-06T10:00:00Z',actualizacion_pendiente:true},
  anexo_sin_remision:[
   {entrada:'catalogo',sku:'ANT',descripcion:'Pieza anterior',solicitado_at:'2026-11-06T09:00:00Z',respondida_at:'2026-11-06T10:00:00Z'},
   {entrada:'catalogo',sku:'NUE',descripcion:'Pieza nueva',solicitado_at:'2026-11-06T11:00:00Z'},
  ],
  retiro_piezas:[{estado:'pendiente',solicitado_at:'2026-11-06T11:05:00Z',piezas:[{descripcion:'Retiro nuevo',sku:'R',cantidad:1}]}],
 }}});
 const actuales = container.querySelector('.consulta-cambios-actuales').textContent;
 expect(actuales).toContain('Pieza nueva');
 expect(actuales).toContain('Retiro nuevo');
 expect(actuales).not.toContain('Pieza anterior');
 expect(container.querySelector('summary').textContent).toBe('Movimientos (1)');
 expect(container.querySelector('.consulta-cambios-historial').textContent).toContain('Pieza anterior');
});

it('reutiliza fotos guardadas de la revisión inicial de tienda y exige captura nueva al actualizar', async () => {
 const guardadas = [{ id: 50, url: '/conjunto.jpg', mime_type: 'image/jpeg', nombre_original: 'conjunto.jpg' }];
 await montar({ origen: 'Tienda', evidenciasGuardadas: guardadas });
 await estado(0); await click('Enviar respuesta');
 expect(onEnviar.mock.lastCall[0].getAll('evidencias_guardadas[]')).toEqual(['50']);
 await act(() => root.unmount()); root = createRoot(container); onEnviar.mockClear();
 await montar({ origen: 'Tienda', evidenciasGuardadas: guardadas, revision: { estado: 'sin_excepciones', actualizacion_pendiente: true } });
 await click('Enviar respuesta');
 expect(onEnviar).not.toHaveBeenCalled();
 expect(container.textContent).toContain('Adjunta evidencia en Captura del pedido');
});
it('una decisión previa de Ventas no bloquea el envío: se revisan solo las capturas del anexo vigente', async () => {
 await montar({revision:{estado:'excepciones_pendientes',actualizacion_pendiente:true,actualizacion_id:'ciclo-2'},pedido:{solicitud_preparacion:{
  revision_disponibilidad:{respondida_at:'2026-11-01T10:00:00Z'},
  acciones_disponibilidad:{9:{accion:'confirmar',revision_id:9,fecha:'2026-11-02T10:00:00Z'},10:{accion:'retirar',revision_id:10,fecha:'2026-11-02T11:00:00Z'}},
  anexo_sin_remision:[{solicitud_id:'sol-n',entrada:'catalogo',sku:'SKU-N',descripcion:'Nueva',cantidad:1}],
 }}});
 await pulsarIndice(0,'Todo OK');
 await foto();await click('Enviar respuesta');
 expect(container.textContent).not.toContain('Revise cada captura');
 expect(envioFinal()).toBeDefined();
});
it('un anexo nuevo llegado por props conserva la captura local y aparece sin cerrar el panel', async () => {
 const solicitud=(n)=>({revision_disponibilidad:{respondida_at:'2026-11-01T10:00:00Z'},anexo_sin_remision:Array.from({length:n},(_,i)=>({solicitud_id:`sol-${i}`,entrada:'catalogo',sku:`SKU-${i}`,descripcion:`Pieza ${i}`,cantidad:1}))});
 await montar({revision:{estado:'sin_excepciones',actualizacion_pendiente:true,actualizacion_id:'c2'},pedido:{solicitud_preparacion:solicitud(1)}});
 await pulsarIndice(0,'Todo OK');
 await montar({revision:{estado:'sin_excepciones',actualizacion_pendiente:true,actualizacion_id:'c3'},pedido:{solicitud_preparacion:solicitud(2)}});
 expect(container.querySelectorAll('article[data-tipo="anexo"]')).toHaveLength(2);
 await foto();await click('Enviar respuesta');
 expect(container.textContent).toContain('Revise cada captura');
 await pulsarIndice(1,'Todo OK');await click('Enviar respuesta');
 expect(envioFinal().get('cambios_revisados[0][resultado]')).toBe('correcto');
 expect(envioFinal().get('cambios_revisados[1][resultado]')).toBe('correcto');
});

it('el diálogo Registrar detalle permite elegir el caso y el comentario de cada pieza y llegan a la fila', async () => {
 await montar({ origen: 'Tienda', revision: { estado: 'sin_excepciones', actualizacion_pendiente: true, actualizacion_id: 'consulta' }, pedido: {
  documentos: [{ id: 21, url: '/anexo.png', mime_type: 'image/png' }],
  solicitud_preparacion: { anexo_sin_remision: [{ entrada: 'documento', linea_id: 'linea-unica', documento_id: 21,
   unidades: [{ unidad_id: 'anexo:linea-unica:doc:21', documento_id: 21, resultado: null }] }] },
 } });
 await click('Registrar detalle');
 const modal = document.querySelector('[aria-label="Registrar detalle de productos"]');
 await fotosModal(modal, 1);
 await act(() => modal.querySelector('[role=switch][aria-label="Mejor ejemplar"]').click());
 await act(() => modal.querySelector('[aria-label^="Agregar comentario a"]').click());
 const nota = modal.querySelector('textarea');
 Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set.call(nota, 'Caja abierta');
 await act(() => nota.dispatchEvent(new Event('input', { bubbles: true })));
 await act(() => [...modal.querySelectorAll('button')].find(b => b.textContent === 'Agregar al pedido (1)').click());
 const fila = container.querySelector('.gelia-cedis-producto');
 expect(fila.querySelector('[role=switch][aria-label="Mejor ejemplar"]').getAttribute('aria-checked')).toBe('true');
 expect(fila.querySelector('[role=switch][aria-label="Única pieza"]').getAttribute('aria-checked')).toBe('false');
 expect(fila.querySelector('textarea').value).toBe('Caja abierta');
});

it('la primera respuesta con detalle usa el diálogo de fotos y no la búsqueda por catálogo', async () => {
 await montar();await estado(1);
 expect(container.textContent).toContain('Agregar productos con detalle');
 expect(container.textContent).not.toContain('Buscar o escanear producto');
 expect(container.textContent).not.toContain('Capturar sin catálogo');
});
it('una pieza buscada en el diálogo entra como Sin existencias, sin foto ni comentario', async () => {
 await montar({ origen: 'Tienda', revision: { estado: 'sin_excepciones', actualizacion_pendiente: true, actualizacion_id: 'consulta' }, pedido: {
  documentos: [{ id: 21, url: '/anexo.png', mime_type: 'image/png' }],
  solicitud_preparacion: { anexo_sin_remision: [{ entrada: 'documento', linea_id: 'linea-unica', documento_id: 21,
   unidades: [{ unidad_id: 'anexo:linea-unica:doc:21', documento_id: 21, resultado: null }] }] },
 } });
 await click('Registrar detalle');
 const modal = document.querySelector('[aria-label="Registrar detalle de productos"]');
 await act(() => [...modal.querySelectorAll('button')].find(b => b.textContent === 'Elegir perfume').click());
 expect(modal.querySelector('[aria-label^="Agregar comentario a"]')).toBeNull();
 await act(() => [...modal.querySelectorAll('button')].find(b => b.textContent === 'Agregar al pedido (1)').click());
 const fila = container.querySelector('.gelia-cedis-producto');
 expect(fila.querySelector('[role=switch][aria-label="Sin existencias"]').getAttribute('aria-checked')).toBe('true');
 expect(fila.textContent).toContain('Sin existencias');
});

it('cada foto del diálogo abre su propia pieza con detalle y llega adjunta a la fila', async () => {
 URL.createObjectURL = vi.fn(() => 'blob:pieza');
 URL.revokeObjectURL = vi.fn();
 await montar({ origen: 'Tienda', revision: { estado: 'sin_excepciones', actualizacion_pendiente: true, actualizacion_id: 'consulta' }, pedido: {
  documentos: [{ id: 21, url: '/anexo.png', mime_type: 'image/png' }],
  solicitud_preparacion: { anexo_sin_remision: [{ entrada: 'documento', linea_id: 'linea-unica', documento_id: 21,
   unidades: [{ unidad_id: 'anexo:linea-unica:doc:21', documento_id: 21, resultado: null }] }] },
 } });
 await click('Registrar detalle');
 const modal = document.querySelector('[aria-label="Registrar detalle de productos"]');
 const input = modal.querySelector('input[aria-label="Adjuntar: Fotos del producto"]');
 Object.defineProperty(input, 'files', { configurable: true, value: [new File(['foto'], 'pieza.png', { type: 'image/png' })] });
 await act(() => input.dispatchEvent(new Event('change', { bubbles: true })));
 await act(() => [...modal.querySelectorAll('button')].find(b => b.textContent === 'Agregar al pedido (1)').click());
 const fila = container.querySelector('.gelia-cedis-producto');
 expect(fila.querySelector('.gelia-cedis-foto img')).not.toBeNull();
 expect(fila.textContent).toContain('1 foto');
});

it('la fila ofrece el lápiz bajo el bote para abrir el comentario y las fotos', async () => {
 await montar();await estado(1);await agregarPieza();
 const lapiz = container.querySelector('.gelia-cedis-acciones [aria-label^="Agregar comentario o fotos"]');
 expect(lapiz.getAttribute('aria-expanded')).toBe('false');
 expect(container.querySelector('details').open).toBe(false);
 await act(() => lapiz.click());
 expect(container.querySelector('details').open).toBe(true);
 expect(container.querySelector('.gelia-cedis-acciones [aria-expanded="true"]')).not.toBeNull();
});
