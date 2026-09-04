// @vitest-environment happy-dom
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import BusquedaRapidaRecepcion from './BusquedaRapidaRecepcion';
import FormularioRecepcionFisica from './FormularioRecepcionFisica';
import {
    assertSinOverflowHorizontal,
    assertTargetTactil,
    buscarControlPorTexto,
    buscarPorAriaLabel,
    configurarViewportMovil,
    elementoEsOperable,
    MOBILE_VIEWPORT_WIDTH,
} from './recepcionMovilViewport';

vi.mock('@inertiajs/react', () => ({
    router: { visit: vi.fn() },
}));

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
    },
}));

const catalogosBase = {
    tipos_bulto: { caja: 'Caja', sobre: 'Sobre' },
    condiciones_bulto: { bueno: 'Bueno', danado: 'Dañado' },
    estados: { pendiente_recepcion: 'Pendiente de recepción' },
};

const resguardoTotal = {
    id: 42,
    version: 1,
    estado: 'pendiente_recepcion',
    snapshot_folio: 'REM-MOV-001',
    referencia_cliente: 'Cliente piloto',
    cantidad_bultos_esperada: 1,
    cantidad_bultos_recibida: 0,
    cantidad_bultos_pendiente: 1,
    recepcion_completa: false,
    bultos_recibidos: [],
};

const resguardoParcial = {
    ...resguardoTotal,
    cantidad_bultos_recibida: 1,
    cantidad_bultos_pendiente: 1,
    bultos_recibidos: [{
        id: 9,
        folio: 'CJA-001',
        tipo: 'caja',
        recepcion_at: '2026-09-02T10:00:00Z',
    }],
};

const almacenes = [{ id: 3, codigo: 'PISO-1', nombre: 'Piso recepción' }];

function habilitarEnvioFormulario(contenedor) {
    const formulario = contenedor.querySelector('form');
    if (formulario) {
        formulario.noValidate = true;
    }
}

function renderEnViewport(componente) {
    const contenedor = document.createElement('div');
    contenedor.setAttribute('data-recepcion-movil-root', '1');
    contenedor.style.width = `${MOBILE_VIEWPORT_WIDTH}px`;
    contenedor.style.maxWidth = `${MOBILE_VIEWPORT_WIDTH}px`;
    document.body.appendChild(contenedor);

    const root = createRoot(contenedor);
    act(() => {
        root.render(componente);
    });

    return {
        contenedor,
        desmontar: () => {
            act(() => root.unmount());
            contenedor.remove();
        },
    };
}

describe('recepcion móvil viewport 375px (REM-06)', () => {
    let montajes = [];

    beforeEach(() => {
        montajes = [];
        configurarViewportMovil();
        globalThis.route = vi.fn((name, params) => `/${name}/${params ?? ''}`);
    });

    afterEach(() => {
        montajes.forEach(({ desmontar }) => desmontar());
        montajes = [];
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('expone búsqueda rápida operable en columna móvil', () => {
        const { contenedor, desmontar } = renderEnViewport(
            createElement(BusquedaRapidaRecepcion, { puedeRecibir: true }),
        );
        montajes.push({ desmontar });

        const formulario = contenedor.querySelector('form');
        expect(formulario?.className).toContain('flex-col');

        const busqueda = buscarPorAriaLabel(contenedor, 'Buscar resguardo para recepción');
        expect(elementoEsOperable(busqueda)).toBe(true);

        const escanear = buscarControlPorTexto(contenedor, 'Escanear');
        assertTargetTactil(escanear, 'Escanear');
        assertTargetTactil(buscarControlPorTexto(contenedor, 'Continuar recepción'), 'Continuar recepción');

        assertSinOverflowHorizontal(contenedor);
    });

    it('permite recepción total: bultos, evidencias y confirmación irreversible', async () => {
        const onEnviar = vi.fn().mockResolvedValue(undefined);
        const { contenedor, desmontar } = renderEnViewport(
            createElement(FormularioRecepcionFisica, {
                resguardo: resguardoTotal,
                almacenes,
                catalogos: catalogosBase,
                enviando: false,
                progreso: 0,
                error: null,
                onEnviar,
            }),
        );
        montajes.push({ desmontar });

        const almacen = contenedor.querySelector('select');
        expect(elementoEsOperable(almacen)).toBe(true);

        const folio = contenedor.querySelector('input[type="text"]');
        expect(elementoEsOperable(folio)).toBe(true);

        const tomarFoto = buscarControlPorTexto(contenedor, 'Tomar foto');
        const galeria = buscarControlPorTexto(contenedor, 'Galería');
        assertTargetTactil(tomarFoto, 'Tomar foto');
        assertTargetTactil(galeria, 'Galería');
        expect(tomarFoto?.querySelector('input[capture="environment"]')).toBeTruthy();

        const confirmar = buscarControlPorTexto(contenedor, 'Confirmar recepción física');
        assertTargetTactil(confirmar, 'Confirmar recepción física');

        habilitarEnvioFormulario(contenedor);

        await act(async () => {
            confirmar.click();
        });

        const vista = document.body.textContent;
        expect(vista).toContain('Confirmar recepción');
        expect(vista).toContain('no se puede deshacer');
        expect(buscarControlPorTexto(document.body, 'Sí, recibir resguardo')).toBeTruthy();

        assertSinOverflowHorizontal(contenedor);
    });

    it('permite llegada parcial complementaria con confirmación parcial', async () => {
        const onEnviar = vi.fn().mockResolvedValue(undefined);
        const { contenedor, desmontar } = renderEnViewport(
            createElement(FormularioRecepcionFisica, {
                resguardo: resguardoParcial,
                almacenes,
                catalogos: catalogosBase,
                enviando: false,
                progreso: 0,
                error: null,
                onEnviar,
            }),
        );
        montajes.push({ desmontar });

        expect(contenedor.textContent).toContain('Recepción en curso');
        expect(contenedor.textContent).toContain('Llegadas anteriores');

        const registrar = buscarControlPorTexto(contenedor, 'Registrar llegada complementaria');
        assertTargetTactil(registrar, 'Registrar llegada complementaria');

        habilitarEnvioFormulario(contenedor);

        await act(async () => {
            registrar.click();
        });

        const vista = document.body.textContent;
        expect(vista).toContain('Confirmar llegada final');
        expect(buscarControlPorTexto(document.body, 'Sí, completar recepción')).toBeTruthy();

        assertSinOverflowHorizontal(contenedor);
    });
});
