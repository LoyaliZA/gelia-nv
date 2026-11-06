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
const montar = (props = {}) => act(() => root.render(<PanelRespuestaDisponibilidad tomada puedeResponder onEnviar={onEnviar} {...props} />));
const click = texto => act(() => [...container.querySelectorAll('button')].find(b => b.textContent.trim() === texto).click());
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
beforeEach(() => {
 globalThis.IS_REACT_ACT_ENVIRONMENT = true;
 globalThis.route = name => name;
 let secuencia=0;
 URL.createObjectURL = vi.fn(() => `blob:foto-${++secuencia}`); URL.revokeObjectURL = vi.fn();
 onEnviar.mockReset();onEnviar.mockResolvedValue({});
 container = document.createElement('div');document.body.append(container);root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount());container.remove(); });
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
