// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/react';
import FiltrosSolicitudes from '@/Components/Filtros/FiltrosSolicitudes';
import useFiltrosSolicitudesPage, { calcularRangoFechas } from '@/hooks/useFiltrosSolicitudesPage';
import { cotizacionHistorial } from '@/Pages/Solicitudes/Partials/solicitudHistorial';
import ModalBitacoraSolicitud from '@/Pages/Solicitudes/Partials/ModalBitacoraSolicitud';

let container, root;
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement('div'); document.body.append(container); root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount()); container.remove(); vi.restoreAllMocks(); vi.useRealTimers(); });
const cambiar = async (id, value) => act(() => {
    const input = document.getElementById(id);
    if (input.tagName === 'INPUT') Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, value);
    else input.value = value;
    input.dispatchEvent(new Event(input.tagName === 'INPUT' ? 'input' : 'change', { bubbles: true }));
});
const aplicar = async () => act(() => [...container.querySelectorAll('button')].find(button => button.textContent === 'Aplicar filtros').click());

it('combina lista, tipo, TAG y mes completo y permite quitar una sola faceta', async () => {
    const apply = vi.fn();
    await act(() => root.render(<FiltrosSolicitudes variante="tag-lista" tabActiva="VIGENTES" busqueda="Cliente" tipoFecha="TODAS" fechaInicio="" fechaFin="" filtroVendedor="7" filtroMotivo="" filtroLista="2" filtrosActivos={1} listas={[{ id: 2, nombre: 'Plata' }]} tiposCliente={[{ id: 3, nombre: 'Distribuidor' }]} onAplicarFiltros={apply} onCambiarTab={vi.fn()} />));
    await cambiar('filtro-fecha-cliente', '3'); await cambiar('filtro-fecha-tag', 'con_tag');
    await cambiar('filtro-fecha-tipo', 'MES_ESPECIFICO'); await cambiar('filtro-fecha-mes', '2024-02'); await aplicar();
    expect(apply).toHaveBeenLastCalledWith(expect.objectContaining({ lista_id: '2', tipo_cliente_id: '3', tag: 'con_tag', fecha_inicio: '2024-02-01', fecha_fin: '2024-02-29', vendedor_id: '7', q: 'Cliente' }));
    await act(() => [...container.querySelectorAll('button')].find(button => button.textContent.includes('Quitar Lista: Plata')).click());
    expect(apply).toHaveBeenLastCalledWith({ lista_id: '' });
});

it('muestra un error y conserva los filtros cuando el rango está invertido', async () => {
    const apply = vi.fn();
    await act(() => root.render(<FiltrosSolicitudes variante="tag-lista" tabActiva="TODAS" busqueda="" tipoFecha="PERSONALIZADO" fechaInicio="2026-10-20" fechaFin="2026-10-01" filtroVendedor="" filtroMotivo="" onAplicarFiltros={apply} onCambiarTab={vi.fn()} />));
    await aplicar();
    expect(apply).not.toHaveBeenCalled();
    expect(container.querySelector('[role="alert"]').textContent).toContain('fecha inicial');
});

it('mantiene visibles las restricciones del listado al abrir el reporte con su estilo habitual', async () => {
    const apply = vi.fn();
    await act(() => root.render(<FiltrosSolicitudes mostrarFiltrosClientes tabActiva="VIGENTES" busqueda="Cliente" tipoFecha="MES_ESPECIFICO" fechaInicio="2026-09-01" fechaFin="2026-09-30" filtroVendedor="" filtroMotivo="" filtroLista="2" filtroTipoCliente="3" filtroTag="con_tag" filtrosActivos={4} listas={[{ id: 2, nombre: 'Plata' }]} tiposCliente={[{ id: 3, nombre: 'Distribuidor' }]} onAplicarFiltros={apply} onCambiarTab={vi.fn()} />));
    expect(container.querySelector('.gelia-tag-filtros')).toBeNull();
    expect(container.textContent).toContain('Con TAG actual');
    expect(container.textContent).toContain('Tipo: Distribuidor');
    expect(container.querySelector('#filtro-fecha-tipo').value).toBe('MES_ESPECIFICO');
    expect([...container.querySelectorAll('button')].find(button => button.textContent.trim() === 'Vigentes').getAttribute('aria-pressed')).toBe('true');
    await act(() => [...container.querySelectorAll('button')].find(button => button.textContent.trim() === 'Buscar').click());
    expect(apply).toHaveBeenLastCalledWith(expect.objectContaining({ lista_id: '2', tipo_cliente_id: '3', tag: 'con_tag', fecha_inicio: '2026-09-01', fecha_fin: '2026-09-30' }));
});

