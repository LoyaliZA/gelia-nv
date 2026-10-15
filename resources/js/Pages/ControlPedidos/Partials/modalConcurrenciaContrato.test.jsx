// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ModalFormPedido from './ModalFormPedido';
import axios from 'axios';
import { router } from '@inertiajs/react';
const { postFormulario } = vi.hoisted(() => ({ postFormulario: vi.fn() }));

vi.mock('axios', () => ({
    default: {
        get: vi.fn(() => Promise.resolve({ data: { direcciones: [] } })),
        post: vi.fn(() => Promise.resolve({ data: {} })),
    },
}));

vi.mock('@inertiajs/react', async () => {
    const ReactMod = await import('react');
    return {
        Head: () => null,
        usePage: () => ({
            props: {
                auth: {
                    user: {
                        id: 7,
                        permissions: ['control_pedidos.crear', 'control_pedidos.preparacion.solicitar'],
                        roles: [],
                    },
                },
                flash: {},
            },
        }),
        router: { post: vi.fn(), visit: vi.fn() },
        useForm: (initial) => {
            const [data, setDataState] = ReactMod.useState(initial);
            const setData = (key, value) => {
                if (typeof key === 'function') {
                    setDataState(key);
                    return;
                }
                if (typeof key === 'object' && key !== null && value === undefined) {
                    setDataState(key);
                    return;
                }
                setDataState((prev) => ({ ...prev, [key]: value }));
            };
            return {
                data,
                setData,
                post: postFormulario,
                processing: false,
                reset: vi.fn(),
                errors: {},
                transform: vi.fn(),
            };
        },
    };
});

const catalogos = {
    preparacion_config: {
        activo: true,
        modalidades_habilitadas: ['ENVIO_MUNICIPIO', 'ENVIO_BODEGA_NORMAL'],
        modalidades_catalogo: [
            { codigo: 'ENVIO_MUNICIPIO', nombre: 'Envío municipal' },
            { codigo: 'ENVIO_BODEGA_NORMAL', nombre: 'Envío bodega' },
        ],
        almacenes_habilitados: [9],
    },
    formulario_config: { autosave_debounce_ms: 50 },
    almacenes: [{ id: 9, nombre: 'CEDIS Centro', codigo: 'CEDIS', tipo: 'cedis', permite_salida_pedidos: true }],
    sucursales: [{ id: 3, nombre: 'Tienda Norte', codigo: 'TN' }],
    paqueterias_municipio: [{
        id: 8,
        nombre: 'Ruta municipal',
        permite_por_cobrar: true,
        campos_destino_obligatorios: ['municipio', 'destinatario', 'telefono', 'direccion'],
    }],
    tipos_operacion_envio: [],
};

const pedidoServidor = {
    cliente_id: 123,
    cliente: { id: 123, nombre: 'Cliente Q3' },
    folio: 'Q3-41',
    revision_registro: 0,
    base_captura: 'base-inicial',
    estatus: { fase_ciclo: 'BORRADOR' },
    documentos: [{ id: 1, tipo: 'pdf_pedido', activo: true }],
    id: 41,
    origen_id: 1,
    origen: { id: 1, requiere_logistica: true, nombre: 'CEDIS' },
    almacen_id: 9,
    sucursal_destino_id: 3,
    prioridad_md: true,
    comentarios_drive: 'Dejar en mostrador',
    consulta_cerrada: true,
    total_mercancia: 100,
    costo_envio: 40,
    saldo_a_favor: 0,
    solicitud_preparacion: {
        version: 1,
        codigo_modalidad: 'ENVIO_MUNICIPIO',
        tipo_referencia: 'COTIZACION',
        folio_referencia: 'COT-014',
        entrega_municipal: {
            destinatario_es_cliente: false,
            destinatario_nombre: 'Receptor alterno',
            destinatario_telefono: '2221112233',
            municipio_destino: 'San Pedro',
            direccion_referencia: 'Calle 4 esquina',
            catalogo_paqueteria_id: 8,
            modalidad_cobro: 'POR_COBRAR',
        },
    },
};

