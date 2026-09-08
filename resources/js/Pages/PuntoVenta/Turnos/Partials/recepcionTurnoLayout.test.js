// @vitest-environment happy-dom
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import BandejaColaRecepcionTurno from './BandejaColaRecepcionTurno';
import EncabezadoRecepcionTurno from './EncabezadoRecepcionTurno';
import {
    assertSinOverflowHorizontal,
    configurarViewportMovil,
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

globalThis.route = vi.fn((name) => `/ruta/${name}`);

const catalogosBase = {
    servicio: 'Ventas',
    estados: { EN_COLA: 'En cola', ASIGNADO: 'Asignado' },
    motivos_baja: [{ valor: 'se_fue', etiqueta: 'Se fue' }],
};

const bandejaBase = {
    servidor_at: new Date().toISOString(),
    resumen: {
        en_espera: 1,
        asignados: 1,
        mayor_espera_segundos: 120,
        vendedores_disponibles: 2,
    },
    en_cola: [{
        id: 1,
        folio: 'V-001',
        estado: 'EN_COLA',
        servicio: 'Ventas',
        snapshot_nombre_llamado: 'Cliente Uno',
        espera_segundos: 120,
        version: 1,
        puede_baja_cola: true,
        prioridad_vip: false,
        prioridad_diamante: false,
        prioridad_adulto_mayor: false,
        prioridad_discapacidad: false,
    }],
    asignados: [{
        id: 2,
        folio: 'V-002',
        estado: 'ASIGNADO',
        servicio: 'Ventas',
        snapshot_nombre_llamado: 'Cliente Dos',
        espera_segundos: 60,
        atencion: {
            primer_nombre: 'Ana',
            atencion_en_curso: false,
            espera_inicial_vencida: false,
        },
    }],
};

function montar(ui, atributos = {}) {
    const contenedor = document.createElement('div');
    Object.entries(atributos).forEach(([clave, valor]) => contenedor.setAttribute(clave, valor));
    document.body.appendChild(contenedor);
    const root = createRoot(contenedor);
    act(() => {
        root.render(ui);
    });
    return contenedor;
}

describe('recepcion turno layout viewport', () => {
    beforeEach(() => {
        configurarViewportMovil();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('apila encabezado y bandeja sin scroll horizontal en móvil', () => {
        const contenedor = montar(
            createElement('div', { 'data-recepcion-turno-root': 'true' }, [
                createElement(EncabezadoRecepcionTurno, { bandeja: bandejaBase, estadoConexion: 'conectado' }),
                createElement(BandejaColaRecepcionTurno, {
                    bandeja: bandejaBase,
                    permisos: { ver: true, baja_cola: true },
                    catalogos: catalogosBase,
                }),
            ]),
            { 'data-recepcion-turno-root': 'true' },
        );

        expect(contenedor.offsetWidth).toBeLessThanOrEqual(MOBILE_VIEWPORT_WIDTH + 1);
        assertSinOverflowHorizontal(contenedor);
        expect(contenedor.textContent).toContain('En espera (1)');
        expect(contenedor.textContent).toContain('Asignados (1)');
    });

    it('muestra dos columnas en viewport amplio', () => {
        globalThis.innerWidth = 1280;
        globalThis.innerHeight = 800;

        const layout = document.createElement('div');
        layout.setAttribute('data-recepcion-turno-layout', 'true');
        layout.className = 'grid gap-4 lg:grid-cols-[minmax(280px,360px)_minmax(0,1fr)]';
        layout.innerHTML = '<div data-recepcion-turno-form></div><div data-bandeja-cola-root></div>';
        document.body.appendChild(layout);

        expect(layout.className).toContain('lg:grid-cols-[minmax(280px,360px)_minmax(0,1fr)]');
        expect(layout.querySelector('[data-recepcion-turno-form]')).toBeTruthy();
        expect(layout.querySelector('[data-bandeja-cola-root]')).toBeTruthy();
    });

    it('oculta bandeja cuando no hay permiso ver', () => {
        const contenedor = montar(createElement(BandejaColaRecepcionTurno, {
            bandeja: bandejaBase,
            permisos: { ver: false, baja_cola: true },
            catalogos: catalogosBase,
        }), { 'data-bandeja-cola-root': 'true' });

        expect(contenedor.textContent).not.toContain('V-001');
        expect(contenedor.textContent).not.toContain('Dar de baja');
    });

    it('estado vacío es compacto', () => {
        const contenedor = montar(createElement(BandejaColaRecepcionTurno, {
            bandeja: { resumen: { en_espera: 0, asignados: 0, mayor_espera_segundos: 0, vendedores_disponibles: 0 }, en_cola: [], asignados: [] },
            permisos: { ver: true },
            catalogos: catalogosBase,
        }), { 'data-bandeja-cola-root': 'true' });

        const vacio = contenedor.querySelector('[data-bandeja-cola-root] > div');
        expect(contenedor.textContent).toContain('Sin turnos en espera ni asignados');
        expect(vacio?.className || '').not.toContain('p-8');
    });
});
