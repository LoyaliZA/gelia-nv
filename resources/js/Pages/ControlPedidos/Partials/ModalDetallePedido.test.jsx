// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, expect, it, vi } from 'vitest';
import ModalDetallePedido from './ModalDetallePedido';
const usuario = vi.hoisted(() => ({ id: 1, permissions: [], roles: [] }));
vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: { auth: { user: usuario } } }) }));
vi.mock('./ModalCambiarDireccion', () => ({
    default: ({ abierto }) => abierto && <div>Cambiar dirección de envío</div>,
}));
vi.mock('./VisorPdfPaginas', () => ({ default: ({ url }) => <output>{url}</output> }));
vi.mock('./ConsultaSolicitudTienda', () => ({ default: () => <div>Consulta vigente</div> }));
vi.mock('./ReferenciaPedidoCompartido', () => ({ default: () => <div>Pedido compartido</div> }));
vi.mock('./SeccionRevisionFisicaPedido', () => ({ default: () => <div>Revisión física</div> }));
let root, container;
afterEach(async () => {
    if (root) await act(() => root.unmount());
    container?.remove();
    usuario.permissions = [];
    usuario.roles = [];
    vi.restoreAllMocks();
    vi.useRealTimers();
});

function montar(pedido) {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = name => name;
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
    return act(() => root.render(<ModalDetallePedido abierto pedido={pedido} />));
}

it('muestra los datos de recolección y conserva los campos logísticos solo para pedidos anteriores', async () => {
    const pedido = {
        id: 1, folio: 'EJEMPLO', cliente: { nombre: 'Cliente' }, documentos: [],
        solicitud_preparacion: { modalidad_entrega: 'tienda', area_revision: 'control_pedidos', cobertura_tienda: { tipo: 'transferencia' } },
        sucursal_destino: { nombre: 'Carmen Cadena' },
    };
    await montar(pedido);
    expect(document.body.textContent).toContain('Recolección en tienda');
    expect(document.body.textContent).toContain('Carmen Cadena');
    expect(document.body.textContent).toContain('Transferencia');
    expect(document.body.querySelector('.gelia-pedidos-navegacion')).toBeNull();
    for (const label of ['Peso real', 'Paquetería', 'Entrega y dirección', 'Reexpedición', 'Tipo caja']) {
        expect(document.body.textContent).not.toContain(label);
    }
    await act(() => root.render(<ModalDetallePedido abierto pedido={{ ...pedido, solicitud_preparacion: null }} />));
    expect(document.body.textContent).toContain('Peso real');
    expect(document.body.textContent).toContain('Entrega y dirección');
});

it('en un envío municipal muestra consignatario, remisión y terminal del snapshot', async () => {
    const pedido = {
        id: 26560, folio: '26560', numero_remision: 'WIZ-26560', paqueteria: { nombre: 'Jaguar' },
        cliente: { nombre: 'Cliente', numero_cliente: 'C-1' }, documentos: [],
        solicitud_preparacion: {
            modalidad_entrega: 'municipal',
            consulta_municipal: { area: 'tienda', estado: 'cerrada' },
            expediente: { nombre: 'Persona receptora', telefono: '9931112233' },
        },
    };
    await montar(pedido);
    expect(document.body.textContent).toContain('Persona receptora');
    expect(document.body.textContent).toContain('9931112233');
    expect(document.body.textContent).toContain('WIZ-26560');
    expect(document.body.textContent).toContain('Jaguar');
    expect(document.body.textContent).toContain('Envío municipal');
    expect(document.body.querySelector('.gelia-pedidos-navegacion')).toBeNull();
    for (const label of ['Peso real', 'Tipo caja', 'Entrega y dirección', 'Reexpedición', 'Peso cobrado', 'Apartado CEDIS', 'C.P.']) {
        expect(document.body.textContent).not.toContain(label);
    }
});

