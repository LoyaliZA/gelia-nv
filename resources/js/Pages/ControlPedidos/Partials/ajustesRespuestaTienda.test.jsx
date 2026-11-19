// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import RespuestaPreparacionTienda from './RespuestaPreparacionTienda';
import SeguimientoSolicitudTienda, { AjustePiezas } from './SeguimientoSolicitudTienda';
vi.mock('../../../Components/Almacenes/SelectorProducto', () => ({ default: ({ onChange }) => <button onClick={() => onChange(1, { id: 1, sku: 'SKU-1', descripcion: 'Perfume del catálogo' })}>Elegir perfume</button> }));
vi.mock('./ModalVistaPreviaDocumento', () => ({
    default: () => null,
    MiniaturaDocumento: ({ documento }) => <img alt="" src={documento.url} />,
    esPdf: () => false,
}));
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
    expect(container.textContent).toContain('Con detalles o faltantes');
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
it('conserva la nota al plegar modificar pedido', async () => {
    const pedido = { id: 1, solicitud_preparacion: { revision_disponibilidad: { estado: 'sin_excepciones', aceptacion_vigente: true } } };
    await act(() => root.render(<SeguimientoSolicitudTienda pedidoId={1} pedido={pedido} area="preparacion_tienda" />));
    await click('Modificar pedido');
    const nota = container.querySelector('#ajuste-nota');
    await act(() => {
        const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
        setter.call(nota, 'Aroma distinto');
        nota.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await click('Modificar pedido');
    expect(container.querySelector('#ajuste-nota').value).toBe('Aroma distinto');
    expect(container.querySelector('[aria-label="Ajuste de piezas"]')).toBeTruthy();
});
it('muestra la captura vigente una sola vez al final del seguimiento de Ventas', async () => {
    const documentos = [{ id: 9, tipo: 'evidencia_condicion', relacion_tipo: 'revision_general', url: '/conjunto.jpg', mime_type: 'image/jpeg', activo: true }];
    const pedido = {
        id: 1, documentos,
        solicitud_preparacion: { revision_disponibilidad: { estado: 'sin_excepciones', aceptacion_vigente: true, evidencia_actual_ids: [9] } },
    };
    await act(() => root.render(<SeguimientoSolicitudTienda pedidoId={1} pedido={pedido} area="control_pedidos" />));
    expect(container.querySelectorAll('[aria-label="Captura del pedido"]')).toHaveLength(1);
    expect(container.querySelector('[aria-label="Resultado de la consulta en CEDIS"]')?.querySelector('[aria-label="Captura del pedido"]')).toBeNull();
});
it('dos anexos consecutivos informan la versión de consulta de cada respuesta para descartar lecturas anteriores', async () => {
    const onSolicitud = vi.fn();
    let version = 10;
    const adaptador = { capacidades: { anexar: true, retirar: false }, comandos: { anexar: vi.fn(async () => ({ data: { solicitud_preparacion: { anexo_sin_remision: [{}] }, version_consulta: ++version } })) } };
    await act(() => root.render(<AjustePiezas pedidoId={1} pedido={{}} solicitud={{}} adaptador={adaptador} onSolicitud={onSolicitud} onAviso={vi.fn()} />));
    for (let i = 0; i < 2; i++) {
        const input = container.querySelector('input[type="file"][multiple]');
        Object.defineProperty(input, 'files', { configurable: true, value: [new File(['x'], `anexo-${i}.png`, { type: 'image/png' })] });
        await act(() => input.dispatchEvent(new Event('change', { bubbles: true })));
        await click('Solicitar agregado');
    }
    expect(onSolicitud.mock.calls.map((c) => c[1])).toEqual([11, 12]);
});
it('una pieza ya solicitada para retiro por SKU deja de ofrecerse en la edición', async () => {
    const pedido = { tarea_preparacion: { respuesta: { productos: [{ id: 1, sku: 'SKU-1', descripcion: 'Perfume A', cantidad: 1 }, { id: 2, sku: 'SKU-2', descripcion: 'Perfume B', cantidad: 3 }] } } };
    const solicitud = { retiro_piezas: [{ estado: 'pendiente', piezas_solicitadas: [{ sku: 'SKU-1', descripcion: 'Perfume A', cantidad: 1 }, { sku: 'SKU-2', descripcion: 'Perfume B', cantidad: 1 }] }] };
    await act(() => root.render(<AjustePiezas pedidoId={1} pedido={pedido} solicitud={solicitud} />));
    await click('Retirar piezas');
    expect(container.textContent).not.toContain('Perfume A');
    expect(container.textContent).toContain('Perfume B');
    expect(container.querySelector('[aria-label="Cantidad a retirar de Perfume B"]').value).toBe('2');
});
