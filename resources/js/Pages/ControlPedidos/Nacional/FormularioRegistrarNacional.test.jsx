// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import FormularioRegistrarNacional from './FormularioRegistrarNacional';
import { adaptadorConsultaNacional } from '../Partials/adaptadoresConsulta';

vi.mock('axios', () => ({ default: { post: vi.fn(), get: vi.fn() } }));
vi.mock('../../../Components/Almacenes/SelectorProducto', () => ({
    default: ({ onChange }) => <button type="button" onClick={() => onChange(1, { id: 1, sku: 'SKU-NUEVA', descripcion: 'Crema nueva' })}>Elegir perfume</button>,
}));
vi.mock('../Partials/GaleriaEvidenciasPedido', () => ({ default: () => null }));
vi.mock('../../../utils/uuidSolicitud', () => ({ uuidSolicitud: () => '11111111-1111-4111-8111-111111111111' }));

const pedido = {
    id: 9,
    folio_remision: 'WIZ-1',
    documentos: [{ id: 1, url: '/a.pdf' }],
    revision_registro: 1,
    base_captura: 'a'.repeat(64),
};
const catalogos = {
    paqueterias: [
        { id: 8, nombre: 'DHL', categoria: 'comercial', requiere_peso: false, sin_logistica: false, activo: true },
        { id: 11, nombre: 'Foránea', categoria: 'comercial', requiere_peso: true, sin_logistica: true, activo: true },
        { id: 9, nombre: 'PAQ. PENDIENTE', categoria: 'local_regional', requiere_peso: false, sin_logistica: false, activo: true },
        { id: 2, nombre: 'Local', categoria: 'local_regional', requiere_peso: false, activo: true },
    ],
    sucursales: [{ id: 4, nombre: 'Sucursal centro' }],
};
let root;
let host;

beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
    axios.post.mockReset();
    axios.post.mockResolvedValue({ data: { ok: true, id: 9, pesaje_solicitado_at: '2026-11-20T12:00:00Z' } });
});

afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

async function render() {
    const guardar = vi.fn(async () => ({ ...pedido, revision_registro: 2, base_captura: 'b'.repeat(64) }));
    await act(async () => {
        root.render(
            <FormularioRegistrarNacional pedido={pedido} catalogos={catalogos} guardar={guardar} onPedido={vi.fn()}>
                {({ contenido, pie }) => <div>{contenido}{pie}</div>}
            </FormularioRegistrarNacional>,
        );
    });

    return guardar;
}

function botonEnviar() {
    return [...document.querySelectorAll('button')].find((boton) => boton.textContent.includes('Enviar consulta a CEDIS'));
}

it('mantiene el envío disponible y no consulta sin paquetería', async () => {
    const guardar = await render();
    expect(botonEnviar().disabled).toBe(false);
    expect(document.getElementById('paqueteria-nacional').textContent).toContain('DHL');
    expect(document.getElementById('paqueteria-nacional').textContent).not.toContain('Foránea');
    expect(document.body.textContent).not.toContain('PAQ. PENDIENTE');
    expect(document.body.textContent).not.toContain('Local');
    await act(async () => {
        botonEnviar().click();
        await Promise.resolve();
    });
    expect(document.body.textContent).toContain('Elija la paquetería comercial.');
    expect(guardar).not.toHaveBeenCalled();
    expect(axios.post).not.toHaveBeenCalled();
});

