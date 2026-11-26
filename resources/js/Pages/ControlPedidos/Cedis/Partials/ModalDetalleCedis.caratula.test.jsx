// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/react';
import ModalDetalleCedis from './ModalDetalleCedis';
import TarjetasCedis from './TarjetasCedis';

vi.mock('@inertiajs/react', () => ({
    router: { reload: vi.fn(), post: vi.fn() },
    usePage: () => ({ props: { auth: { user: { permissions: ['control_pedidos.cedis', 'control_pedidos.cedis.enviar'], roles: [] } } } }),
}));
vi.mock('../../../Activos/Partials/useDispositivoCampo', () => ({ default: () => ({ esCampo: false }) }));
vi.mock('../../Partials/ConsultaSolicitudTienda', () => ({ default: () => null }));
vi.mock('../../Municipal/IntegracionFisicaConjunto', () => ({ default: () => null }));
vi.mock('../../Partials/ModalVistaPreviaDocumento', () => ({
    default: ({ abierto, documento, documentos, onClose }) => abierto && (
        <>
            <output>{documentos?.map((doc) => doc.url).join(',') ?? documento?.url}</output>
            <button type="button" onClick={onClose}>Cerrar vista previa</button>
        </>
    ),
    MiniaturaDocumento: ({ documento, onVer }) => (
        <button type="button" onClick={() => onVer(documento)}>{documento.nombre_original}</button>
    ),
}));
vi.mock('../../Partials/ModalConfirmarAccion', () => ({
    default: ({ abierto, mensaje, etiquetaConfirmar, onConfirm }) => abierto && (
        <div data-testid="confirmacion">
            <p>{mensaje}</p>
            <button onClick={onConfirm}>{etiquetaConfirmar}</button>
        </div>
    ),
}));
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
    vi.clearAllMocks();
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

it('confirma todas las pendientes al recibir las guías y conserva el detalle de cada caja', async () => {
    const pedido = {
        id: 42,
        folio: 'NAC-42',
        numero_rastreo: '7845210030',
        guias_pdf_por_caja: true,
        faltan_guias_por_caja: 1,
        estatus: { fase_ciclo: 'PENDIENTE_DE_ENVIO' },
        solicitud_preparacion: { modalidad_entrega: 'nacional' },
        cajas: [1, 2].map((id) => ({
            id, orden: id, estatus_recoleccion: 'pendiente',
            largo: 31, ancho: 23, alto: 14, peso_volumetrico_kg: 1.9964, peso_real_kg: 2, peso_cobrado_kg: 3,
        })),
        documentos: [
            ...[8, 9, 10].map((id) => ({
                id, relacion_id: id === 10 ? 2 : 1, relacion_tipo: 'envio_caja', tipo: 'evidencia_condicion',
                nombre_original: `Evidencia ${id}`, url: `/evidencia/${id}`,
            })),
            { id: 11, pedido_bma_caja_id: 1, tipo: 'guia', activo: false, relacion_tipo: 'guia_caja_confirmada', url: '/guia/anterior' },
            { id: 12, pedido_bma_caja_id: 1, tipo: 'guia', activo: true, relacion_tipo: 'guia_caja_avance', url: '/guia/avance' },
        ],
    };
    await montar(pedido);
    const selectorCaja = 'input[aria-label^="Recolectar envío"]';
    const enviar = () => [...document.querySelectorAll('button')].find((b) => b.textContent.includes('Marcar enviado'));
    expect(document.querySelectorAll(selectorCaja)).toHaveLength(0);
    const envios = document.querySelector('[aria-label="Envíos del pesaje"]');
    expect(envios.textContent).not.toContain('Evidencia 8');
    expect(envios.textContent).toContain('31×23×14 cm · Vol. 1.9964 kg · Real 2 kg · Cobrado 3 kg');
    expect(document.body.textContent).toContain('0/2 recolectadas');
    expect(enviar().disabled).toBe(true);
    const verGuia = (numero) => envios.querySelector(`button[aria-label="Ver guía PDF del envío ${numero}"]`);
    const cerrarVistaPrevia = () => act(() => [...document.querySelectorAll('button')].find((b) => b.textContent === 'Cerrar vista previa').click());
    expect(verGuia(1).disabled).toBe(true);
    expect(envios.querySelector('button[aria-label="Envío 1 sin guía PDF"]').disabled).toBe(true);
    expect(envios.querySelectorAll('a[download]')).toHaveLength(0);
    await act(() => [...document.querySelectorAll('button')].find((b) => b.textContent === 'Evidencia 8').click());
    expect(document.querySelector('output').textContent).toBe('/evidencia/8');
    await cerrarVistaPrevia();

    const confirmado = {
        ...pedido,
        faltan_guias_por_caja: 0,
        documentos: [...pedido.documentos, ...[1, 2].map((id) => ({
            id, pedido_bma_caja_id: id, tipo: 'guia', activo: true,
            relacion_tipo: 'guia_caja_confirmada', url: `/guia/${id}`, nombre_original: `guia-${id}.pdf`,
        }))],
    };
    await act(() => root.render(<ModalDetalleCedis abierto pedido={confirmado} onClose={() => {}} />));
    expect(document.querySelectorAll(selectorCaja)).toHaveLength(0);
    expect(enviar().disabled).toBe(false);
    const descargas = [...envios.querySelectorAll('a[aria-label^="Descargar guía del envío"]')];
    expect(descargas.map((a) => [a.getAttribute('href'), a.getAttribute('download')])).toEqual([
        ['/guia/1', 'guia-1.pdf'],
        ['/guia/2', 'guia-2.pdf'],
    ]);
    for (const [indice, descarga] of descargas.entries()) {
        await act(() => verGuia(indice + 1).click());
        expect(document.querySelector('output').textContent).toBe(descarga.getAttribute('href'));
        await cerrarVistaPrevia();
    }
    expect(document.querySelector('input[placeholder*="Guía de este envío"]')).toBeNull();
    await act(() => enviar().click());
    const confirmacion = document.querySelector('[data-testid="confirmacion"]');
    expect(confirmacion.textContent).toContain('2 cajas pendientes');
    expect(router.post).not.toHaveBeenCalled();
    await act(() => confirmacion.querySelector('button').click());
    expect(router.post).toHaveBeenCalledExactlyOnceWith(
        '/control_pedidos.cedis.marcar_enviado/42',
        { todas_las_cajas_pendientes: true },
        expect.any(Object),
    );
});

