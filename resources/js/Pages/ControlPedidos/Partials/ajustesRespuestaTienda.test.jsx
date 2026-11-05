// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import RespuestaPreparacionTienda from './RespuestaPreparacionTienda';
import { AjustePiezas } from './SeguimientoSolicitudTienda';
vi.mock('../../../Components/Almacenes/SelectorProducto', () => ({ default: ({ onChange }) => <button onClick={() => onChange(1, { id: 1, sku: 'SKU-1', descripcion: 'Perfume del catálogo' })}>Elegir perfume</button> }));
let root, container;
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = name => name;
    URL.createObjectURL = vi.fn(() => 'blob:captura-prueba');
    URL.revokeObjectURL = vi.fn();
    container = document.createElement('div'); document.body.append(container); root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
const click = texto => act(() => [...container.querySelectorAll('button')].find(b => b.textContent.trim() === texto).click());
it('sin existencias no mezcla atributos ni repite el resultado o la explicación automática', async () => {
    await act(() => root.render(<RespuestaPreparacionTienda tarea={{ respuesta: { respondida: true, productos: [{ id: 1, descripcion: 'Perfume', estado_fisico: 'sin_existencia', observacion: 'Sin existencias', mejor_ejemplar: true, unica_pieza: true }], revision: { estado: 'excepciones_pendientes' }, observaciones: 'La respuesta incluye piezas con excepción. El pedido se cierra con Cerrar consulta. Para cambiar piezas, registre un anexo o un retiro.' } }} />));
    expect(container.textContent.match(/Sin existencias/g)).toHaveLength(1);
    expect(container.textContent).not.toContain('Mejor ejemplar');
    expect(container.textContent).not.toContain('Única pieza');
    expect(container.textContent).not.toContain('El pedido se cierra');
    expect(container.textContent).toContain('Con incidencias');
});
it('la imagen adjunta tiene miniatura y abre el visor con el archivo seleccionado', async () => {
    const onVer = vi.fn();
    await act(() => root.render(<AjustePiezas pedidoId={1} pedido={{}} solicitud={{}} onVer={onVer} />));
    const input = container.querySelector('input[type="file"][multiple]');
    Object.defineProperty(input, 'files', { value: [new File(['imagen'], 'pieza.png', { type: 'image/png' })] });
    await act(() => input.dispatchEvent(new Event('change', { bubbles: true })));
    expect(container.querySelector('img').src).toBe('blob:captura-prueba');
    await act(() => container.querySelector('[aria-label="Ver evidencia 1: pieza.png"]').click());
    expect(onVer).toHaveBeenCalledWith([expect.objectContaining({ url: 'blob:captura-prueba', nombre_original: 'pieza.png' })], 0);
});
it('conserva las capacidades de agregar y retirar por catálogo y permite corregir la selección', async () => {
    await act(() => root.render(<AjustePiezas pedidoId={1} pedido={{}} solicitud={{}} />));
    await click('SKU / nombre'); await click('Elegir perfume'); await click('Agregar');
    expect(container.textContent).toContain('Perfume del catálogo');
    await click('Quitar');
    expect(container.textContent).not.toContain('Perfume del catálogo');
    await click('Retirar piezas'); await click('SKU / nombre'); await click('Elegir perfume'); await click('Agregar');
    expect(container.textContent).toContain('Perfume del catálogo');
    expect(container.textContent).toContain('Solicitar retiro');
});
it('ofrece el faltante sin SKU desde la respuesta para retirarlo sin buscar inventario', async () => {
    await act(() => root.render(<AjustePiezas pedidoId={1} pedido={{ revisiones_producto: [{ id: 9, descripcion_producto: 'Crema agotada', cantidad_solicitada: 2, estado_fisico: 'sin_existencia' }] }} solicitud={{}} />));
    await click('Retirar piezas'); await click('SKU / nombre');
    expect(container.textContent).toContain('Crema agotada');
    const cantidad = container.querySelector('[aria-label="Cantidad a retirar de Crema agotada"]');
    expect(cantidad.value).toBe('2');
    await act(() => container.querySelector('input[type=checkbox]').click());
    expect(cantidad.disabled).toBe(false);
});
