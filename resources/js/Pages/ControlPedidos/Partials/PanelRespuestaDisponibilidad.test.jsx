// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import PanelRespuestaDisponibilidad from './PanelRespuestaDisponibilidad';
vi.mock('../../../Components/Almacenes/SelectorProducto', () => ({ default: ({ onChange, incluirSinInventario }) => <button disabled={!incluirSinInventario} onClick={() => onChange(7, { id: 7, sku: 'SKU-7', descripcion: 'Perfume de catálogo' })}>Elegir perfume</button> }));
vi.mock('../Tienda/Partials/DocumentoSolicitudTienda', () => ({ default: () => <div>Documento de referencia</div> }));
let root, container;
const onEnviar = vi.fn();
const montar = () => act(() => root.render(<PanelRespuestaDisponibilidad tomada puedeResponder onEnviar={onEnviar} />));
const click = texto => act(() => [...container.querySelectorAll('button')].find(b => b.textContent.trim() === texto).click());
const estado = indice => act(() => container.querySelectorAll('input[type=radio]')[indice].click());
const foto = async () => {
 const input = container.querySelector('input[type=file][multiple]');
 Object.defineProperty(input, 'files', { configurable: true, value: [new File(['foto'], 'evidencia.png', { type: 'image/png' })] });
 await act(() => input.dispatchEvent(new Event('change', { bubbles: true })));
};
beforeEach(() => {
 globalThis.IS_REACT_ACT_ENVIRONMENT = true;
 globalThis.route = name => name;
 URL.createObjectURL = vi.fn(() => 'blob:foto'); URL.revokeObjectURL = vi.fn();
 onEnviar.mockReset();onEnviar.mockResolvedValue({});
 container = document.createElement('div');document.body.append(container);root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount());container.remove(); });
it('exige estado y evidencia y responde el conjunto sin capturar piezas correctas', async () => {
 await montar();await click('Enviar respuesta');expect(container.textContent).toContain('Indica el estado');
 await estado(0);await click('Enviar respuesta');expect(container.textContent).toContain('Adjunta evidencia');
 await foto();await click('Enviar respuesta');
 expect(onEnviar).toHaveBeenCalledTimes(1);
 expect(onEnviar.mock.lastCall[0].get('confirmacion_explicita')).toBe('1');
 expect([...onEnviar.mock.lastCall[0].keys()].some(k => k.startsWith('excepciones'))).toBe(false);
 expect(container.textContent).toContain('Consulta respondida');
});
it('consulta catálogo sin inventario y envía SKU, cantidad y faltante sin redactar', async () => {
 await montar();await estado(1);await click('Elegir perfume');await click('Agregar');await foto();await click('Enviar respuesta');
 const form = onEnviar.mock.lastCall[0];
 expect(form.get('excepciones[0][sku]')).toBe('SKU-7');
 expect(form.get('excepciones[0][detalle]')).toBe('Sin existencias');
 expect(form.get('excepciones[0][condicion]')).toBe('faltante');
 expect(form.get('confirmacion_explicita')).toBe('0');
});
it('retira una pieza de la captura y no envía excepciones al cambiar a conjunto correcto', async () => {
 await montar();await estado(1);await click('Elegir perfume');await click('Agregar');
 await act(() => container.querySelector('[aria-label="Quitar pieza 1"]').click());
 expect(container.textContent).not.toContain('Perfume de catálogo');
 await click('Elegir perfume');await click('Agregar');await estado(0);await foto();await click('Enviar respuesta');
 expect(onEnviar.mock.lastCall[0].get('excepciones[0][sku]')).toBeNull();
 expect(container.textContent).not.toContain('Perfume de catálogo');
});
