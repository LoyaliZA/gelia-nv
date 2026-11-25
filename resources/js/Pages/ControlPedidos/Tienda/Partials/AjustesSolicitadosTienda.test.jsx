// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, it, expect, vi } from 'vitest';
import AjustesSolicitadosTienda from './AjustesSolicitadosTienda';
import RespuestaPreparacionTienda from '../../Partials/RespuestaPreparacionTienda';
const post = vi.hoisted(() => vi.fn());
vi.mock('@inertiajs/react', () => ({ router: { post } }));
vi.mock('../../../../Components/Almacenes/SelectorProducto', () => ({ default: () => <span>Buscar producto</span> }));
let root, container;
const tarea = { id: 4, estado: 'RESPONDIDA', puede_preparar: true, pedido: { retiros: [{ version: 2, estado: 'pendiente', motivo: 'Petición del cliente', piezas: [{ sku: 'A', descripcion: 'Aroma', cantidad: 1 }] }], anexos_pendientes: [] } };
const render = (value, props = {}) => act(() => root.render(<AjustesSolicitadosTienda tarea={value} auth={{ user: { id: 2 } }} {...props} />));
beforeEach(() => { globalThis.IS_REACT_ACT_ENVIRONMENT = true; globalThis.route = (name, id) => `${name}/${id}`; post.mockReset(); container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
it('permite atender el retiro solicitado después de responder el apartado', async () => {
    await render(tarea);
    const aplicar = [...container.querySelectorAll('button')].find((b) => b.textContent === 'Aplicar retiro');
    await act(() => aplicar.click());
    expect(post).toHaveBeenCalledWith('control_pedidos.tienda.retiro.atender/4', expect.objectContaining({ version: 2, decision: 'aplicar' }), expect.any(Object));
});
it('sin ajustes no ofrece acciones y nunca abre una nueva respuesta general', async () => {
    await render({ ...tarea, pedido: { retiros: [], anexos_pendientes: [] } });
    expect(container.querySelector('button')).toBeNull();
});
it('usa el número visible de la misma revisión en los retiros y confirmaciones guardados', async () => {
    const confirmada = { revision_id: 82, descripcion: 'Pieza con detalle 4', cantidad: 1 };
    const retirada = { revision_id: 83, descripcion: 'Pieza con detalle 5', cantidad: 1 };
    const fotoOriginal = { id: 91, nombre_original: 'original.png', url: '/original.png' };
    const fotoReemplazo = { id: 92, nombre_original: 'reemplazo.png', url: '/reemplazo.png' };
    const revision = { estado: 'excepciones_pendientes', respondida_at: '2026-11-25T15:00:00Z' };
    const excepciones = [
        { id: 81, descripcion: 'Pieza con detalle 3', estado_fisico: 'regular' },
        { id: 90, descripcion: 'Producto agotado', estado_fisico: 'sin_existencia' },
        { id: 82, descripcion: confirmada.descripcion, estado_fisico: 'regular' },
        { id: 83, descripcion: retirada.descripcion, estado_fisico: 'regular' },
        { id: 84, descripcion: 'Pieza con detalle 6', estado_fisico: 'regular', cantidad: 1, documentos: [fotoOriginal] },
    ];
    const consultada = { ...tarea, respuesta: { revision, excepciones }, pedido: {
        area_revision: 'preparacion_tienda', revision_disponibilidad: revision,
        acciones_disponibilidad: { 82: { ...confirmada, accion: 'confirmar', fecha: '2026-11-25T14:00:00Z' },
            84: { revision_id: 84, accion: 'sustituir', descripcion: 'Pieza con detalle 6', cantidad: 1 } },
        retiro_piezas: [{ solicitado_at: '2026-11-25T16:00:00Z', piezas_solicitadas: [retirada] }],
        anexo_sin_remision: [{ sustituye_revision_id: 84, descripcion: 'Alternativa en documento', documentos: [fotoReemplazo],
            respondida_at: '2026-11-25T15:00:00Z', solicitado_at: '2026-11-25T14:00:00Z', pendiente_revision: false }],
    } };

    const onVer = vi.fn();
    await act(() => root.render(<><RespuestaPreparacionTienda tarea={consultada} /><AjustesSolicitadosTienda tarea={consultada} onVer={onVer} /></>));

    const filas = [...container.querySelectorAll('[role="row"] .tienda-respuesta-producto strong')];
    expect(filas.map(fila => fila.textContent)).toEqual([
        'Pieza con detalle 1', 'Producto agotado', 'Pieza con detalle 2', 'Pieza con detalle 3', 'Pieza con detalle 4',
    ]);
    expect(container.querySelector('[data-tipo="retiro"] .consulta-cambio-identidad').textContent).toBe('Pieza con detalle 3 · 1 pza(s)');
    expect(container.querySelector('details [data-tipo="confirmacion"] .consulta-cambio-identidad').textContent).toBe('Pieza con detalle 2 · 1 pza(s)');
    const sustitucion = container.querySelector('details [data-sustitucion]');
    expect(sustitucion.textContent).toContain('Pieza a sustituirPieza con detalle 4 · 1 pza(s)');
    expect(sustitucion.textContent).toContain('Reemplazo solicitadoAlternativa en documento');
    await act(() => sustitucion.querySelector('[aria-label="Ver original.png"]').click());
    expect(onVer).toHaveBeenLastCalledWith([fotoOriginal], 0);
    await act(() => sustitucion.querySelector('[aria-label="Ver reemplazo.png"]').click());
    expect(onVer).toHaveBeenLastCalledWith([fotoReemplazo], 0);
});
it('una tarea sustituida o un auxiliar sin facultad no puede responder ajustes', async () => {
    await render({ ...tarea, estado: 'SUSTITUIDA' });
    expect(container.querySelector('button')).toBeNull();
    await render({ ...tarea, puede_preparar: false, puede_responder: false });
    expect(container.querySelector('button')).toBeNull();
});
it('el anexo pendiente tiene respuesta propia y valida el faltante antes de enviar', async () => {
    await render({ ...tarea, pedido: { anexos_pendientes: [{ indice: 0, entrada: 'documento' }] } });
    const responder = [...container.querySelectorAll('button')].find((b) => b.textContent === 'Reportar faltante o detalle');
    await act(() => responder.click());
    expect(post).not.toHaveBeenCalled();
    expect(container.textContent).toContain('Indique el faltante');
    await act(() => {
        const nota = container.querySelector('textarea');
        Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set.call(nota, 'No disponible');
        nota.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await act(() => responder.click());
    expect(post.mock.calls[0][0]).toBe('control_pedidos.tienda.anexo.atender/4');
    expect(post.mock.calls[0][1].get('nota')).toBe('No disponible');
});

it('avisa de una captura pendiente y deja de bloquear la actualización cuando se resuelve el ajuste', async () => {
    const onDirtyChange = vi.fn();
    const pendiente = { ...tarea, pedido: { anexos_pendientes: [{ indice: 0, entrada: 'documento' }] } };
    await render(pendiente, { onDirtyChange });
    expect(onDirtyChange).toHaveBeenLastCalledWith(false);
    await act(() => {
        const nota = container.querySelector('textarea');
        Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set.call(nota, 'No disponible');
        nota.dispatchEvent(new Event('input', { bubbles: true }));
    });
    expect(onDirtyChange).toHaveBeenLastCalledWith(true);
    await render({ ...tarea, pedido: { anexos_pendientes: [], retiros: [] } }, { onDirtyChange });
    expect(onDirtyChange).toHaveBeenLastCalledWith(false);
});
