// @vitest-environment happy-dom
import React, { act, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import BuscarUsuario from '../Pages/GestionInterna/ComisionesEmpaque/BuscarUsuario';
import { Preparar, Pagos, Acumulado } from '../Pages/GestionInterna/ComisionesEmpaque/Index';
import Equipo from '../Pages/GestionInterna/ComisionesEmpaque/Equipo';
import Periodos from '../Pages/GestionInterna/ComisionesEmpaque/Periodos';

const { guardar, navegar } = vi.hoisted(() => ({ guardar: vi.fn(), navegar: vi.fn() }));
vi.mock('../Layouts/AppLayout', () => ({ default: ({ children }) => children }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null, usePage: () => ({ props: {} }),
    router: { get: navegar },
    useForm: (inicial) => {
        const base = useRef(inicial);
        const transformar = useRef((data) => data);
        const [data, setData] = useState(inicial);
        return {
            data, errors: {}, processing: false, isDirty: JSON.stringify(base.current) !== JSON.stringify(data),
            setData: (campo, valor) => setData((prev) => typeof campo === 'string' ? { ...prev, [campo]: valor } : campo),
            transform: (fn) => { transformar.current = fn; },
            post: (url, opciones) => { guardar(url, transformar.current(data)); opciones?.onSuccess?.(); }, put: (url, opciones) => { guardar(url, transformar.current(data)); opciones?.onSuccess?.(); },
            reset: () => setData(base.current),
        };
    },
}));
let root, container;
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = (nombre, id = '') => `${nombre}/${id}`;
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
    guardar.mockClear(); navegar.mockClear();
});
afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});
const render = (elemento) => act(() => root.render(elemento));
const boton = (texto) => [...container.querySelectorAll('button')].find((b) => b.textContent.trim() === texto);
async function escribir(input, valor) {
    await act(() => {
        const setter = Object.getOwnPropertyDescriptor(input instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype, 'value').set;
        setter.call(input, valor);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
}
it('borra la cuenta anterior cuando se escribe otro nombre y permite elegir la nueva', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => [{ id: 2, name: 'Beatriz' }] }));
    const cambios = vi.fn();
    function Ejemplo() {
        const [id, setId] = useState(1);
        return <BuscarUsuario value={id} nombre={id === 1 ? 'Ana' : ''} onChange={(id) => { cambios(id); setId(id); }} />;
    }
    await render(<Ejemplo />);
    await escribir(container.querySelector('input'), 'Beatriz');
    expect(cambios).toHaveBeenCalledWith('');
    expect(container.querySelector('input').value).toBe('Beatriz');
    expect(container.querySelector('[role="status"]').textContent).not.toContain('Cuenta seleccionada');
    await act(() => boton('Beatriz').click());
    expect(cambios).toHaveBeenLastCalledWith(2);
    expect(container.querySelector('[role="status"]').textContent).toContain('Beatriz');
});
it('explica una búsqueda fallida sin conservar una selección previa', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('sin red')));
    const cambiar = vi.fn();
    await render(<BuscarUsuario value="" onChange={cambiar} />);
    await escribir(container.querySelector('input'), 'Ana');
    expect(container.querySelector('[role="status"]').textContent).toContain('No se pudo buscar');
    expect(cambiar).toHaveBeenCalledWith('', '');
});
it('crea el siguiente periodo heredando el reparto sin capturar porcentajes', async () => {
    const lineas = [{ participante_id: 1, user_id: 11, nombre: 'Ana', porcentaje: '60' }, { participante_id: 2, user_id: 12, nombre: 'Beatriz', porcentaje: '40' }];
    await render(<Periodos areaId={1} equipo={{ lineas }} puede={{ configurar: true, confirmar: true }} periodos={[{ id: 8, nombre: 'Octubre', area_id: 1, desde: '2026-10-01', hasta: '2026-10-13', cerrado: true, folio_corte: 'CE-8', lineas }]} />);
    await act(() => boton('Crear siguiente periodo').click());
    const form = container.querySelector('form');
    expect(form.querySelector('input[type="date"]').value).toBe('2026-10-14');
    expect(form.querySelector('input[type="number"]')).toBeNull();
    expect(form.textContent).toContain('Reparto permanente heredado');
    await escribir(form.querySelector('input'), 'Continuación de octubre');
    await escribir(form.querySelectorAll('input[type="date"]')[1], '2026-10-15');
    await act(() => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    const datos = guardar.mock.calls[0][1];
    expect(datos.desde).toBe('2026-10-14');
    expect(datos.hasta).toBe('2026-10-15');
    expect(datos).not.toHaveProperty('lineas');
});
it('guarda porcentajes permanentes distintos sobre un mismo fondo', async () => {
    await render(<Equipo areaId={1} puede={{ configurar: true }} acumulado="1000.00" equipo={{ area: 'CEDIS', lineas: [{ user_id: 1, nombre: 'Ana', porcentaje: '50' }, { user_id: 2, nombre: 'Beatriz', porcentaje: '30' }, { user_id: 3, nombre: 'Carlos', porcentaje: '10' }, { user_id: 4, nombre: 'Daniel', porcentaje: '10' }] }} />);
    expect(container.textContent).toContain('$500.00');
    expect(container.textContent).toContain('$300.00');
    await act(() => boton('Ajustar equipo y porcentajes').click());
    const form = container.querySelector('form');
    await escribir([...form.querySelectorAll('input')].find((e) => e.placeholder.startsWith('Por ejemplo')), 'Equipo habitual');
    await act(() => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    expect(guardar.mock.calls[0][1].lineas.map((l) => l.porcentaje)).toEqual(['50', '30', '10', '10']);
    expect(guardar.mock.calls[0][1]).not.toHaveProperty('desde');
});

it('confirma el corte con la petición transformada sin encadenar el envío', async () => {
    await render(<Preparar areas={[{ id: 1, nombre: 'CEDIS' }]} puede={{ confirmar: true }} filtros={{ area_id: '1', limite_desde: '2026-10-01', limite_hasta: '2026-10-13' }} preview={{ cajas: 100, importe: '1000.00', hash: 'calculo-revisado', unidades: [{ id: 1, cantidad: 100 }], tramos: [], tipos: [], errores: [] }} />);
    await act(() => boton('Confirmar corte').click());
    expect(guardar).toHaveBeenCalledWith('gestion_interna.comisiones_empaque.confirmar/', {
        area_id: '1', limite_desde: '2026-10-01', limite_hasta: '2026-10-13', incluir_tardias: false, hash: 'calculo-revisado',
    });
});
it('oculta la confirmación si cambian las fechas después de revisar el cálculo', async () => {
    await render(<Preparar areas={[{ id: 1, nombre: 'CEDIS' }]} puede={{ confirmar: true }} filtros={{ area_id: '1', limite_desde: '2026-10-01', limite_hasta: '2026-10-13' }} preview={{ cajas: 100, importe: '1000.00', hash: 'calculo-revisado', unidades: [{ id: 1, cantidad: 100 }], tramos: [], tipos: [], errores: [] }} />);
    await escribir(container.querySelectorAll('input[type="date"]')[1], '2026-10-15');
    expect(boton('Confirmar corte')).toBeUndefined();
    expect(guardar).not.toHaveBeenCalled();
});

it('limpia la selección y usa otra clave para un segundo pago legítimo', async () => {
    await render(<Pagos puede={{ pagar: true }} saldos={[{ id: 1, nombre: 'Ana', activo: true, pendiente: '250.00', reconocido: '250.00', pagado: '0.00', asignaciones: [{ id: 5, folio: 'CE-1', area: 'CEDIS', pendiente: '250.00' }] }]} />);
    for (let i = 0; i < 2; i++) {
        await act(() => container.querySelector('input[type="checkbox"]').click());
        await escribir(container.querySelector('input[type="date"]'), '2026-10-20');
        await act(() => container.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
        expect(container.querySelector('input[type="checkbox"]').checked).toBe(false);
    }
    expect(guardar).toHaveBeenCalledTimes(2);
    expect(guardar.mock.calls[0][1].clave_idempotencia).not.toBe(guardar.mock.calls[1][1].clave_idempotencia);
});

it('abre el reporte sin una captura manual permanente y permite revisar un empaque', async () => {
    await render(<Acumulado filtros={{ area_id: 1 }} resumen={{ total_registros: 1, por_revisar: 1 }} acumulado={{ data: [{ id: 1, cliente: 'Cliente pendiente', empacado_at: '2026-10-13', area: 'CEDIS', cantidad_origen: 4, situacion: 'pendiente_clasificacion', situacion_etiqueta: 'Pendiente de clasificación', lineas: [] }] }} puede={{ capturar: true, clasificar: true }} tipos={[]} situaciones={{}} />);
    expect(container.textContent).not.toContain('Fecha y hora reales del empaque');
    await act(() => boton('Revisar').click());
    expect(document.body.textContent).toContain('Revisión del empaque');
    expect(document.body.textContent).toContain('Cliente pendiente');
    expect(document.body.textContent).toContain('El desglose debe sumar 4 cajas');
    expect(document.body.textContent).toContain('Agregar tipo de caja');
    expect(document.body.textContent).not.toContain('Abrir pedido de origen');
    await act(() => container.querySelector('button[aria-label="Por revisar"]').click());
    expect(navegar.mock.calls[0][1]).toMatchObject({ area_id: 1, tab: 'acumulado', vista: 'pendientes' });
});
