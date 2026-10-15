// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ModalFormPedido from './ModalFormPedido';

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
                    user: { id: 7, permissions: ['control_pedidos.crear', 'control_pedidos.preparacion.solicitar'], roles: [] },
                },
                flash: {},
            },
        }),
        router: { post: vi.fn(), visit: vi.fn() },
        useForm: (initial) => {
            const [data, setDataState] = ReactMod.useState(initial);
            const setData = (key, value) => {
                if (typeof key === 'function') { setDataState(key); return; }
                if (typeof key === 'object' && key !== null && value === undefined) { setDataState(key); return; }
                setDataState((prev) => ({ ...prev, [key]: value }));
            };
            return { data, setData, post: vi.fn(), processing: false, reset: vi.fn(), errors: {}, transform: vi.fn() };
        },
    };
});

const catalogos = {
    preparacion_config: {
        activo: true,
        modalidades_habilitadas: ['RECOGE_TIENDA', 'RECOGE_TIENDA_TRANSFERENCIA', 'ENVIO_MUNICIPIO', 'ENVIO_BODEGA_NORMAL'],
        modalidades_catalogo: [
            { codigo: 'RECOGE_TIENDA', nombre: 'Recoge hoy' },
            { codigo: 'RECOGE_TIENDA_TRANSFERENCIA', nombre: 'Resguardo con transferencia' },
            { codigo: 'ENVIO_MUNICIPIO', nombre: 'Envío municipal' },
            { codigo: 'ENVIO_BODEGA_NORMAL', nombre: 'Envío bodega' },
        ],
        almacenes_habilitados: [],
    },
    destino_sucursal_config: { modalidades_requieren_destino: ['RECOGE_TIENDA', 'RECOGE_TIENDA_TRANSFERENCIA'] },
    formulario_config: { autosave_debounce_ms: 60000 },
    almacenes: [
        { id: 9, nombre: 'CEDIS Centro', codigo: 'CEDIS', tipo_almacen: { nombre: 'CEDIS' }, permite_salida_pedidos: true },
        { id: 4, nombre: 'PDV Norte', codigo: 'PDV-1', tipo_almacen: { nombre: 'PDV' }, permite_salida_pedidos: true },
        { id: 8, nombre: 'Solo destino', codigo: 'CC', tipo_almacen: { nombre: 'CEDIS' }, permite_salida_pedidos: false },
    ],
    sucursales: [{ id: 3, nombre: 'Sucursal PDV', codigo: 'TN' }],
    origenes: [
        { id: 1, nombre: 'Tienda', requiere_logistica: false },
        { id: 2, nombre: 'Envío', requiere_logistica: true },
    ],
    tipos_operacion_envio: [],
};

const base = {
    id: 41,
    cliente_id: 123,
    cliente: { id: 123, nombre: 'Cliente Q4' },
    folio: 'Q4-41',
    revision_registro: 0,
    base_captura: 'base',
    estatus: { fase_ciclo: 'BORRADOR' },
    documentos: [{ id: 1, tipo: 'pdf_pedido', activo: true }],
    origen_id: 1,
    origen: { id: 1, requiere_logistica: false, nombre: 'Tienda' },
    almacen_id: 9,
    sucursal_destino_id: 3,
    consulta_cerrada: false,
};

let root;
let container;

const control = (texto) => {
    const label = Array.from(document.querySelectorAll('label')).find((nodo) => nodo.textContent.includes(texto));
    return label?.querySelector('select, input') || label?.parentElement?.querySelector('select, input') || null;
};

const montar = async (pedido) => {
    await act(async () => {
        root.render(<ModalFormPedido abierto onClose={() => {}} catalogos={catalogos} pedido={pedido} />);
    });
};

beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    vi.stubGlobal('route', (name, params) => `/${name}${params ? `/${params}` : ''}`);
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
});

describe('Q4 elegibilidad CEDIS, Tienda y PDV', () => {
    it('conserva salida CEDIS y destino PDV sin atribuir la consulta a Tienda', async () => {
        await montar(base);
        expect(control('Almacén de salida')?.value).toBe('9');
        expect(control('Sucursal destino')?.value).toBe('3');
        expect(document.body.textContent).toContain('Solicitar separación de mercancía a CEDIS');
        expect(document.body.textContent).not.toContain('Solicitar preparación en Tienda');
    });

    it('una tarea respondida no presenta preparación vigente ni sustituye el almacén', async () => {
        await montar({
            ...base,
            usa_preparacion_tienda: true,
            estatus: { fase_ciclo: 'PESAJE_PENDIENTE' },
            estatus_envio: 'pendiente_pesaje',
            tarea_preparacion: { id: 3, estado: 'RESPONDIDA', modalidad: { codigo: 'ENVIO_BODEGA_NORMAL' } },
        });
        expect(control('Almacén de salida')?.value).toBe('9');
        expect(document.body.textContent).toContain('Esperando CEDIS');
        expect(document.body.textContent).not.toContain('Esperando respuesta de Tienda');
    });

    it('una tarea vigente conserva la responsabilidad de Tienda y el almacén CEDIS', async () => {
        await montar({
            ...base,
            tarea_preparacion: { id: 4, estado: 'PENDIENTE', modalidad: { codigo: 'ENVIO_BODEGA_NORMAL' } },
        });
        expect(control('Almacén de salida')?.value).toBe('9');
        expect(document.body.textContent).toContain('Solicitar preparación en Tienda');
    });

    it('recoge hoy sigue exigiendo el almacén PDV', async () => {
        await montar({
            ...base,
            solicitud_preparacion: { codigo_modalidad: 'RECOGE_TIENDA' },
        });
        expect(control('Almacén de salida')?.value).toBe('4');
        expect(document.body.textContent).toContain('Solicitar apartado en PDV');
    });

    it('cambiar a una modalidad compatible no borra la sucursal ni la salida CEDIS', async () => {
        await montar(base);
        const boton = Array.from(document.querySelectorAll('button')).find((nodo) => nodo.textContent.includes('Resguardo con transferencia'));
        await act(() => boton.click());
        expect(control('Almacén de salida')?.value).toBe('9');
        expect(control('Sucursal destino')?.value).toBe('3');
        expect(document.body.textContent).toContain('Este almacén de salida se conserva');
        expect(document.body.textContent).toContain('Solicitar separación de mercancía a CEDIS');
    });

    it('un envío municipal no sustituye la salida CEDIS por PDV', async () => {
        await montar({
            ...base,
            origen_id: 2,
            origen: { id: 2, requiere_logistica: true, nombre: 'Envío' },
            catalogo_paqueteria_id: 8,
            solicitud_preparacion: { codigo_modalidad: 'ENVIO_MUNICIPIO' },
        });
        expect(control('Almacén de salida')?.value).toBe('9');
        expect(document.body.textContent).toContain('Este almacén de salida se conserva');
        expect(document.body.textContent).not.toContain('Solicitar preparación en Tienda');
    });

    it('un pedido enviado no se reinterpreta como preparación pendiente', async () => {
        await montar({
            ...base,
            usa_preparacion_tienda: true,
            estatus: { fase_ciclo: 'ENVIADO' },
            estatus_envio: 'completo',
            tarea_preparacion: { id: 8, estado: 'RESPONDIDA', modalidad: { codigo: 'ENVIO_BODEGA_NORMAL' } },
        });
        expect(document.body.textContent).not.toContain('Esperando respuesta de Tienda');
        expect(document.body.textContent).not.toContain('Pendiente de respuesta de Tienda');
    });
});
