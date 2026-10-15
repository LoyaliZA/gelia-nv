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
                post: vi.fn(),
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
    formulario_config: { autosave_debounce_ms: 600000 },
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
    vi.unstubAllGlobals();
});

describe('Registrar Pedidos: recuperación de captura', () => {
    it('restaura modalidad, prioridad, referencia y entrega municipal al reabrir', async () => {
        await montar({ pedido: pedidoServidor });
        expect(controlTrasEtiqueta('Preparación del envío desde Tienda')?.value).toBe('ENVIO_MUNICIPIO');
        expect(controlTrasEtiqueta('Prioridad del mismo día')?.checked).toBe(true);
        expect(document.querySelector('input[aria-label="Referencia adicional opcional"]')?.value).toBe('COT-014');
        expect(controlTrasEtiqueta('Municipio / destino')?.value).toBe('San Pedro');
        expect(controlTrasEtiqueta('Nombre destinatario')?.value).toBe('Receptor alterno');
        expect(controlTrasEtiqueta('Teléfono')?.value).toBe('2221112233');
        expect(controlTrasEtiqueta('Dirección / referencia')?.value).toBe('Calle 4 esquina');
        expect(controlTrasEtiqueta('Transporte')?.value).toBe('8');
        expect(controlTrasEtiqueta('Cobro')?.value).toBe('POR_COBRAR');
        expect(controlTrasEtiqueta('Almacén')?.value || document.querySelector('[data-campo="almacen"] select')?.value).toBe('9');

        const modalidad = controlTrasEtiqueta('Preparación del envío desde Tienda');
        await act(async () => {
            modalidad.value = 'ENVIO_BODEGA_NORMAL';
            modalidad.dispatchEvent(new Event('change', { bubbles: true }));
        });
        expect(controlTrasEtiqueta('Municipio / destino')).toBeNull();
        await act(async () => {
            modalidad.value = 'ENVIO_MUNICIPIO';
            modalidad.dispatchEvent(new Event('change', { bubbles: true }));
        });
        expect(controlTrasEtiqueta('Municipio / destino')?.value).toBe('San Pedro');
        expect(controlTrasEtiqueta('Nombre destinatario')?.value).toBe('Receptor alterno');

        const cotizacion = Array.from(document.querySelectorAll('a')).find((nodo) => nodo.textContent.includes('Cotización'));
        const datos = Array.from(document.querySelectorAll('a')).find((nodo) => nodo.textContent.includes('Datos del cliente'));
        await act(async () => cotizacion.click());
        await act(async () => datos.click());
        expect(controlTrasEtiqueta('Municipio / destino')?.value).toBe('San Pedro');
        expect(document.querySelector('[data-campo="almacen"] select')?.value).toBe('9');

        await act(async () => root.unmount());
        root = createRoot(container);
        await montar({ pedido: { ...pedidoServidor, consulta_cerrada: true } });
        expect(controlTrasEtiqueta('Municipio / destino')?.value).toBe('San Pedro');
        expect(controlTrasEtiqueta('Prioridad del mismo día')?.checked).toBe(true);
    });

    it('recupera un borrador local v2 del mismo usuario y no inventa municipio en v1', async () => {
        localStorage.setItem('control_pedidos.borrador_pedido_v2', JSON.stringify({
            usuario_id: 7,
            origen_id: 1,
            almacen_id: 9,
            captura_preparacion: {
                version: 1,
                codigo_modalidad: 'ENVIO_MUNICIPIO',
                prioridad_md: false,
                tipo_referencia: 'PEDIDO',
                folio_referencia: 'PED-2',
                entrega_municipal: {
                    destinatario_es_cliente: true,
                    municipio_destino: 'Tlaxcala',
                    catalogo_paqueteria_id: 8,
                    modalidad_cobro: 'PAGADO',
                },
            },
        }));
        await montar({ pedido: null, recuperarBorrador: true });
        expect(controlTrasEtiqueta('Municipio / destino')?.value).toBe('Tlaxcala');
        expect(document.querySelector('input[aria-label="Referencia adicional opcional"]')?.value).toBe('PED-2');

        await act(async () => root.unmount());
        localStorage.clear();
        localStorage.setItem('control_pedidos.borrador_pedido_v1', JSON.stringify({
            origen_id: 1,
            almacen_id: 9,
            comentarios_drive: 'Solo v1',
        }));
        root = createRoot(container);
        await montar({ pedido: null, recuperarBorrador: true });
        expect(controlTrasEtiqueta('Municipio / destino')).toBeNull();
        expect(document.body.textContent).not.toContain('Tlaxcala');
    });

    it('no aplica una captura local de otro usuario ni de otro pedido', async () => {
        localStorage.setItem('control_pedidos.borrador_pedido_v2', JSON.stringify({
            usuario_id: 99,
            pedido_id: 41,
            captura_preparacion: {
                codigo_modalidad: 'ENVIO_MUNICIPIO',
                entrega_municipal: { municipio_destino: 'Ajeno' },
            },
        }));
        await montar({ pedido: pedidoServidor });
        expect(controlTrasEtiqueta('Municipio / destino')?.value).not.toBe('Ajeno');
        expect(controlTrasEtiqueta('Municipio / destino')?.value).toBe('San Pedro');
    });
});