it('mantiene casillas y confirmación parcial para el pedido asegurado', async () => {
    await montar({
        id: 43, folio: 'NAC-43', aplica_seguro: true, numero_rastreo: 'ASEGURADO-1',
        estatus: { fase_ciclo: 'PENDIENTE_DE_ENVIO' },
        solicitud_preparacion: { modalidad_entrega: 'nacional' },
        cajas: [1, 2].map((id) => ({ id, orden: id, estatus_recoleccion: 'pendiente' })),
        documentos: [{ id: 8, relacion_id: 1, relacion_tipo: 'envio_caja', tipo: 'evidencia_condicion', url: '/evidencia/8' }],
    });
    const cajas = document.querySelectorAll('input[aria-label^="Recolectar envío"]');
    expect(cajas).toHaveLength(2);
    await act(() => document.querySelector('button[aria-label="Ver evidencia del envío 1"]').click());
    expect(document.querySelector('output').textContent).toBe('/evidencia/8');
    await act(() => [...document.querySelectorAll('button')].find((b) => b.textContent === 'Cerrar vista previa').click());
    await act(() => cajas[0].click());
    await act(() => [...document.querySelectorAll('button')].find((b) => b.textContent.includes('Marcar recolectadas')).click());
    const confirmacion = document.querySelector('[data-testid="confirmacion"]');
    expect(confirmacion.textContent).toContain('1 de 2 envíos pendientes');
    await act(() => confirmacion.querySelector('button').click());
    expect(router.post).toHaveBeenCalledExactlyOnceWith(
        '/control_pedidos.cedis.marcar_enviado/43', { cajas: [{ id: 1 }] }, expect.any(Object),
    );
});

it('la tarjeta sin seguro abre el detalle desde Marcar enviado y la asegurada conserva su texto', async () => {
    await montar(null);
    const base = {
        numero_rastreo: 'RASTREO-1', cajas_pendientes: 2,
        estatus: { fase_ciclo: 'PENDIENTE_DE_ENVIO' },
        solicitud_preparacion: { modalidad_entrega: 'nacional' },
    };
    const sinSeguro = { ...base, id: 42, folio: 'NAC-42', guias_pdf_por_caja: true, faltan_guias_por_caja: 0 };
    const asegurado = { ...base, id: 43, folio: 'NAC-43', aplica_seguro: true };
    const onVerDetalle = vi.fn();
    await act(() => root.render(<TarjetasCedis pedidos={{ data: [sinSeguro, asegurado] }} onVerDetalle={onVerDetalle} />));
    const botones = [...host.querySelectorAll('button')];
    await act(() => botones.find((b) => b.textContent.trim() === 'Marcar enviado').click());
    await act(() => botones.find((b) => b.textContent.trim() === 'Elegir envíos a recolectar').click());
    expect(onVerDetalle.mock.calls).toEqual([[sinSeguro], [asegurado]]);
    expect(router.post).not.toHaveBeenCalled();
});
