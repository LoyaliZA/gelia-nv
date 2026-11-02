// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import TarjetaVendedorGerencia from './TarjetaVendedorGerencia';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

vi.mock('axios', () => ({ default: { post: vi.fn() } }));

const persona = {
    id: 4,
    nombre: 'Vendedor',
    estado_vendedor: 'atendiendo',
    recibe_turnos: false,
    atencion_actual: { turno_id: 12, turno_version: 7, cliente: 'Cliente' },
};
const catalogos = { motivos_cierre: [{ valor: 'venta', etiqueta: 'Venta' }] };

let root;
let onActualizado;
let onError;

function montar(props = {}) {
    const contenedor = document.createElement('div');
    document.body.appendChild(contenedor);
    root = createRoot(contenedor);
    act(() => root.render(React.createElement(TarjetaVendedorGerencia, {
        persona,
        puedeGestionar: true,
        catalogos,
        onActualizado,
        onError,
        ...props,
    })));
}

async function pulsar(texto) {
    const boton = [...document.querySelectorAll('button')]
        .find((elemento) => elemento.textContent.trim() === texto);
    expect(boton).toBeDefined();
    expect(boton.disabled).toBe(false);
    await act(async () => boton.click());
}

describe('gestión de atención desde Operación General', () => {
    beforeEach(() => {
        onActualizado = vi.fn();
        onError = vi.fn();
        sessionStorage.clear();
        vi.stubGlobal('route', vi.fn((nombre, id) => `${nombre}/${id}`));
        axios.post.mockResolvedValue({ data: { turno: { id: 12, version: 8 } } });
    });

    afterEach(() => {
        act(() => root?.unmount());
        document.body.innerHTML = '';
        document.body.style.overflow = '';
        vi.clearAllMocks();
        vi.unstubAllGlobals();
    });

    it('no ofrece transferir sin el permiso de gerencia', () => {
        montar();
        const boton = [...document.querySelectorAll('button')]
            .find((elemento) => elemento.textContent.includes('Transferir'));
        expect(boton).toBeUndefined();
    });

    it('transfiere la atención en curso a otra persona del equipo', async () => {
        montar({
            puedeTransferirTurno: true,
            destinosTransferencia: [{ id: 9, nombre: 'Diana Paola', estado_vendedor: 'disponible' }],
        });
        await pulsar('Transferir');
        const destino = document.querySelector('input[name="destino-transferencia"]');
        expect(destino).not.toBeNull();
        await act(async () => {
            destino.click();
        });
        await pulsar('Confirmar transferencia');

        expect(axios.post).toHaveBeenCalledWith('punto_venta.turnos.transferir/12', {
            version: 7,
            idempotency_key: expect.any(String),
            destino_user_id: 9,
        });
        expect(onActualizado).toHaveBeenCalledOnce();
    });

    it('confirmar cierre envía la versión del turno y actualiza la operación', async () => {
        montar();
        await pulsar('Terminar atención');
        await pulsar('Confirmar cierre');

        expect(axios.post).toHaveBeenCalledWith('punto_venta.turnos.cerrar_atencion/12', {
            version: 7,
            idempotency_key: expect.any(String),
            motivo: 'venta',
            motivo_detalle: null,
        });
        expect(onActualizado).toHaveBeenCalledWith({ turno: { id: 12, version: 8 } });
        expect(document.querySelector('[aria-labelledby="modal-cerrar-titulo"]')).toBeNull();
        expect(onError).toHaveBeenCalledWith(null);
    });

    it.each([0, undefined])('informa al usuario cuando falta la versión del turno (%s)', async (version) => {
        montar({ persona: { ...persona, atencion_actual: { ...persona.atencion_actual, turno_version: version } } });
        await pulsar('Terminar atención');
        await pulsar('Confirmar cierre');

        expect(axios.post).not.toHaveBeenCalled();
        expect(onError).toHaveBeenCalledWith('No se pudo obtener el estado de la atención. Actualiza la operación e intenta de nuevo.');
        expect(document.querySelector('[aria-labelledby="modal-cerrar-titulo"]')).not.toBeNull();
    });

    it('confirmar reatención envía el turno y el vendedor seleccionados', async () => {
        montar({
            persona: { ...persona, estado_vendedor: 'disponible', recibe_turnos: true, atencion_actual: null },
            puedeAsignarReatencion: true,
            reatenciones: [{ id: 12, version: 8, folio: 'V-12', cliente_nombre: 'Cliente', candidatos: [{ id: 4 }] }],
        });
        await pulsar('Reatención');
        await pulsar('Confirmar reatención');

        expect(axios.post).toHaveBeenCalledWith('punto_venta.turnos.asignar_reatencion/12', {
            version: 8,
            destino_user_id: 4,
            idempotency_key: 'pdv:reatencion:12:4',
        });
        expect(onActualizado).toHaveBeenCalledOnce();
    });

    it('habilita reatención en espera si la persona es candidata', async () => {
        montar({
            persona: { ...persona, estado_vendedor: 'en_espera', recibe_turnos: false, atencion_actual: null },
            puedeAsignarReatencion: true,
            reatenciones: [{ id: 12, version: 8, folio: 'V-12', cliente_nombre: 'Cliente', candidatos: [{ id: 4 }] }],
        });

        const boton = [...document.querySelectorAll('button')]
            .find((elemento) => elemento.textContent.trim() === 'Reatención');
        expect(boton.disabled).toBe(false);
        expect(boton.title).toBe('Asignar un cliente en re-atención');
    });

    it('mantiene reatención bloqueada en espera si la persona no es candidata', () => {
        montar({
            persona: { ...persona, estado_vendedor: 'en_espera', recibe_turnos: false, atencion_actual: null },
            puedeAsignarReatencion: true,
            reatenciones: [{ id: 12, version: 8, folio: 'V-12', cliente_nombre: 'Cliente', candidatos: [{ id: 9 }] }],
        });

        const boton = [...document.querySelectorAll('button')]
            .find((elemento) => elemento.textContent.trim() === 'Reatención');
        expect(boton.disabled).toBe(true);
        expect(boton.title).toBe('No hay clientes en ventana de re-atención');
    });
});
