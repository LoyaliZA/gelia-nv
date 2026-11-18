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
const detalle = async (texto = 'Caja rota', indice = 0) => act(() => {
 const textarea=container.querySelectorAll('textarea')[indice];
 Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype,'value').set.call(textarea,texto);
 textarea.dispatchEvent(new Event('input',{bubbles:true}));
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

it('una revisión recibida conserva descripción y archivos pendientes', async () => {
    const pedido = { id: 4, documentos: [] };
    await montar({ pedido });
    await estado(1); await click('Capturar sin catálogo');
    const input = container.querySelector('.gelia-cedis-producto input');
    await act(() => {
        Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, 'Captura pendiente');
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await foto();
    const archivos = container.querySelector('input[aria-label="Adjuntar: Captura del pedido"]').files;
    await montar({ pedido, revision: { actualizacion_id: 'recibida', actualizacion_pendiente: true } });
    expect(container.querySelector('.gelia-cedis-producto input').value).toBe('Captura pendiente');
    expect(container.querySelectorAll('textarea').length).toBeGreaterThan(0);
    expect(container.textContent).toContain('evidencia-0.png');
    expect(archivos.length).toBe(1);
});

it('conserva las miniaturas enviadas sin descargar documentos fuera de etapa y limpia el siguiente ciclo', async () => {
    const pedido = { id: 4, documentos: [] };
    await montar({ pedido });
    await estado(1); await click('Elegir perfume'); await interruptor('Única pieza');
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
    await click('Enviar respuesta');
    expect(onEnviar).toHaveBeenCalledOnce();
    expect(container.textContent).toContain('Adjunta evidencia en Captura del pedido.');
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
 await montar();await estado(1);await click('Elegir perfume');
 expect(container.querySelectorAll('[role=switch]')).toHaveLength(3);
 expect([...container.querySelectorAll('[role=switch]')].every(b=>b.getAttribute('aria-checked')==='false')).toBe(true);
 expect(container.querySelector('input[type=number],select')).toBeNull();
 expect(container.querySelector('details').open).toBe(false);
 expect(container.querySelector('[aria-label="Agregar evidencia del producto 1"]').disabled).toBe(false);
});
it('envía faltante sin detalle o foto de producto y conserva la captura general', async () => {
 await montar();await estado(1);await click('Elegir perfume');await interruptor('Sin existencias');
 expect(container.querySelector('textarea')).toBeNull();
 expect(container.querySelector('[aria-label="Agregar evidencia del producto 1"]').disabled).toBe(true);
 await foto();await click('Enviar respuesta');
 const form=onEnviar.mock.lastCall[0];
 expect(form.get('excepciones[0][sku]')).toBe('SKU-7');
 expect(form.get('excepciones[0][cantidad]')).toBe('1');
 expect(form.get('excepciones[0][detalle]')).toBe('Sin existencias');
 expect(form.get('excepciones[0][evidencias][]')).toBeNull();
});
it('quita una unidad sin desplegarla y no manda excepciones si cambia a conjunto correcto', async () => {
 await montar();await estado(1);await click('Elegir perfume');
 expect(container.querySelector('details').open).toBe(false);
 await act(() => container.querySelector('[aria-label="Quitar pieza 1"]').click());
 expect(container.querySelectorAll('.gelia-cedis-producto')).toHaveLength(0);
 await click('Elegir perfume');await estado(0);await foto();await click('Enviar respuesta');
 expect(onEnviar.mock.lastCall[0].get('excepciones[0][sku]')).toBeNull();
});
it('cada lectura repetida crea una unidad independiente, numerada y con retiro individual', async () => {
 axios.get.mockResolvedValue({data:{data:[{id:7,sku:'SKU-7',descripcion:'Perfume escaneado'}]}});
 await montar();await estado(1);await click('Escanear producto');await click('Escanear producto');
 expect(container.querySelectorAll('.gelia-cedis-producto')).toHaveLength(2);
 expect([...container.querySelectorAll('.gelia-cedis-unidad')].map(el=>el.textContent)).toEqual(['1/2','2/2']);
 await interruptor('Sin existencias',0);await interruptor('Sin existencias',1);await foto();await click('Enviar respuesta');
 const form=onEnviar.mock.lastCall[0];
 expect(form.get('excepciones[0][cantidad]')).toBe('1');expect(form.get('excepciones[1][cantidad]')).toBe('1');
 expect(axios.get.mock.lastCall[1].params.almacen_id).toBeUndefined();
});
it('separa fotos y captura y conserva ambos atributos independientes por unidad', async () => {
 await montar();await estado(1);await click('Elegir perfume');await detalle();
 await interruptor('Única pieza');await interruptor('Mejor ejemplar');await foto();await click('Enviar respuesta');
 expect(onEnviar).not.toHaveBeenCalled();expect(container.textContent).toContain('foto de cada producto');
 await foto('Fotos del producto',2);await click('Enviar respuesta');
 const form=onEnviar.mock.lastCall[0];
 expect(form.getAll('excepciones[0][evidencias][]')).toHaveLength(2);
 expect(form.get('evidencias[]').name).toBe('evidencia-0.png');
 expect(form.get('excepciones[0][unica_pieza]')).toBe('1');expect(form.get('excepciones[0][mejor_ejemplar]')).toBe('1');
});
it('la cámara acciona el input de captura, admite fotos sucesivas y limita a cinco', async () => {
 await montar();await estado(1);await click('Elegir perfume');
 const input=container.querySelector('input[aria-label="Tomar foto: Fotos del producto"]');
 const activar=vi.spyOn(input,'click');
 await act(()=>container.querySelector('[aria-label="Agregar evidencia del producto 1"]').click());
 expect(activar).toHaveBeenCalledOnce();
 expect(container.querySelector('.gelia-cedis-producto .gelia-pedidos-adjuntos-control')).toBeNull();
 await foto('Fotos del producto',2);await foto('Fotos del producto',4);
 expect(container.querySelectorAll('.gelia-cedis-producto .gelia-pedidos-evidencia')).toHaveLength(5);
 expect(container.querySelector('[aria-label="Agregar evidencia del producto 1"]').disabled).toBe(true);
 expect(container.textContent).toContain('hasta 5 fotos');
});
it('sin existencias borra detalle, fotos y atributos, que vuelven apagados al desactivarlo', async () => {
 await montar();await estado(1);await click('Elegir perfume');await detalle();await foto('Fotos del producto');await interruptor('Única pieza');await interruptor('Mejor ejemplar');
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
 await montar();await estado(1);await click('Elegir perfume');await interruptor(atributo);
 expect(container.textContent).toContain('Observaciones (opcional)');
 await foto('Fotos del producto');await foto();await click('Enviar respuesta');
 expect(onEnviar).toHaveBeenCalledOnce();
 const form=onEnviar.mock.lastCall[0];
 expect(form.get('excepciones[0][detalle]')).toBe('');
 expect(form.get('excepciones[0][cantidad]')).toBe('1');
});
it('un defecto sin atributos sigue exigiendo descripción aunque tenga fotos', async () => {
 await montar();await estado(1);await click('Elegir perfume');await foto('Fotos del producto');await foto();await click('Enviar respuesta');
 expect(onEnviar).not.toHaveBeenCalled();expect(container.textContent).toContain('Describe el defecto');
});
it('una actualización por captura reutiliza la respuesta por conjunto y exige evidencia nueva', async () => {
 const revision={estado:'sin_excepciones',actualizacion_pendiente:true,actualizacion_id:'123e4567-e89b-42d3-a456-426614174000'};
 await montar({revision,pedido:{documentos:[{id:1,tipo:'evidencia_condicion',relacion_tipo:'revision_general',url:'/anterior.jpg'}],solicitud_preparacion:{anexo_sin_remision:[{entrada:'documento',documento_id:2}]}}});
 expect(container.textContent).toContain('Actualización de consulta');
 expect(container.textContent).not.toContain('Confirmar disponibilidad');
 expect(container.querySelector('img[src="/anterior.jpg"]')).toBeNull();
 expect(container.querySelector('input[type=radio]')).toBeNull();
 await click('Enviar respuesta');expect(onEnviar).not.toHaveBeenCalled();
 await click('Todo OK');
 await foto();await click('Enviar respuesta');
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
 await interruptor('Sin existencias');await foto();await click('Enviar respuesta');
 expect(onEnviar.mock.lastCall[0].get('excepciones[0][descripcion]')).toBe('Pieza sin catálogo');
 expect(onEnviar.mock.lastCall[0].get('excepciones[0][condicion]')).toBe('faltante');
});
it('precarga SKU repetidos como solicitudes distintas y reporta el faltante sin buscarlos otra vez', async () => {
 await montar({revision:{estado:'sin_excepciones',actualizacion_pendiente:true},pedido:{solicitud_preparacion:{anexo_sin_remision:[
  {entrada:'catalogo',sku:'SKU-A',descripcion:'Aroma nuevo',cantidad:2},
  {entrada:'catalogo',sku:'SKU-A',descripcion:'Aroma nuevo',cantidad:1},
 ]}}});
 expect(container.querySelector('input[type=radio]')).toBeNull();
 expect(container.querySelectorAll('[data-tipo="anexo"]')).toHaveLength(2);
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
 await interruptor('Sin existencias');
 const parcial=onEnviar.mock.calls.find(c => c[0].get('persistir_unidad') === '1');
 expect(parcial).toBeTruthy();
 expect(parcial[0].get('cambios_revisados[0][resultado]')).toBe('incidencia');
 expect(parcial[0].get('excepciones[0][descripcion]')).toBe('Pieza de la captura');
});
it('permite marcar Todo OK desde la captura abierta y exige revisar cada documento pendiente', async () => {
 await montar({revision:{estado:'sin_excepciones',actualizacion_pendiente:true},pedido:{documentos:[{id:21,url:'/captura.png',mime_type:'image/png',nombre_original:'captura.png'}],solicitud_preparacion:{anexo_sin_remision:[{entrada:'documento',documento_id:21}]}}});
 await foto();await click('Enviar respuesta');expect(onEnviar).not.toHaveBeenCalled();
 await click('Abrir captura');
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
 const elegir=()=>act(()=>[...modal.querySelectorAll('button')].find(b=>b.textContent==='Elegir perfume').click());
 await elegir();await elegir();
 expect(container.querySelectorAll('.gelia-cedis-producto')).toHaveLength(0);
 expect(modal.querySelectorAll('[aria-label="Piezas seleccionadas"] li')).toHaveLength(2);
 await act(()=>[...modal.querySelectorAll('button')].find(b=>b.textContent==='Agregar al pedido (2)').click());
 expect(container.querySelectorAll('.gelia-cedis-producto')).toHaveLength(2);
 expect(container.querySelectorAll('textarea')).toHaveLength(2);
 expect(container.querySelectorAll('input[aria-label="Adjuntar: Fotos del producto"]')).toHaveLength(2);
 expect(container.textContent.toLowerCase()).not.toMatch(/\bincidencias?\b/);
 await foto();await click('Enviar respuesta');
 expect(onEnviar).not.toHaveBeenCalled();expect(container.textContent).toContain('Describe el defecto');
 await interruptor('Sin existencias',0);await interruptor('Sin existencias',1);await click('Enviar respuesta');
 expect(envioFinal()).toBeTruthy();
 expect(envioFinal().get('excepciones[0][sku]')).toBe('SKU-7');
 expect(envioFinal().get('excepciones[1][sku]')).toBe('SKU-7');
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
it('los cambios respondidos se muestran con Mostrar todos los cambios y la primera consulta no usa incidencia', async () => {
 await montar({revision:{estado:'sin_excepciones',actualizacion_pendiente:true},pedido:{solicitud_preparacion:{anexo_sin_remision:[{entrada:'catalogo',sku:'ANT',descripcion:'Pieza anterior',respondida_at:'2026-11-06T10:00:00Z'}]}}});
 expect(container.querySelector('summary').textContent).toBe('Mostrar todos los cambios (1)');
 await montar({revision:null,pedido:{}});
 expect(container.textContent).toContain('Todo OK');
 expect(container.textContent.toLowerCase()).not.toMatch(/\bincidencias?\b/);
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