it('en un envío nacional omite terminal municipal y campos vacíos de logística', async () => {
    const pedido = {
        id: 9, folio: 'N-9', cliente: { nombre: 'Cliente' }, documentos: [],
        solicitud_preparacion: { modalidad_entrega: 'nacional', captura_nacional: 'compartida' },
        paqueteria: { nombre: 'DHL' },
        total_a_cobrar: 1200,
    };
    await montar(pedido);
    expect(document.body.textContent).toContain('Envío nacional');
    expect(document.body.textContent).toContain('DHL');
    expect(document.body.textContent).toContain('Entrega y dirección');
    expect(document.body.textContent).not.toContain('Terminal destino');
    expect(document.body.textContent).not.toContain('Consignatario');
    expect(document.body.textContent).not.toContain('Tipo caja');
    expect(document.body.textContent).not.toContain('Peso real');
    expect(document.body.querySelector('.gelia-pedidos-navegacion')).toBeNull();
});

const nacionalSinSeguro = {
    id: 42, folio: 'NAC-42', aplica_seguro: false, numero_rastreo: '6523',
    guia_subida_at: '2026-11-26T20:40:24Z',
    estatus: { fase_ciclo: 'ENVIADO' },
    solicitud_preparacion: { modalidad_entrega: 'nacional', captura_nacional: 'compartida' },
    cajas: [
        { id: 202, orden: 3, estatus_recoleccion: 'recolectada', tipo_caja: { nombre: 'Caja #32' } },
        { id: 28, orden: 1, estatus_recoleccion: 'recolectada', tipo_caja: { nombre: 'Caja #21' } },
        { id: 185, orden: 2, estatus_recoleccion: 'recolectada', tipoCaja: { nombre: 'Caja #85' } },
        { id: 303, orden: 4 },
        { id: 404, orden: 5, estado_operativo: 'retirada' },
    ],
};

function guiaDeCaja(id, caja, atributos = {}) {
    return {
        id, tipo: 'guia', activo: true, relacion_tipo: 'guia_caja_confirmada',
        pedido_bma_caja_id: caja, url: `/guias/${id}`, nombre_original: `guia-${id}.pdf`,
        ...atributos,
    };
}

it('agrupa los PDF confirmados por caja, conserva el rastreo y abre el visor de la fila elegida', async () => {
    const copiar = vi.spyOn(navigator.clipboard, 'writeText').mockResolvedValue(undefined);
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
    await montar({
        ...nacionalSinSeguro,
        documentos: [
            guiaDeCaja(1, 28, { activo: false }), guiaDeCaja(2, 202),
            guiaDeCaja(3, 303, { relacion_tipo: 'guia_caja_avance' }),
            guiaDeCaja(4, '185'), guiaDeCaja(5, 28), guiaDeCaja(6, 404), guiaDeCaja(7, 999),
        ],
    });
    const entrega = document.querySelector('#detalle-envio');
    const desplegable = entrega.querySelector('details');
    const encabezado = desplegable.querySelector('summary');
    expect(desplegable.open).toBe(false);
    expect(encabezado.textContent).toContain('3 disponibles');
    expect(entrega.textContent).toContain('6523');
    expect(entrega.textContent).toContain('Liberado:');
    expect(entrega.textContent).not.toContain('Ver / Descargar guía');

    await act(() => [...entrega.querySelectorAll('button')].find((b) => b.textContent === 'Copiar').click());
    expect(copiar).toHaveBeenCalledWith('6523');
    await act(() => vi.advanceTimersByTime(2000));

    await act(() => encabezado.click());
    expect(desplegable.open).toBe(true);
    const lista = desplegable.querySelector('[aria-label="Guías disponibles por caja"]');
    expect(lista.classList.contains('overflow-y-auto')).toBe(true);
    expect([...lista.querySelectorAll('li')].map((fila) => [...fila.querySelectorAll('p')].map((p) => p.textContent))).toEqual([
        ['Envío 1', 'Caja #21'], ['Envío 2', 'Caja #85'], ['Envío 3', 'Caja #32'],
    ]);
    const descargas = [...lista.querySelectorAll('a[download]')];
    expect(descargas.map((a) => [a.getAttribute('href'), a.getAttribute('download')])).toEqual([
        ['/guias/5', 'guia-5.pdf'], ['/guias/4', 'guia-4.pdf'], ['/guias/2', 'guia-2.pdf'],
    ]);
    for (const enlace of descargas) {
        expect(enlace.classList.contains('size-11')).toBe(true);
        expect(enlace.classList.contains('theme-btn-secondary')).toBe(false);
        expect(enlace.classList.contains('theme-btn-primary--compact')).toBe(false);
        const icono = enlace.querySelector('svg.lucide-download');
        expect(icono?.getAttribute('width')).toBe('16');
        expect(icono?.getAttribute('height')).toBe('16');
    }
    for (const [indice, nombreCaja] of ['Caja #21', 'Caja #85', 'Caja #32'].entries()) {
        const envio = indice + 1;
        const enlace = descargas[indice];
        expect(enlace.getAttribute('aria-label')).toBe(`Descargar guía del envío ${envio}, ${nombreCaja}`);
        const ver = lista.querySelector(`button[aria-label="Ver guía del envío ${envio}, ${nombreCaja}"]`);
        await act(() => ver.click());
        const visor = document.querySelector('[aria-label="Visor de evidencia"]');
        expect(visor.querySelector('output').textContent).toBe(enlace.getAttribute('href'));
        await act(() => {
            visor.querySelector('button[aria-label="Cerrar vista de la foto"]').click();
            vi.advanceTimersByTime(50);
        });
    }
});

