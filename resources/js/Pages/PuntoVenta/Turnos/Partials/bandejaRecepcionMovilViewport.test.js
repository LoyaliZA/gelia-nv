// @vitest-environment happy-dom
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import BandejaColaRecepcionTurno from './BandejaColaRecepcionTurno';
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
        post: vi.fn(),
        isCancel: () => false,
    },
}));

const catalogosBase = {
    servicio: 'Ventas',
    estados: { EN_COLA: 'En cola', ASIGNADO: 'Asignado' },
    motivos_baja: [
        { valor: 'se_fue', etiqueta: 'Se fue' },
        { valor: 'desistio', etiqueta: 'Desistió' },
        { valor: 'otro', etiqueta: 'Otro' },
    ],
};

const bandejaBase = {
    servidor_at: new Date().toISOString(),
    en_cola: [{
        id: 1,
        folio: 'V-001',
        estado: 'EN_COLA',
        snapshot_nombre_llamado: 'Visitante Móvil',
        version: 1,
        puede_baja_cola: true,
        prioridad_vip: false,
        prioridad_diamante: false,
        prioridad_adulto_mayor: false,
        prioridad_discapacidad: false,
    }],
    asignados: [],
};

function montar(ui) {
    const contenedor = document.createElement('div');
    contenedor.setAttribute('data-bandeja-cola-root', 'true');
    document.body.appendChild(contenedor);
    const root = createRoot(contenedor);
    act(() => {
        root.render(ui);
    });
    return contenedor;
}

describe('bandeja cola recepcion viewport móvil', () => {
    beforeEach(() => {
        configurarViewportMovil();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('mantiene acciones críticas táctiles y sin overflow horizontal', () => {
        const contenedor = montar(createElement(BandejaColaRecepcionTurno, {
            bandeja: bandejaBase,
            permisos: { ver: true, baja_cola: true },
            catalogos: catalogosBase,
        }));

        expect(contenedor.offsetWidth).toBeLessThanOrEqual(MOBILE_VIEWPORT_WIDTH + 1);
        assertSinOverflowHorizontal(contenedor);

        const baja = buscarControlPorTexto(contenedor, 'Dar de baja');
        assertTargetTactil(baja, 'Dar de baja');
        expect(elementoEsOperable(baja)).toBe(true);

        const actualizar = buscarControlPorTexto(contenedor, 'Actualizar');
        assertTargetTactil(actualizar, 'Actualizar');
    });

    it('oculta baja cuando no hay permiso baja_cola', () => {
        const contenedor = montar(createElement(BandejaColaRecepcionTurno, {
            bandeja: bandejaBase,
            permisos: { ver: true, baja_cola: false },
            catalogos: catalogosBase,
        }));

        expect(contenedor.textContent).not.toContain('Dar de baja');
    });
});
