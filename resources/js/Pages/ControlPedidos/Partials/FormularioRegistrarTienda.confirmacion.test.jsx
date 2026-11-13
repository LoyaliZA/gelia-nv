// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import FormularioRegistrarTienda from './FormularioRegistrarTienda';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }));
vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...await importOriginal(),
    usePage: () => ({ props: { auth: { user: { id: 1, departamento_id: 1, permissions: [] } } } }),
}));

let root, container, guardado;
const boton = (texto) => [...document.querySelectorAll('button')].find((elemento) => elemento.textContent.trim() === texto);
const total = () => document.getElementById('total-mercancia-tienda');
const cambiar = (campo, valor) => {
    Object.getOwnPropertyDescriptor(Object.getPrototypeOf(campo), 'value').set.call(campo, String(valor));
    campo.dispatchEvent(new Event(campo.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true }));
};
const escribir = async (importe) => {
    expect(total().disabled).toBe(false);
    await act(async () => total().focus());
    await act(async () => cambiar(total(), importe));
};
const autoguardar = () => act(() => vi.advanceTimersByTimeAsync(700));
const montar = () => act(async () => root.render(<FormularioRegistrarTienda abierto pedido={guardado} onClose={() => {}} />));

beforeEach(() => {
    vi.resetAllMocks();
    vi.useFakeTimers();
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    vi.stubGlobal('route', (nombre, id) => `${nombre}/${id}`);
    guardado = {
        id: 9, folio: 'PED-9', folio_remision: 'WIZ-9', cliente_id: 3, departamento_operacion_id: 1, sucursal_destino_id: 2,
        consulta_cerrada: false, puede_cerrar_consulta: true, total_mercancia: 0, saldo_a_favor: 0,
        revision_registro: 1, base_captura: 'inicial', estatus: { fase_ciclo: 'PESAJE_RESPONDIDO' },
        solicitud_preparacion: { modalidad_entrega: 'tienda', area_revision: 'preparacion_tienda', caso_tienda: 'revisar_apartar', revision_disponibilidad: { estado: 'sin_excepciones' }, horarios_operativos: { activos: false } },
    };
    axios.get.mockImplementation(async (url) => {
        if (url.endsWith('/disponibilidad-tienda')) return { data: guardado };
        // El botón debe funcionar aunque la lectura de respaldo siga en vuelo.
        if (url.includes('pagos.resumen')) return new Promise(() => {});
        return { data: {} };
    });
    vi.stubGlobal('fetch', vi.fn(async () => ({ json: async () => {
        const transferencia = guardado.solicitud_preparacion.cobertura_tienda?.tipo === 'transferencia';
        const pagado = transferencia ? 200 : 0;
        const pendiente = Math.max(0, guardado.total_mercancia - pagado);
        return { ...guardado, pagos: transferencia ? [{ id: 1, monto: pagado, forma_pago: 'transferencia' }] : [],
            resumen: { cubierto: !pendiente, pendiente, total_pagado: pagado, bloqueos: pendiente ? ['El total a cubrir no está completo.'] : [] } };
    } })));
    axios.post.mockImplementation(async (url, datos) => {
        if (url.endsWith('/cerrar-consulta')) {
            guardado = { ...guardado, consulta_cerrada: true, base_captura: 'cierre' };
            return { data: { consulta_cerrada: true, revision_registro: guardado.revision_registro, base_captura: guardado.base_captura } };
        }
        if (url.endsWith('/cobertura-tienda')) {
            guardado = { ...guardado, base_captura: 'cobertura', solicitud_preparacion: { ...guardado.solicitud_preparacion, cobertura_tienda: { tipo: datos.cobertura } } };
            return { data: { solicitud_preparacion: guardado.solicitud_preparacion, revision_registro: guardado.revision_registro, base_captura: guardado.base_captura } };
        }
        if (url.endsWith('/confirmar-pedido-tienda')) {
            return { data: { solicitud_preparacion: { ...guardado.solicitud_preparacion, cobertura_tienda: { tipo: 'apartado', confirmacion_enviada_at: '2026-11-10' } } } };
        }
        if (datos.revision_esperada !== guardado.revision_registro || datos.base_captura !== guardado.base_captura) {
            throw { response: { status: 409, data: { codigo: 'conflicto_captura', message: 'Base de captura anterior' } } };
        }
        guardado = { ...guardado, total_mercancia: datos.total_mercancia, revision_registro: guardado.revision_registro + 1, base_captura: `importe-${datos.total_mercancia}` };
        return { data: guardado };
    });
    axios.put.mockImplementation(async () => ({ data: { ...guardado, estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR' } } }));
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

it.each(['apartado', 'credito', 'transferencia'])('confirma %s con la base recién guardada y el mismo modal', async (cobertura) => {
    await montar();
    const modal = document.querySelector('.registrar-tienda-modal');
    await act(async () => boton('Confirmar separación de pedido en Tienda').click());
    await act(async () => cambiar(document.getElementById('modalidad-cobertura-tienda'), cobertura));
    await act(async () => boton('Guardar modalidad').click());

    const texto = cobertura === 'apartado' ? 'Enviar confirmación a tienda' : 'Confirmar pedido y enviar a revisión';
    if (cobertura === 'transferencia') {
        await escribir(250);
        await autoguardar();
        expect(boton(texto).disabled).toBe(true);
    }
    await escribir(200);
    await autoguardar();
    const guardados = axios.post.mock.calls.filter(([url]) => url.endsWith('/autoguardar'));
    expect(guardados[0][1]).toMatchObject({ revision_esperada: 1, base_captura: 'cobertura' });
    expect(guardado.total_mercancia).toBe(200);
    expect(boton(texto).disabled).toBe(false);
    expect(document.querySelector('.registrar-tienda-modal')).toBe(modal);
    expect(document.body.textContent).not.toContain('Cambio en otra sesión');
    await act(async () => boton(texto).click());
    if (cobertura === 'apartado') expect(axios.post.mock.lastCall[0]).toBe('/control-pedidos/9/confirmar-pedido-tienda');
    else expect(axios.put.mock.lastCall[0]).toBe('/control-pedidos/9/enviar');
});

it('conserva el importe tecleado si otra sesión guardó una revisión nueva', async () => {
    guardado = { ...guardado, consulta_cerrada: true, solicitud_preparacion: { ...guardado.solicitud_preparacion, cobertura_tienda: { tipo: 'apartado' } } };
    await montar();
    guardado = { ...guardado, total_mercancia: 300, revision_registro: 2, base_captura: 'otra-sesion' };

    await escribir(200);
    await autoguardar();

    expect(total().value).toBe('200');
    expect(guardado.total_mercancia).toBe(300);
    expect(boton('Enviar confirmación a tienda').disabled).toBe(true);
    expect(document.body.textContent).toContain('Cambio en otra sesión');
});