it('omite el desplegable cuando solo hay avances o PDF invalidados', async () => {
    await montar({
        ...nacionalSinSeguro,
        documentos: [guiaDeCaja(1, 28, { activo: false }), guiaDeCaja(2, 185, { relacion_tipo: 'guia_caja_avance' })],
    });
    const entrega = document.querySelector('#detalle-envio');
    expect(entrega.textContent).toContain('6523');
    expect(entrega.querySelector('details')).toBeNull();
    expect(entrega.textContent).not.toContain('Ver / Descargar guía');
});

it.each([
    ['nacional con seguro', { aplica_seguro: true }],
    ['nacional con guía del cliente', { cliente_proporciona_guia: true }],
    ['pedido legado', { solicitud_preparacion: null }],
])('conserva la acción de guía única en %s', async (_caso, atributos) => {
    await montar({ ...nacionalSinSeguro, ...atributos, documentos: [guiaDeCaja(1, 28, { relacion_tipo: null })] });
    const entrega = document.querySelector('#detalle-envio');
    expect(entrega.querySelector('details')).toBeNull();
    expect(entrega.textContent).toContain('Ver / Descargar guía');
});

const botonCambiar = () => [...document.querySelectorAll('#detalle-envio button')]
    .find((boton) => boton.textContent.trim() === 'Cambiar');

it.each(['ENVIADO', 'ENTREGADO'])('oculta Cambiar en nacional %s aunque exista el permiso', async (fase) => {
    usuario.permissions = ['control_pedidos.direccion.cambiar'];
    await montar({ ...nacionalSinSeguro, estatus: { fase_ciclo: fase } });
    expect(botonCambiar()).toBeUndefined();
});

it('mantiene el permiso y la apertura del formulario antes del despacho nacional', async () => {
    const pedido = { ...nacionalSinSeguro, estatus: { fase_ciclo: 'PENDIENTE_DE_ENVIO' } };
    await montar(pedido);
    expect(botonCambiar()).toBeUndefined();
    usuario.permissions = ['control_pedidos.direccion.cambiar'];
    await act(() => root.render(<ModalDetallePedido abierto pedido={pedido} />));
    await act(() => botonCambiar().click());
    expect(document.body.textContent).toContain('Cambiar dirección de envío');
});

it('mantiene la visibilidad previa de Cambiar en el detalle legado', async () => {
    usuario.roles = ['Super Admin'];
    await montar({ ...nacionalSinSeguro, solicitud_preparacion: null });
    expect(botonCambiar()).toBeDefined();
});
