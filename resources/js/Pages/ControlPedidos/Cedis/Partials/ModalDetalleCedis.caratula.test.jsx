// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, expect, it, vi } from 'vitest';
import ModalDetalleCedis from './ModalDetalleCedis';

vi.mock('@inertiajs/react', () => ({
    router: { reload: vi.fn(), post: vi.fn() },
    usePage: () => ({ props: { auth: { user: { permissions: ['control_pedidos.cedis'], roles: [] } } } }),
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
