// @vitest-environment happy-dom
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import Ventas from './Ventas';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { visit: vi.fn(), reload: vi.fn() },
    usePage: () => ({ props: { auth: { user: { id: 7 } }, flash: {} } }),
}));

vi.mock('../../../Layouts/AppLayout', () => ({
    default: ({ children }) => <div data-app-layout="true">{children}</div>,
}));

vi.mock('../../../Components/PuntoVenta/PdvAlertProvider', () => ({
    default: ({ children }) => <>{children}</>,
    usePdvAlertContext: () => ({ terminalGeneral: { terminalActiva: false, terminalId: null } }),
    usePdvAlertReload: () => {},
}));

vi.mock('@/hooks/useGeliaDeployWatch', () => ({
    default: () => {},
}));

vi.mock('axios', () => ({
    default: {
        get: vi.fn(() => new Promise(() => {})),
        post: vi.fn(),
        isCancel: () => false,
    },
}));

function montar(props) {
    const contenedor = document.createElement('div');
    document.body.appendChild(contenedor);
    const root = createRoot(contenedor);
    act(() => {
        root.render(<Ventas {...props} />);
    });
    return { contenedor, root };
}

const propsBase = {
    auth: { user: { id: 7, name: 'Vendedor Prueba' } },
    permisos: { atender: true },
    sucursal_activa: { id: 3, nombre: 'Sucursal Centro' },
    sucursales_asignadas: [{ id: 3, nombre: 'Sucursal Centro' }],
    catalogos: {},
};

describe('Vista Mi atención', () => {
    beforeEach(() => {
        globalThis.route = vi.fn((name) => `/ruta/${name}`);
    });

    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('muestra el módulo cuando la persona está disponible y sin turno', () => {
        const { contenedor, root } = montar({
            ...propsBase,
            tablero: {
                turno_asignado: null,
                estado_vendedor: 'disponible',
                servidor_at: '2026-11-02T16:00:00Z',
            },
        });

        expect(contenedor.textContent).toContain('Mi atención');
        expect(contenedor.textContent).toContain('Sin turno asignado');
        expect(contenedor.querySelector('[data-ventas-tablero-root]')).not.toBeNull();

        act(() => {
            root.unmount();
        });
    });
});