it('envía la consulta de pesaje con la paquetería comercial', async () => {
    const guardar = await render();
    const select = document.getElementById('paqueteria-nacional');
    await act(async () => {
        select.value = '8';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await act(async () => botonEnviar().click());
    expect(guardar).toHaveBeenCalledWith({ catalogo_paqueteria_id: 8 });
    expect(axios.post).toHaveBeenCalledWith(
        '/control-pedidos/9/solicitar-pesaje',
        expect.objectContaining({ revision_esperada: 2, base_captura: 'b'.repeat(64) }),
        expect.anything(),
    );
    expect(document.body.textContent).toContain('Salida: CEDIS');
});

it('actualiza la paquetería después del cierre, incluida la pendiente', async () => {
    const cerrado = {
        ...pedido,
        consulta_cerrada_at: '2026-11-20T18:00:00Z',
        pesaje_solicitado_at: '2026-11-20T12:00:00Z',
        catalogo_paqueteria_id: 8,
        revision_registro: 4,
        base_captura: 'd'.repeat(64),
    };
    axios.post.mockResolvedValue({ data: { ok: true, catalogo_paqueteria_id: 9 } });
    await act(async () => {
        root.render(
            <FormularioRegistrarNacional pedido={cerrado} catalogos={catalogos} guardar={vi.fn()} onPedido={vi.fn()}>
                {({ contenido, pie }) => <div>{contenido}{pie}</div>}
            </FormularioRegistrarNacional>,
        );
    });
    const select = document.getElementById('paqueteria-posterior');
    expect(select).not.toBeNull();
    expect(select.textContent).toContain('PAQ. PENDIENTE');
    expect(document.getElementById('paqueteria-nacional')).toBeNull();
    await act(async () => {
        select.value = '9';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });
    const boton = [...document.querySelectorAll('button')].find((item) => item.textContent.includes('Actualizar paquetería'));
    expect(boton.disabled).toBe(false);
    await act(async () => boton.click());
    expect(axios.post).toHaveBeenCalledWith(
        '/control-pedidos/9/paqueteria-nacional',
        expect.objectContaining({
            catalogo_paqueteria_id: 9,
            revision_esperada: 4,
            base_captura: 'd'.repeat(64),
        }),
        expect.anything(),
    );
});

it('registra el importe del complemento en el pedido con remisión', async () => {
    const conRemision = {
        ...pedido,
        numero_remision: 'REM-9',
        pago_validado_at: '2026-11-20T18:00:00Z',
        revisiones: [{ sku: 'SKU-VIEJO', cantidad: 2 }],
    };
    await act(async () => {
        root.render(
            <FormularioRegistrarNacional pedido={conRemision} catalogos={catalogos} guardar={vi.fn()} onPedido={vi.fn()}>
                {({ contenido, pie }) => <div>{contenido}{pie}</div>}
            </FormularioRegistrarNacional>,
        );
    });
    const importe = document.getElementById('importe-complemento');
    const boton = [...document.querySelectorAll('button')].find((item) => item.textContent.includes('Registrar complemento'));
    expect(importe).not.toBeNull();
    expect(boton.disabled).toBe(false);
    await act(async () => {
        importe.dispatchEvent(new FocusEvent('focusout', { bubbles: true }));
    });
    expect(document.body.textContent).toContain('Indique el importe del complemento.');
    const soporte = document.getElementById('soporte-complemento');
    await act(async () => {
        Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(importe, '40.50');
        importe.dispatchEvent(new Event('input', { bubbles: true }));
        Object.defineProperty(soporte, 'files', { configurable: true, value: [new File(['soporte'], 'soporte.jpg', { type: 'image/jpeg' })] });
        soporte.dispatchEvent(new Event('change', { bubbles: true }));
        [...document.querySelectorAll('button')].find((item) => item.textContent.includes('Elegir perfume')).click();
    });
    await act(async () => {
        [...document.querySelectorAll('button')].find((item) => item.textContent === 'Agregar').click();
    });
    await act(async () => boton.click());
    const cuerpo = axios.post.mock.calls.at(-1)[1];
    expect(axios.post).toHaveBeenCalledWith('/control-pedidos/9/ajuste-nacional', expect.any(FormData), expect.anything());
    expect(cuerpo.get('tipo')).toBe('anexo');
    expect(cuerpo.get('solicitud_id')).toBe('11111111-1111-4111-8111-111111111111');
    expect(cuerpo.get('total_mercancia_nueva')).toBe('40.5');
    expect(cuerpo.get('piezas[0][sku]')).toBe('SKU-NUEVA');
    expect(cuerpo.get('piezas[0][descripcion]')).toBe('Crema nueva');
    expect(cuerpo.get('piezas[0][descripcion]')).not.toBe('Complemento');
    expect(cuerpo.get('evidencia')).toBeTruthy();
    expect(String([...cuerpo.entries()])).not.toContain('SKU-VIEJO');
});

it('solicita recolección en sucursal sin convertir la modalidad', async () => {
    const conRemision = {
        ...pedido,
        numero_remision: 'REM-9',
        pesaje_solicitado_at: '2026-11-20T12:00:00Z',
        costo_envio: 55,
        revision_registro: 3,
        base_captura: 'c'.repeat(64),
    };
    axios.post.mockResolvedValue({ data: { ok: true, id: 9, cambio_a_tienda: { estado: 'pendiente_recepcion', sucursal_destino_id: 4 } } });
    await act(async () => {
        root.render(
            <FormularioRegistrarNacional pedido={conRemision} catalogos={catalogos} guardar={vi.fn()} onPedido={vi.fn()}>
                {({ contenido, pie }) => <div>{contenido}{pie}</div>}
            </FormularioRegistrarNacional>,
        );
    });
    expect(document.body.textContent).toContain('La guía invalidada no está cancelada ante la paquetería');
    const select = document.getElementById('sucursal-recoleccion-nacional');
    await act(async () => {
        select.value = '4';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await act(async () => {
        [...document.querySelectorAll('button')].find((item) => item.textContent.includes('Solicitar recolección en sucursal')).click();
    });
    expect(axios.post).toHaveBeenCalledWith(
        '/control-pedidos/9/cambio-tienda-nacional',
        expect.objectContaining({ sucursal_destino_id: 4, solicitud_id: '11111111-1111-4111-8111-111111111111' }),
        expect.anything(),
    );
});

it('anexa sobre el mismo pedido nacional', async () => {
    const form = new FormData();
    const adaptador = adaptadorConsultaNacional({
        pedidoId: 7,
        pedido: { pesaje_respondido_at: '2026-11-20', revision_registro: 3, base_captura: 'c'.repeat(64) },
    });
    await adaptador.comandos.anexar({ archivos: [{ size: 10 }], armar: () => form });
    expect(axios.post).toHaveBeenCalledWith('/control-pedidos/7/ajuste-nacional', form, expect.anything());
    expect(form.get('tipo')).toBe('anexo');
    expect(String(axios.post.mock.calls[0][0])).not.toContain('anexo-tienda');
});