let root;
let container;

const controlTrasEtiqueta = (texto) => {
    const label = Array.from(document.querySelectorAll('label')).find((nodo) => nodo.textContent.includes(texto));
    if (!label) return null;
    return label.querySelector('input, select, textarea')
        || label.parentElement?.querySelector('input, select, textarea')
        || null;
};

const montar = async (props) => {
    await act(async () => {
        root.render(<ModalFormPedido abierto onClose={() => {}} catalogos={catalogos} {...props} />);
    });
};

beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    vi.stubGlobal('route', (name, params) => `/${name}${params ? `/${params}` : ''}`);
    localStorage.clear();
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    localStorage.clear();
    vi.useRealTimers();
    vi.unstubAllGlobals();
});


const diferida = () => { let resolver, rechazar; const promesa = new Promise((r, e) => { resolver = r; rechazar = e; }); return { promesa, resolver, rechazar }; };
const tick = async () => act(async () => { await vi.advanceTimersByTimeAsync(60); });
const editarFolio = async (valor) => act(() => {
    const input = document.querySelector('input[placeholder="Folio"]');
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, valor);
    input.dispatchEvent(new Event('input', { bubbles: true }));
});
const respuesta = (revision) => ({ data: { id: 41, folio: 'Q3-41', revision_registro: revision, base_captura: `base-${revision}` } });
const guardarManual = async () => act(() => {
    const boton = Array.from(document.querySelectorAll('button')).find((b) => b.textContent.includes('Guardar borrador'));
    expect(boton).toBeTruthy();
    boton.click();
});

