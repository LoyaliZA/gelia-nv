// @vitest-environment happy-dom
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import TarjetaEstadoJornada from './TarjetaEstadoJornada';
import {
    assertSinOverflowHorizontal,
    assertTargetTactil,
    buscarControlPorTexto,
    configurarViewportMovil,
    elementoEsOperable,
    MOBILE_VIEWPORT_WIDTH,
} from '../../Resguardos/Partials/recepcionMovilViewport';

vi.mock('axios', () => ({
    default: {
        post: vi.fn(),
        get: vi.fn(),
        isCancel: () => false,
    },
}));

const estadoAbierto = {
    jornada: { id: 1, estado: 'ABIERTA', version: 1, apertura_at: '2026-09-04T12:00:00Z' },
    actividad: 'disponible',
    intervalo: { tipo: 'disponible', inicio_at: '2026-09-04T12:00:00Z' },
    sucursal_dia: { acepta_altas: true, version: 1 },
};

function montar(ui) {
    const contenedor = document.createElement('div');
    contenedor.setAttribute('data-operacion-root', 'true');
    document.body.appendChild(contenedor);
    const root = createRoot(contenedor);
    act(() => {
        root.render(ui);
    });
    return contenedor;
}

describe('operación viewport móvil', () => {
    beforeEach(() => {
        configurarViewportMovil();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('mantiene acciones de jornada táctiles y sin overflow horizontal', () => {
        const contenedor = montar(createElement(TarjetaEstadoJornada, {
            estado: estadoAbierto,
            permisos: { jornada_cerrar: true, pausa: true },
            servidorAt: '2026-09-04T12:01:00Z',
            onActualizado: vi.fn(),
            onConflicto: vi.fn(),
            onError: vi.fn(),
        }));

        assertSinOverflowHorizontal(contenedor, MOBILE_VIEWPORT_WIDTH);

        const cerrar = buscarControlPorTexto(contenedor, 'Cerrar jornada');
        const pausa = buscarControlPorTexto(contenedor, 'Iniciar pausa');

        expect(elementoEsOperable(cerrar)).toBe(true);
        expect(elementoEsOperable(pausa)).toBe(true);
        assertTargetTactil(cerrar);
        assertTargetTactil(pausa);
    });
});
