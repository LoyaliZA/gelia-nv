// @vitest-environment happy-dom
import React, { act, useRef, useState } from 'react';
import axios from 'axios';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import BuscarUsuario from '../Pages/GestionInterna/ComisionesEmpaque/BuscarUsuario';
import { Index as ComisionesEmpaque } from '../Pages/GestionInterna/ComisionesEmpaque/Index';
import { Preparar, Pagos } from '../Pages/GestionInterna/ComisionesEmpaque/Liquidaciones';
import { Acumulado } from '../Pages/GestionInterna/ComisionesEmpaque/Empaques';
import Equipo from '../Pages/GestionInterna/ComisionesEmpaque/Equipo';
import Periodos from '../Pages/GestionInterna/ComisionesEmpaque/Periodos';
import { precargarComisiones } from '../Pages/GestionInterna/ComisionesEmpaque/compartido';

const { guardar, navegar, recargar, precargar, invalidar } = vi.hoisted(() => ({
    guardar: vi.fn(), navegar: vi.fn(), recargar: vi.fn(), precargar: vi.fn(), invalidar: vi.fn(),
}));
vi.mock('../Layouts/AppLayout', () => ({ default: ({ children }) => children }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null, usePage: () => ({ props: {} }),
    router: { get: navegar, reload: recargar, prefetch: precargar, flushByCacheTags: invalidar },
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
    guardar.mockClear(); navegar.mockClear(); recargar.mockClear(); precargar.mockClear(); invalidar.mockClear();
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

it('limita cada porcentaje al saldo del equipo y permite completarlo desde el botón', async () => {
    const lineas = [
        { user_id: 1, nombre: 'Ana', porcentaje: '45' },
        { user_id: 2, nombre: 'Beatriz', porcentaje: '40' },
        { user_id: 3, nombre: 'Carlos', porcentaje: '10' },
    ];
    await render(<Equipo areaId={1} equipo={{ area: 'Tienda', lineas }} puede={{ configurar: true }} />);
    await act(() => boton('Ajustar equipo y porcentajes').click());
    const campos = container.querySelectorAll('input[type="number"]');
    expect(campos[2].max).toBe('15.00');
    await escribir(campos[2], '60');
    expect(campos[2].value).toBe('15.00');
    await escribir(campos[0], '35');
    expect(campos[2].max).toBe('25.00');
    await act(() => container.querySelector('[aria-label="Usar máximo disponible para Carlos"]').click());
    expect(campos[2].value).toBe('25.00');
    expect(container.querySelector('[role="status"]').textContent).toContain('100.00% asignado');
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
    expect(document.body.textContent).toContain('El desglose debe sumar 4 piezas');
    expect(document.body.textContent).toContain('Agregar tipo de caja');
    expect(document.body.textContent).not.toContain('Abrir pedido de origen');
    await act(() => container.querySelector('button[aria-label="Por revisar"]').click());
    expect(navegar.mock.calls[0][1]).toMatchObject({ area_id: 1, tab: 'acumulado', vista: 'pendientes' });
});

it('conserva el área y el rango seleccionados al abrir Liquidaciones o Productividad', async () => {
    await render(<ComisionesEmpaque tab="acumulado" puede={{ configurar: true }}
        areas={[{ id: 1, nombre: 'CEDIS' }, { id: 2, nombre: 'Tienda' }]}
        filtros={{ area_id: 2, periodo_id: 4, desde: '2026-11-01', hasta: '2026-11-15' }}
        periodos={[{ id: 4, area_id: 2, nombre: 'Primera quincena', desde: '2026-11-01', hasta: '2026-11-15' }]} />);
    const contexto = { area_id: 2, periodo_id: 4, desde: '2026-11-01', hasta: '2026-11-15' };
    await act(() => boton('Liquidaciones').click());
    expect(navegar.mock.calls[0][1]).toMatchObject({ ...contexto, tab: 'periodos' });
    await act(() => boton('Productividad').click());
    expect(navegar.mock.calls[1][1]).toMatchObject({ ...contexto, tab: 'productividad' });
    for (const visita of navegar.mock.calls) {
        expect(visita[2]).toMatchObject({ showProgress: false, preserveState: true, preserveScroll: true });
    }
});


it('difiere datos nuevos mientras hay una captura y refresca solo el reporte al cerrarla', async () => {
    vi.useFakeTimers();
    try {
        vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
        vi.spyOn(axios, 'get').mockResolvedValue({ data: { estado: 'ok', revision: 'nueva' } });
        await render(<ComisionesEmpaque tab="acumulado" puede={{ capturar: true }}
            areas={[{ id: 1, nombre: 'CEDIS' }]} filtros={{ area_id: 1, vista: 'pendientes', buscar: 'REC' }}
            sincronizacion={{ estado: 'ok', revision: 'anterior' }} />);
        await act(() => boton('Registrar empaque').click());
        const cliente = [...container.querySelectorAll('form label')].find((etiqueta) => etiqueta.textContent.trim() === 'Cliente' && etiqueta.querySelector('input[required]')).querySelector('input');
        await escribir(cliente, 'Captura en curso');
        await act(async () => vi.advanceTimersByTimeAsync(25000));
        expect(container.textContent).toContain('Hay datos nuevos');
        expect(cliente.value).toBe('Captura en curso');
        expect(recargar).not.toHaveBeenCalled();
        expect(axios.get).toHaveBeenCalledWith(expect.stringContaining('sincronizacion.estado'), expect.objectContaining({ params: { area_id: 1 } }));

        await act(() => boton('Ocultar captura').click());
        await act(async () => vi.advanceTimersByTimeAsync(25000));
        expect(recargar).toHaveBeenCalledWith(expect.objectContaining({
            only: ['acumulado', 'totales', 'resumen', 'sincronizacion'], preserveState: true, preserveScroll: true, showProgress: false,
        }));
        expect(container.querySelector('[data-busqueda-live]').value).toBe('REC');
        expect(container.querySelector('[aria-label="Por revisar"]').getAttribute('aria-pressed')).toBe('true');
    } finally {
        vi.useRealTimers();
    }
});

it('aplica Más filtros y recibe nuevos empaques sin desmontar la superficie', async () => {
    vi.useFakeTimers();
    try {
        vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
        vi.spyOn(axios, 'get').mockResolvedValue({ data: { estado: 'ok', revision: 'r2' } });
        const inicial = {
            tab: 'acumulado', puede: { configurar: true }, areas: [{ id: 1, nombre: 'CEDIS' }],
            filtros: { area_id: 1 }, sincronizacion: { estado: 'ok', revision: 'r1' },
        };
        await render(<ComisionesEmpaque {...inicial} />);
        const titulo = container.querySelector('h1');
        const panel = container.querySelector('.theme-card--solid');
        const form = container.querySelector('form');
        const detalles = form.querySelector('details');
        await act(() => { detalles.open = true; });
        await escribir(detalles.querySelector('input'), 'Cliente nuevo');
        await act(() => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
        expect(navegar.mock.lastCall[1]).toMatchObject({ tab: 'acumulado', cliente: 'Cliente nuevo', area_id: 1 });
        expect(navegar.mock.lastCall[2]).toMatchObject({ showProgress: false, preserveState: true, preserveScroll: true });
        const payload = { ...inicial, filtros: { area_id: 1, cliente: 'Cliente nuevo' } };
        await render(<ComisionesEmpaque {...payload} />);
        await act(() => navegar.mock.lastCall[2].onSuccess({ props: payload }));
        expect(detalles.open).toBe(false);
        await act(async () => vi.advanceTimersByTimeAsync(25000));
        expect(recargar).toHaveBeenCalledWith(expect.objectContaining({ showProgress: false, only: ['acumulado', 'totales', 'resumen', 'sincronizacion'] }));
        await render(<ComisionesEmpaque {...payload} sincronizacion={{ estado: 'ok', revision: 'r2' }}
            acumulado={{ data: [{ id: 1, cliente: 'Cliente nuevo', situacion: 'elegible', lineas: [] }] }} />);
        expect(container.textContent).toContain('Cliente nuevo');
        expect(container.querySelector('h1')).toBe(titulo);
        expect(container.querySelector('.theme-card--solid')).toBe(panel);
    } finally {
        vi.useRealTimers();
    }
});

it('ignora una precarga en curso al navegar a otra sección', async () => {
    vi.useFakeTimers();
    try {
        vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
        await render(<ComisionesEmpaque tab="acumulado" puede={{ configurar: true }}
            areas={[{ id: 1, nombre: 'CEDIS' }]} filtros={{ area_id: 1 }}
            sincronizacion={{ estado: 'ok', revision: 'r1', contexto: 'usuario-cedis' }} />);
        await act(async () => vi.advanceTimersByTimeAsync(700));
        expect(precargar).toHaveBeenCalledTimes(2);
        const periodos = precargar.mock.calls.find(([, opciones]) => opciones.data.tab === 'periodos')[1];
        expect(periodos).toMatchObject({ showProgress: false, data: { area_id: 1, tab: 'periodos' } });
        await act(() => boton('Liquidaciones').click());
        expect(navegar).not.toHaveBeenCalled();
        await act(() => boton('Configuración').click());
        expect(navegar.mock.lastCall[1].tab).toBe('configuracion');
        await act(() => {
            periodos.onPrefetched({ status: 200 });
            precargar.mock.calls.find(([, opciones]) => opciones.data.tab === 'productividad')[1].onPrefetched({ status: 200 });
        });
        expect(navegar).toHaveBeenCalledTimes(1);
    } finally {
        vi.useRealTimers();
    }
});

it('reanuda la precarga bajo demanda después de una espera agotada', () => {
    vi.useFakeTimers();
    try {
        const parametros = { tab: 'productividad', area_id: 1 };
        precargarComisiones(parametros, 'revision-prueba');
        expect(precargar).toHaveBeenCalledTimes(1);
        vi.advanceTimersByTime(5000);
        precargarComisiones(parametros, 'revision-prueba');
        expect(precargar).toHaveBeenCalledTimes(1);
        vi.advanceTimersByTime(30_000);
        expect(precargar).toHaveBeenCalledTimes(1);
        precargarComisiones(parametros, 'revision-prueba');
        expect(precargar).toHaveBeenCalledTimes(2);
        precargar.mock.lastCall[1].onPrefetched({ status: 200 });
    } finally {
        vi.useRealTimers();
    }
});

it('conserva el cálculo revisado hasta solicitar otra vista previa', async () => {
    const filtros = { area_id: '1', limite_desde: '2026-10-01', limite_hasta: '2026-10-13' };
    const previo = { cajas: 1, importe: '10.00', hash: 'revisado', unidades: [{ id: 1 }], tramos: [], tipos: [], errores: [] };
    const nuevo = { ...previo, cajas: 2, importe: '20.00', hash: 'nuevo' };
    const props = { areas: [{ id: 1, nombre: 'CEDIS' }], filtros, puede: { confirmar: true } };
    await render(<Preparar {...props} preview={previo} />);
    await render(<Preparar {...props} preview={nuevo} />);
    expect(container.textContent).toContain('$10.00');
    const form = container.querySelector('form');
    await act(() => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    await act(() => navegar.mock.lastCall[2].onSuccess({ props: { preview: nuevo } }));
    expect(container.textContent).toContain('$20.00');
});