describe('Q3 modal montado: coordinación y confirmaciones', () => {
    beforeEach(() => { vi.useFakeTimers(); axios.post.mockReset(); postFormulario.mockClear(); });
    it('serializa cambios durante una petición y solo confirma el snapshot enviado', async () => {
        const primera = diferida();
        axios.post.mockReturnValueOnce(primera.promesa).mockResolvedValue(respuesta(2));
        await montar({ pedido: pedidoServidor });
        await editarFolio('COT-PRIMERA'); await tick();
        expect(axios.post).toHaveBeenCalledTimes(1);
        await editarFolio('COT-SEGUNDA'); await tick();
        expect(axios.post).toHaveBeenCalledTimes(1);
        await act(async () => primera.resolver(respuesta(1)));
        expect(document.querySelector('input[placeholder="Folio"]').value).toBe('COT-SEGUNDA');
        expect(document.body.textContent).toContain('cambios pendientes');
        await tick();
        expect(axios.post).toHaveBeenCalledTimes(2);
        expect(axios.post.mock.lastCall[1].revision_esperada).toBe(1);
        expect(axios.post.mock.lastCall[1].solicitud_preparacion.folio_referencia).toBe('COT-SEGUNDA');
    });
    it('el guardado manual espera el automático y envía su revisión confirmada', async () => {
        const primera = diferida();
        axios.post.mockReturnValueOnce(primera.promesa).mockResolvedValue(respuesta(2));
        await montar({ pedido: pedidoServidor }); await tick();
        await guardarManual();
        expect(postFormulario).not.toHaveBeenCalled();
        expect(axios.post).toHaveBeenCalledTimes(1);
        await act(async () => primera.resolver(respuesta(1)));
        expect(axios.post).toHaveBeenCalledTimes(2);
        expect(axios.post.mock.lastCall[1]).toMatchObject({ _method: 'put', revision_esperada: 1, base_captura: 'base-1' });
    });
    it('un 409 conserva cambios, bloquea reintento y no adopta la base del servidor', async () => {
        const primera = diferida(); axios.post.mockReturnValue(primera.promesa);
        await montar({ pedido: pedidoServidor }); await editarFolio('LOCAL-Q3'); await tick();
        await act(async () => primera.rechazar({ response: { status: 409, data: { updated_at: 'futuro', base_captura: 'ajena', message: 'Conflicto Q3' } } }));
        await editarFolio('LOCAL-CONSERVADO'); await tick(); await guardarManual();
        expect(axios.post).toHaveBeenCalledTimes(1);
        expect(document.querySelector('input[placeholder="Folio"]').value).toBe('LOCAL-CONSERVADO');
        expect(document.body.textContent).toContain('conflicto');
        expect(document.body.textContent).toContain('Conservar copia local y recargar');
    });
    it('permite reintentar un error de red con la misma base sin fingir guardado', async () => {
        axios.post.mockRejectedValueOnce(new Error('sin red')).mockResolvedValue(respuesta(1));
        await montar({ pedido: pedidoServidor }); await tick();
        expect(document.body.textContent).toMatch(/error/i);
        await guardarManual();
        expect(axios.post.mock.lastCall[1]).toMatchObject({ revision_esperada: 0, base_captura: 'base-inicial' });
    });
    it('la solicitud espera los últimos cambios confirmados y dos clics no duplican el comando', async () => {
        const primera = diferida(), segunda = diferida();
        axios.post.mockReturnValueOnce(primera.promesa).mockReturnValueOnce(segunda.promesa).mockResolvedValue({ data: { ok: true } });
        router.post.mockClear();
        await montar({ pedido: pedidoServidor }); await tick();
        await editarFolio('REFERENCIA-RECIENTE');
        await act(() => {
            const boton = Array.from(document.querySelectorAll('button')).find((b) => b.textContent.includes('Solicitar preparación en Tienda'));
            expect(boton).toBeTruthy(); boton.click(); boton.click();
        });
        expect(router.post).not.toHaveBeenCalled();
        await act(async () => primera.resolver(respuesta(1)));
        expect(axios.post).toHaveBeenCalledTimes(2);
        expect(router.post).not.toHaveBeenCalled();
        await act(async () => segunda.resolver(respuesta(2)));
        expect(axios.post).toHaveBeenCalledTimes(3);
        expect(axios.post.mock.lastCall[1]).toMatchObject({ folio_referencia: 'REFERENCIA-RECIENTE', revision_esperada: 2, base_captura: 'base-2' });
        expect(axios.post.mock.lastCall[1].idempotencia_clave).toBeTruthy();
    });
    it('una respuesta de otra apertura no confirma ni reemplaza la nueva captura', async () => {
        const primera = diferida(); axios.post.mockReturnValue(primera.promesa);
        await montar({ pedido: pedidoServidor }); await tick();
        await act(() => root.render(<ModalFormPedido abierto={false} onClose={() => {}} catalogos={catalogos} pedido={pedidoServidor} />));
        await montar({ pedido: { ...pedidoServidor, base_captura: 'base-nueva', solicitud_preparacion: { ...pedidoServidor.solicitud_preparacion, folio_referencia: 'NUEVA-APERTURA' } } });
        await act(async () => primera.resolver(respuesta(99)));
        expect(document.querySelector('input[placeholder="Folio"]').value).toBe('NUEVA-APERTURA');
        expect(document.body.textContent).not.toContain('Servidor · Q3-41');
        expect(document.body.textContent).not.toContain('Servidor · error');
    });

    it('caracteriza CEDIS a sucursal con historia Tienda sin fabricar una transición', async () => {
        router.post.mockClear();
        await montar({ pedido: { ...pedidoServidor, solicitud_preparacion: null, usa_preparacion_tienda: true, tarea_preparacion: null,
            origen: { id: 1, requiere_logistica: false, nombre: 'Tienda' }, estatus: { fase_ciclo: 'PESAJE_PENDIENTE' }, estatus_envio: 'pendiente_pesaje', consulta_cerrada: false } });
        expect(controlTrasEtiqueta('Almacén de salida')?.value).toBe('9');
        expect(document.body.textContent).not.toContain('Esperando respuesta de Tienda');
        expect(document.body.textContent).toContain('Esperando CEDIS');
        expect(router.post).not.toHaveBeenCalled();
    });

});
