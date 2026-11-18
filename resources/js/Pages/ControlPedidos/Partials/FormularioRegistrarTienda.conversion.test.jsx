// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import FormularioRegistrarTienda from './FormularioRegistrarTienda';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } }));
vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: { auth: { user: { id: 1, departamento_id: 1, permissions: [] } } } }) }));
vi.mock('../Municipal/FormularioRegistrarMunicipal', () => ({
    default: ({ children, modo }) => (
        <div>
            <p>Captura municipal</p>
            <p>{modo}</p>
            {children({
                contenido: <p>Terminal destino</p>,
                pie: null,
                captura: {
                    catalogo_paqueteria_id: 2,
                    consignatario_id: 12,
                    almacen_id: null,
                    leyenda_terminal: 'PAGADO',
                    costo_transporte: 'conocido',
                    costo_envio: '150.00',
                },
                motivoEnvio: '',
                sucia: true,
            })}
        </div>
    ),
}));
vi.mock('../Municipal/SeguimientoSolicitudMunicipal', () => ({
    default: ({ pedido }) => <div>Seguimiento municipal {pedido?.folio}</div>,
}));
vi.mock('./GaleriaEvidenciasPedido', () => ({
    default: ({ label }) => <p>{label}</p>,
}));
vi.mock('./ModalVistaPreviaDocumento', () => ({ default: () => null }));
vi.mock('./useActualizacionesPedidos', () => ({ default: () => {} }));
vi.mock('./SeguimientoSolicitudTienda', () => ({ default: () => null, respuestaDeTiendaEmitida: () => false }));
vi.mock('./ContinuidadTienda', () => ({ default: () => null }));
vi.mock('./ConfirmacionRevisionTienda', () => ({ default: () => null }));
vi.mock('./SeguimientoTransferenciaTienda', () => ({ default: () => null }));
vi.mock('../../../utils/uuidSolicitud', () => ({ uuidSolicitud: () => '11111111-1111-4111-8111-111111111111' }));

let root;
let host;
const flags = {
    origen: 'tienda',
    destinos: [
        { codigo: 'municipal', nombre: 'Envío municipal', habilitado: true, motivo_bloqueo: null },
        { codigo: 'tienda', nombre: 'Tienda', habilitado: false, motivo_bloqueo: 'Se habilitará más adelante.' },
        { codigo: 'nacional', nombre: 'Envío nacional', habilitado: false, motivo_bloqueo: 'Se habilitará más adelante.' },
    ],
    puede_iniciar: true,
    herencia: { caso: 'sin_consulta' },
    finanzas: { pendiente_transporte: '150.00', transporte_desconocido: false },
    fisica: { condicion: 'sin_movimiento', pendiente: false },
    version: 0,
};
const pedido = {
    id: 81,
    folio: 'PBMA-81',
    folio_remision: 'WIZ-81',
    revision_registro: 3,
    cliente_id: 3,
    cliente: { nombre: 'Cliente de ejemplo', numero_cliente: 'C-1' },
    departamento_operacion_id: 1,
    conversion_modalidad: flags,
    solicitud_preparacion: { modalidad_entrega: 'tienda' },
    documentos: [{ tipo: 'pdf_pedido', url: '/doc.pdf', nombre_original: 'pedido.pdf', activo: true }],
};
const reserva = {
    flags,
    setFlags: vi.fn(),
    reservaOk: true,
    error: '',
    setError: vi.fn(),
    aviso: '',
    setAviso: vi.fn(),
    instanciaId: () => '11111111-1111-4111-8111-111111111111',
    claveIdempotencia: () => '11111111-1111-4111-8111-111111111111',
    marcarConfirmada: vi.fn(),
};
const boton = (nombre) => [...document.querySelectorAll('button')].find((el) => el.textContent.includes(nombre));

beforeEach(() => {
    vi.useFakeTimers();
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    axios.get.mockResolvedValue({ data: pedido });
    axios.post.mockResolvedValue({ data: { ok: true, folio: 'PBMA-81', modalidad_entrega: 'municipal', conversion_modalidad: flags } });
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
});
afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
    vi.useRealTimers();
    vi.restoreAllMocks();
});

const montar = () => act(() => root.render(
    <FormularioRegistrarTienda
        abierto
        intencion="conversion"
        reservaConversion={reserva}
        pedido={pedido}
        catalogos={{ departamentos: [{ id: 1, nombre: 'Ventas' }] }}
        onClose={() => {}}
    />,
));

it('muestra destinos inactivos y un solo botón de confirmación', async () => {
    await montar();
    expect(document.body.textContent).toContain('Cambiar modalidad');
    expect(document.body.textContent).toContain('Envío municipal');
    expect(document.body.textContent).toContain('Envío nacional');
    expect(document.body.textContent).toContain('Se habilitará más adelante');
    expect(document.body.textContent).not.toMatch(/M-02|T-03/);
    expect(document.body.textContent).not.toContain('Guardar solicitud');
    expect(document.body.textContent).not.toContain('Enviar consulta');
    expect(document.body.textContent).not.toContain('Solicitar en tienda');
    const confirmar = boton('Confirmar cambio de modalidad');
    expect(confirmar).toBeTruthy();
    expect([...document.querySelectorAll('button')].filter((el) => el.textContent.includes('Confirmar')).length).toBe(1);
    const nacional = [...document.querySelectorAll('#modalidad-tienda option')].find((el) => el.value === 'nacional');
    expect(nacional.disabled).toBe(true);
});

it('cambia el selector sin autoguardar y confirma con la misma instancia', async () => {
    axios.post.mockImplementation(async (url) => {
        if (String(url).endsWith('/conversion-modalidad')) {
            return { data: { ok: true, folio: 'PBMA-81', modalidad_entrega: 'municipal', pedido_id: 81, herencia: { consulta: { estado: 'respondida' } } } };
        }
        return { data: { ok: true, conversion_modalidad: flags } };
    });
    await montar();
    const select = document.getElementById('modalidad-tienda');
    await act(() => {
        select.value = 'municipal';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await act(() => vi.advanceTimersByTimeAsync(800));
    const posts = axios.post.mock.calls.map(([url]) => String(url));
    expect(posts.some((url) => url.includes('municipal.solicitud') || url.includes('/solicitud'))).toBe(false);
    expect(document.body.textContent).toContain('Captura municipal');
    expect(document.body.textContent).toContain('Evidencia petición cliente');
    const motivo = document.querySelector('textarea');
    await act(() => {
        Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set.call(motivo, 'El cliente pide entrega en casa');
        motivo.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await act(() => document.querySelector('input[type=checkbox]').click());
    await act(() => boton('Confirmar cambio de modalidad').click());
    await act(() => Promise.resolve());
    const confirmacion = axios.post.mock.calls.find(([url]) => String(url).endsWith('/conversion-modalidad') && !String(url).includes('reserva') && !String(url).includes('vista-previa'));
    expect(confirmacion[1].get('instancia_id')).toBe('11111111-1111-4111-8111-111111111111');
    expect(confirmacion[1].get('destino')).toBe('municipal');
    expect(document.body.textContent).toContain('Cambio confirmado');
    expect(document.body.textContent).toContain('Seguimiento municipal PBMA-81');
    expect(reserva.marcarConfirmada).toHaveBeenCalled();
});
