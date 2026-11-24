// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it } from 'vitest';
import RespuestaDisponibilidadPedido from './RespuestaDisponibilidadPedido';

let root, container;
beforeEach(() => { globalThis.IS_REACT_ACT_ENVIRONMENT = true; container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
const render = props => act(() => root.render(<RespuestaDisponibilidadPedido estado="excepciones_pendientes" responsable="Paola" {...props} />));
const pieza = (extra = {}) => ({ id: 1, descripcion: 'Perfume', sku: 'P1', cantidad: 1, estado_fisico: 'bueno', documentos: [], ...extra });
const cambio = (extra = {}) => ({ clave: 'c1', descripcion: 'Anexo A', sku: 'A', cantidad: 1, documentos: [], tipo: 'anexo', tono: 'aviso', etiqueta: 'Por revisar', ...extra });
const tonos = () => [...container.querySelectorAll('.tienda-respuesta-fila')].map(f => f.getAttribute('data-tono'));

it('cada pieza toma su tono por estado y por decisión de Ventas', async () => {
    await render({ filas: [pieza(), pieza({ id: 2, estado_fisico: 'sin_existencia' }), pieza({ id: 3, estado_fisico: 'regular' }), pieza({ id: 4, estado_fisico: 'bueno', resolucion: { accion: 'retirar' } })] });
    expect(tonos()).toEqual(['exito', 'peligro', 'aviso', 'peligro']);
});
it('una fila de cambio pendiente es amarilla y muestra su estado en texto', async () => {
    await render({ filas: [pieza()], cambios: { actuales: [cambio()], anteriores: [] } });
    const fila = container.querySelector('[data-origen="cambio"]');
    expect(fila.getAttribute('data-tono')).toBe('aviso');
    expect(fila.textContent).toContain('Por revisar');
    expect(fila.textContent).toContain('Anexo A');
});
it('los movimientos anteriores se pliegan al pie con su cuenta', async () => {
    await render({ filas: [pieza()], cambios: { actuales: [], anteriores: [cambio({ clave: 'a' }), cambio({ clave: 'b' })] } });
    expect(container.querySelector('details.tienda-respuesta-movimientos summary').textContent).toBe('Movimientos (2)');
    expect(container.querySelectorAll('details.tienda-respuesta-movimientos [data-origen="cambio"]')).toHaveLength(2);
});
it('sin cambios no hay desplegable de movimientos', async () => {
    await render({ filas: [pieza()] });
    expect(container.querySelector('.tienda-respuesta-movimientos')).toBeNull();
});
it('con solo filas de cambio la tabla se pinta', async () => {
    await render({ filas: [], cambios: { actuales: [cambio()], anteriores: [] } });
    expect(container.querySelector('[role="table"]')).not.toBeNull();
});
it('muestra el aviso de reapertura', async () => {
    await render({ filas: [pieza()], avisoReapertura: 'Consulta reabierta por Ana.' });
    expect(container.textContent).toContain('Consulta reabierta por Ana.');
});