it('preserva las facetas al cambiar estado, paginar y exportar, y limpia todas explícitamente', async () => {
    let hook;
    const get = vi.spyOn(router, 'get').mockImplementation(() => {});
    function Harness() { hook = useFiltrosSolicitudesPage({ filtros: { lista_id: '2', tipo_cliente_id: '3', tag: 'con_tag', q: 'Cliente', tipo_fecha: 'MES_ESPECIFICO', fecha_inicio: '2026-09-01', fecha_fin: '2026-09-30' }, rutaIndex: '/solicitudes' }); return null; }
    await act(() => root.render(<Harness />));
    expect(hook.filtrosAdicionalesActivos).toBe(4);
    expect(hook.construirParams({ page: 2 })).toEqual(expect.objectContaining({ lista_id: '2', tipo_cliente_id: '3', tag: 'con_tag', page: 2 }));
    expect(hook.exportParams).toEqual(expect.objectContaining({ lista_id: '2', tipo_cliente_id: '3', tag: 'con_tag', fecha_fin: '2026-09-30' }));
    await act(() => hook.aplicarFiltros({ tab: 'VIGENTES' }));
    expect(get).toHaveBeenLastCalledWith('/solicitudes', expect.objectContaining({ tab: 'VIGENTES', lista_id: '2', tipo_cliente_id: '3', tag: 'con_tag' }), expect.any(Object));
    await act(() => hook.limpiarFiltrosAdicionales());
    expect(get).toHaveBeenLastCalledWith('/solicitudes', { tab: 'VIGENTES', q: 'Cliente' }, expect.any(Object));
});

it('calcula la semana de lunes a domingo incluso en domingo y resuelve el mes anterior', () => {
    vi.useFakeTimers(); vi.setSystemTime(new Date(2026, 9, 11, 20));
    expect(calcularRangoFechas('SEMANA')).toEqual({ inicioCalculado: '2026-10-05', finCalculado: '2026-10-11' });
    expect(calcularRangoFechas('MES_ANTERIOR')).toEqual({ inicioCalculado: '2026-09-01', finCalculado: '2026-09-30' });
});

it('recupera la propuesta histórica de lista y TAG sin inventar el tipo omitido en registros antiguos', () => {
    const snapshot = { antes: { monto_venta: 1000, lista_nombre: 'Bronce', tipo_cliente_nombre: 'Normal', tag_vendedor_nombre: null }, monto_cotizado: 6000, lista_descuento_id: 2, proceso_id: 1 };
    const cotizado = cotizacionHistorial(snapshot, { usuario: { name: 'Ventas' } }, { listas: [{ id: 2, nombre: 'Plata' }], procesos: [{ id: 1, nombre: 'ASIGNAR TAG Y LISTA' }] });
    expect(cotizado).toEqual(expect.objectContaining({ monto_venta: 7000, lista_nombre: 'Plata', tag_vendedor_nombre: 'Ventas', tipo_cliente_nombre: undefined }));
    expect(snapshot.antes.lista_nombre).toBe('Bronce');
});

it('muestra una transición accesible hacia la propuesta congelada aunque la solicitud se haya modificado', async () => {
    const solicitud = { id: 1, monto_cotizado: 10, tipo_cliente: { nombre: 'Otro tipo actual' }, auditorias: [{ id: 1, estado_nuevo: { nombre: 'Pendiente' }, motivo_reporte: 'Creación', datos_snapshot: { antes: { lista_nombre: 'Bronce', tipo_cliente_nombre: 'Normal' }, cotizado: { lista_nombre: 'Plata', tipo_cliente_nombre: 'Distribuidor' } } }] };
    await act(() => root.render(<ModalBitacoraSolicitud solicitud={solicitud} onClose={vi.fn()} />));
    const table = document.querySelector('table');
    expect(table.textContent).toContain('Cotizado / solicitado');
    expect(table.textContent).toContain('Plata'); expect(table.textContent).toContain('Distribuidor');
    expect(table.textContent).not.toContain('Otro tipo actual');
    expect(table.querySelectorAll('[data-changed="true"]')).toHaveLength(2);
    expect(table.textContent).toContain('Pasa a');
});
