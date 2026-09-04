// @vitest-environment happy-dom
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import FormularioAltaTurno from './FormularioAltaTurno';
import {
    assertSinOverflowHorizontal,
    assertTargetTactil,
    buscarControlPorTexto,
    configurarViewportMovil,
    elementoEsOperable,
    MOBILE_VIEWPORT_WIDTH,
} from '../../Resguardos/Partials/recepcionMovilViewport';

vi.mock('@inertiajs/react', () => ({
    router: { visit: vi.fn() },
}));

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        isCancel: () => false,
    },
}));

const catalogosBase = { servicio: 'Ventas', estados: { EN_COLA: 'En cola', ASIGNADO: 'Asignado' } };

function montar(ui) {
    const contenedor = document.createElement('div');
    contenedor.setAttribute('data-recepcion-turno-root', 'true');
    document.body.appendChild(contenedor);
    const root = createRoot(contenedor);
    act(() => {
        root.render(ui);
    });
    return contenedor;
}

describe('recepcion turno viewport móvil', () => {
    beforeEach(() => {
        configurarViewportMovil();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('mantiene acciones críticas táctiles y sin overflow horizontal', () => {
        const contenedor = montar(createElement(FormularioAltaTurno, {
            permisos: { alta: true, marcar_prioridad: true },
            catalogos: catalogosBase,
            enviando: false,
            error: null,
            onEnviar: vi.fn(),
        }));

        expect(contenedor.offsetWidth).toBeLessThanOrEqual(MOBILE_VIEWPORT_WIDTH + 1);
        assertSinOverflowHorizontal(contenedor);

        const registrar = buscarControlPorTexto(contenedor, 'Registrar turno');
        assertTargetTactil(registrar, 'Registrar turno');
        expect(elementoEsOperable(registrar)).toBe(true);

        const visitante = buscarControlPorTexto(contenedor, 'Visitante');
        assertTargetTactil(visitante, 'Visitante');
    });

    it('oculta prioridad cuando no hay permiso marcar_prioridad', () => {
        const contenedor = montar(createElement(FormularioAltaTurno, {
            permisos: { alta: true, marcar_prioridad: false },
            catalogos: catalogosBase,
            enviando: false,
            error: null,
            onEnviar: vi.fn(),
        }));

        expect(contenedor.textContent).not.toContain('Adulto mayor');
        expect(contenedor.textContent).not.toContain('Discapacidad');
    });
});
