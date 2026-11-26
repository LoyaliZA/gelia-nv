// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, expect, it, vi } from 'vitest';
import ModalDetalleCedis from './ModalDetalleCedis';

vi.mock('@inertiajs/react', () => ({
    router: { reload: vi.fn(), post: vi.fn() },
    usePage: () => ({ props: { auth: { user: { permissions: ['control_pedidos.cedis', 'control_pedidos.cedis.enviar'], roles: [] } } } }),
}));
vi.mock('../../../Activos/Partials/useDispositivoCampo', () => ({ default: () => ({ esCampo: false }) }));
vi.mock('../../Partials/ConsultaSolicitudTienda', () => ({ default: () => null }));
vi.mock('../../Municipal/IntegracionFisicaConjunto', () => ({ default: () => null }));
vi.mock('../../Partials/ModalVistaPreviaDocumento', () => ({ default: () => null, MiniaturaDocumento: () => null }));
vi.mock('../../Partials/ModalConfirmarAccion', () => ({ default: () => null }));
vi.mock('./ModalMarcarEmpacadoBultos', () => ({ default: () => null }));
vi.mock('../../Partials/ModalAlertaPedido', () => ({ default: () => null }));
vi.mock('../../Partials/SeccionGuiaRastreo', () => ({ default: () => null }));
vi.mock('../../Nacional/superficieNacional', () => ({
    cambioATiendaNacional: () => false,
    integracionFisicaPendiente: () => false,
    piezasComplementoNacional: () => [],
    sucursalRecoleccionNacional: () => null,
    vinculoFisicoNacional: () => null,
}));

let root;
let host;
afterEach(async () => {
    if (root) await act(() => root.unmount());
    host?.remove();
});

async function montar(pedido) {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = (name, id) => `/${name}/${id}`;
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
    await act(() => root.render(<ModalDetalleCedis abierto pedido={pedido} onClose={() => {}} />));
}

it('en municipal muestra consignatario y botones de carátula', async () => {
    await montar({
        id: 26563,
        folio: '26563',
        estatus: { fase_ciclo: 'EN_CEDIS' },
        documentos: [],
        paqueteria: { nombre: 'Sultana Pichucalco' },
        solicitud_preparacion: { modalidad_entrega: 'municipal' },
        caratula_municipal: {
            nombre: 'Persona receptora',
            telefono: '9931112233',
            leyenda: 'PAGADO',
            tiene_consignatario: true,
            tiene_identificacion: true,
        },
    });
    expect(document.body.textContent).toContain('Persona receptora');
    expect(document.body.textContent).toContain('9931112233');
    expect(document.body.textContent).toContain('Ver carátula con identificación');
    expect(document.body.textContent).toContain('Ver carátula sin identificación');
    expect(document.body.textContent).toContain('Marcar empacado');
});

it('en un pedido que no es municipal no ofrece carátula de consignatario', async () => {
    await montar({
        id: 9,
        folio: 'N-9',
        estatus: { fase_ciclo: 'EN_CEDIS' },
        documentos: [],
        solicitud_preparacion: { modalidad_entrega: 'nacional' },
        direccion_vigente: { nombre_destinatario: 'Quien recibe', municipio: 'Centro' },
    });
    expect(document.body.textContent).not.toContain('Ver carátula con identificación');
    expect(document.body.textContent).not.toContain('Ver carátula sin identificación');
    expect(document.body.textContent).not.toContain('Consignatario');
});

it('refleja las guías confirmadas al actualizar el mismo detalle y conserva la selección de caja', async () => {
    const pedido = {
        id: 42,
        folio: 'NAC-42',
        numero_rastreo: '7845210030',
        guias_pdf_por_caja: true,
        faltan_guias_por_caja: 1,
        estatus: { fase_ciclo: 'PENDIENTE_DE_ENVIO' },
        solicitud_preparacion: { modalidad_entrega: 'nacional' },
        cajas: [1, 2].map((id) => ({ id, orden: id, estatus_recoleccion: 'pendiente' })),
        documentos: [],
    };
    await montar(pedido);
    const caja = () => document.querySelector('input[aria-label="Recolectar envío 1"]');
    const enviar = () => [...document.querySelectorAll('button')].find((b) => b.textContent.includes('Marcar recolectadas'));
    await act(() => caja().click());
    expect(caja().checked).toBe(true);
    expect(enviar().disabled).toBe(true);
    expect(document.querySelector('button[aria-label="Ver PDF de la caja 1"]')).toBeNull();

    const confirmado = {
        ...pedido,
        faltan_guias_por_caja: 0,
        documentos: [1, 2].map((id) => ({
            id, pedido_bma_caja_id: id, tipo: 'guia', activo: true,
            relacion_tipo: 'guia_caja_confirmada', url: `/guia/${id}`,
        })),
    };
    await act(() => root.render(<ModalDetalleCedis abierto pedido={confirmado} onClose={() => {}} />));
    expect(caja().checked).toBe(true);
    expect(enviar().disabled).toBe(false);
    expect(document.querySelector('button[aria-label="Ver PDF de la caja 1"]')).not.toBeNull();
    expect(document.querySelector('button[aria-label="Ver PDF de la caja 2"]')).not.toBeNull();
    expect(document.querySelector('input[placeholder*="Guía de este envío"]')).toBeNull();
});
