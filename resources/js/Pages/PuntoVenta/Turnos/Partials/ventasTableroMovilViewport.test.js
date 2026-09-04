// @vitest-environment happy-dom
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import TarjetaTurnoVentas from './TarjetaTurnoVentas';
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

const turnoBase = {
    id: 1,
    folio: 'V-001',
    version: 1,
    snapshot_nombre_llamado: 'Cliente Prueba',
    prioridad_vip: true,
    atencion: {
        id: 10,
        user_id: 1,
        atencion_en_curso: false,
        espera_inicial_vencida: false,
        espera_inicial_expira_at: '2026-09-04T12:05:00Z',
        fin_at: null,
        es_transferencia: false,
    },
};

const catalogos = {
    motivos_cierre: [
        { valor: 'venta', etiqueta: 'Venta' },
        { valor: 'no_se_presento', etiqueta: 'No se presentó' },
    ],
};

function montar(ui) {
    const contenedor = document.createElement('div');
    contenedor.setAttribute('data-ventas-tablero-root', 'true');
    document.body.appendChild(contenedor);
    const root = createRoot(contenedor);
    act(() => {
        root.render(ui);
    });
    return contenedor;
}

describe('tablero ventas viewport móvil', () => {
    beforeEach(() => {
        configurarViewportMovil();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('mantiene acciones críticas táctiles y sin overflow horizontal', () => {
        const contenedor = montar(createElement(TarjetaTurnoVentas, {
            turno: turnoBase,
            servidorAt: '2026-09-04T12:00:00Z',
            permisos: { cerrar_atencion: true, transferir: false },
            catalogos,
            personasTransferencia: [],
            onActualizado: vi.fn(),
            onConflicto: vi.fn(),
            onError: vi.fn(),
        }));

        expect(contenedor.offsetWidth).toBeLessThanOrEqual(MOBILE_VIEWPORT_WIDTH + 1);
        assertSinOverflowHorizontal(contenedor);

        const iniciar = buscarControlPorTexto(contenedor, 'Iniciar atención');
        assertTargetTactil(iniciar, 'Iniciar atención');
        expect(elementoEsOperable(iniciar)).toBe(true);
    });

    it('muestra etiqueta VIP en tarjeta', () => {
        const contenedor = montar(createElement(TarjetaTurnoVentas, {
            turno: turnoBase,
            servidorAt: '2026-09-04T12:00:00Z',
            permisos: { cerrar_atencion: true },
            catalogos,
            personasTransferencia: [],
            onActualizado: vi.fn(),
            onConflicto: vi.fn(),
            onError: vi.fn(),
        }));

        expect(contenedor.textContent).toContain('VIP');
    });
});
