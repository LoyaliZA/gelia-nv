// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import FormularioRegistrarNacional from './FormularioRegistrarNacional';
import { adaptadorConsultaNacional } from '../Partials/adaptadoresConsulta';

vi.mock('axios', () => ({ default: { post: vi.fn(), get: vi.fn() } }));
vi.mock('../../../Components/Almacenes/SelectorProducto', () => ({
    default: ({ onChange }) => <button type="button" onClick={() => onChange(1, { id: 1, sku: 'SKU-NUEVA', descripcion: 'Crema nueva' })}>Elegir perfume</button>,
}));
vi.mock('../Partials/GaleriaEvidenciasPedido', () => ({
    default: ({ onChange }) => <button type="button" onClick={() => onChange([new File(['contenido'], 'pieza.pdf', { type: 'application/pdf' })], [])}>Adjuntar captura</button>,
}));
vi.mock('./ContinuacionEnvioNacional', () => ({
    default: ({ pendiente }) => <p>{pendiente ? 'Pago de mercancía' : 'Cotización y pago'}</p>,
}));
vi.mock('../../../utils/uuidSolicitud', () => ({ uuidSolicitud: () => '11111111-1111-4111-8111-111111111111' }));

const pedido = {
    id: 9,
    folio_remision: 'WIZ-1',
    documentos: [{ id: 1, tipo: 'pdf_pedido', url: '/a.pdf' }],
    revision_registro: 1,
    base_captura: 'a'.repeat(64),
};
const catalogos = {
    paqueterias: [
        { id: 8, nombre: 'DHL', categoria: 'comercial', requiere_peso: false, sin_logistica: false, activo: true },
        { id: 11, nombre: 'Foránea', categoria: 'comercial', requiere_peso: true, sin_logistica: true, activo: true },
        { id: 12, nombre: 'FedEx', categoria: 'comercial', requiere_peso: false, activo: true },
        { id: 13, nombre: 'Estafeta', categoria: 'comercial', requiere_peso: false, activo: true },
        { id: 9, nombre: 'PAQ. PENDIENTE', categoria: 'local_regional', requiere_peso: false, sin_logistica: false, activo: true },
        { id: 2, nombre: 'Local', categoria: 'local_regional', requiere_peso: false, activo: true },
    ],
    sucursales: [{ id: 4, nombre: 'Sucursal centro' }],
};
let root;
let host;

beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
    axios.post.mockReset();
    axios.post.mockResolvedValue({ data: { ok: true, id: 9, pesaje_solicitado_at: '2026-11-20T12:00:00Z' } });
});

afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

async function render(extra = {}, catalogosPrueba = catalogos) {
    const guardar = vi.fn(async (captura) => ({ ...pedido, ...extra, catalogo_paqueteria_id: captura.catalogo_paqueteria_id, revision_registro: 2, base_captura: 'b'.repeat(64) }));
    await act(async () => {
        root.render(
            <FormularioRegistrarNacional pedido={{ ...pedido, ...extra }} catalogos={catalogosPrueba} guardar={guardar} onPedido={vi.fn()}>
                {({ contenido, panelEnvio, pie, guardarBorrador }) => <div>{panelEnvio}{contenido}{pie}<button type="button" onClick={guardarBorrador}>Guardar borrador</button></div>}
            </FormularioRegistrarNacional>,
        );
    });

    return guardar;
}

function botonEnviar() {
    return [...document.querySelectorAll('button')].find((boton) => boton.textContent.includes('Enviar consulta a CEDIS'));
}

it('mantiene el envío disponible y no consulta sin paquetería', async () => {
    const guardar = await render();
    expect(botonEnviar().disabled).toBe(false);
    expect(document.getElementById('paqueteria-nacional').textContent).toContain('DHL');
    expect(document.getElementById('paqueteria-nacional').textContent).not.toContain('Foránea');
    expect(document.getElementById('paqueteria-nacional').textContent).toContain('PAQ. PENDIENTE');
    expect(document.getElementById('paqueteria-nacional').textContent).toContain('Estafeta');
    expect(document.getElementById('paqueteria-nacional').textContent).toContain('FedEx');
    expect(document.body.textContent).not.toContain('Local');
    await act(async () => {
        botonEnviar().click();
        await Promise.resolve();
    });
    expect(document.body.textContent).toContain('Elija la paquetería o seleccione PAQ. PENDIENTE.');
    expect(guardar).not.toHaveBeenCalled();
    expect(axios.post).not.toHaveBeenCalled();
});

it('envía la consulta de pesaje con la paquetería comercial', async () => {
    const guardar = await render();
    const select = document.getElementById('paqueteria-nacional');
    await act(async () => {
        select.value = '8';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await act(async () => botonEnviar().click());
    expect(guardar).toHaveBeenCalledWith({ catalogo_paqueteria_id: 8 });
    expect(axios.post).toHaveBeenCalledWith(
        '/control-pedidos/9/solicitar-pesaje',
        expect.objectContaining({ revision_esperada: 2, base_captura: 'b'.repeat(64) }),
        expect.anything(),
    );
    expect(document.querySelector('label[for="almacen-salida-nacional"]').textContent).toBe('Almacén de salida');
    expect(document.getElementById('almacen-salida-nacional').disabled).toBe(true);
});

it('conserva la paquetería comercial del alta y no la vuelve a pedir tras el cierre', async () => {
    const cerrado = {
        ...pedido,
        consulta_cerrada_at: '2026-11-20T18:00:00Z',
        pesaje_solicitado_at: '2026-11-20T12:00:00Z',
        catalogo_paqueteria_id: 8,
        revision_registro: 4,
        base_captura: 'd'.repeat(64),
    };
    await act(async () => {
        root.render(
            <FormularioRegistrarNacional pedido={cerrado} catalogos={catalogos} guardar={vi.fn()} onPedido={vi.fn()}>
                {({ contenido, panelEnvio, pie }) => <div>{panelEnvio}{contenido}{pie}</div>}
            </FormularioRegistrarNacional>,
        );
    });
    expect(document.getElementById('paqueteria-posterior')).toBeNull();
    expect(document.getElementById('paqueteria-nacional')).toBeNull();
    expect(document.body.textContent).toContain('Paquetería: DHL');
    expect([...document.querySelectorAll('button')].some((item) => item.textContent.includes('Seleccionar paquetería'))).toBe(false);
});

it('con PAQ. PENDIENTE ofrece DHL y las demás nacionales para definir la empresa', async () => {
    const cerrado = {
        ...pedido,
        consulta_cerrada_at: '2026-11-20T18:00:00Z',
        pesaje_solicitado_at: '2026-11-20T12:00:00Z',
        catalogo_paqueteria_id: 9,
        revision_registro: 4,
        base_captura: 'd'.repeat(64),
    };
    axios.post.mockResolvedValue({ data: { ok: true, catalogo_paqueteria_id: 8 } });
    await act(async () => {
        root.render(
            <FormularioRegistrarNacional pedido={cerrado} catalogos={catalogos} guardar={vi.fn()} onPedido={vi.fn()}>
                {({ contenido, panelEnvio, pie }) => <div>{panelEnvio}{contenido}{pie}</div>}
            </FormularioRegistrarNacional>,
        );
    });
    const select = document.getElementById('paqueteria-posterior');
    expect(select).not.toBeNull();
    expect(select.textContent).toContain('DHL');
    expect(select.textContent).toContain('PAQ. PENDIENTE');
    expect(document.getElementById('paqueteria-nacional')).toBeNull();
    await act(async () => {
        select.value = '8';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });
    const boton = [...document.querySelectorAll('button')].find((item) => item.textContent.includes('Seleccionar paquetería'));
    expect(boton.disabled).toBe(false);
    await act(async () => boton.click());
    expect(axios.post).toHaveBeenCalledWith(
        '/control-pedidos/9/paqueteria-nacional',
        expect.objectContaining({
            catalogo_paqueteria_id: 8,
            revision_esperada: 4,
            base_captura: 'd'.repeat(64),
        }),
        expect.anything(),
    );
});

it('ofrece el anexo extraordinario en un pedido con remisión y abre su modal corto', async () => {
    const conRemision = {
        ...pedido,
        numero_remision: 'REM-9',
        consulta_cerrada_at: '2026-11-20T14:00:00Z',
        pago_validado_at: '2026-11-20T18:00:00Z',
        pesaje_solicitado_at: '2026-11-20T12:00:00Z',
    };
    await act(async () => {
        root.render(
            <FormularioRegistrarNacional pedido={conRemision} catalogos={catalogos} guardar={vi.fn()} onPedido={vi.fn()}>
                {({ contenido, panelEnvio, pie }) => <div>{panelEnvio}{contenido}{pie}</div>}
            </FormularioRegistrarNacional>,
        );
    });
    expect(document.getElementById('importe-complemento')).toBeNull();
    expect(document.body.textContent).not.toContain('Registrar complemento');
    const abrir = [...document.querySelectorAll('button')].find((item) => item.textContent.trim() === 'Anexo extraordinario');
    expect(abrir).toBeDefined();
    await act(async () => abrir.click());
    const dialogo = document.body.querySelector('[role="dialog"]');
    expect(dialogo.textContent).toContain('Hereda cliente, almacén de salida, paquetería y dirección');
    expect(document.getElementById('anexo-folio-wizerp')).not.toBeNull();
});

it('no ofrece el anexo extraordinario mientras el pedido no tiene pago ni remisión', async () => {
    await render({ pesaje_solicitado_at: '2026-11-20T12:00:00Z', consulta_cerrada_at: '2026-11-20T13:00:00Z' });
    expect([...document.querySelectorAll('button')].some((item) => item.textContent.trim() === 'Anexo extraordinario')).toBe(false);
});

const nacionalCerradoConRemision = {
    numero_remision: 'REM-9',
    pesaje_solicitado_at: '2026-11-20T12:00:00Z',
    pesaje_respondido_at: '2026-11-20T13:00:00Z',
    consulta_cerrada_at: '2026-11-20T14:00:00Z',
};

it.each([
    { nombre: 'DHL', id: 8, continuacion: 'Cotización y pago' },
    { nombre: 'PAQ. PENDIENTE', id: 9, continuacion: 'Pago de mercancía' },
])('conserva la continuación para $nombre sin el panel de sucursal', async ({ id, continuacion }) => {
    await render({ ...nacionalCerradoConRemision, catalogo_paqueteria_id: id });

    expect(host.textContent).toContain(continuacion);
    expect(host.textContent).toContain('Anexo extraordinario');
    expect(host.querySelector('[aria-label="Recolección en sucursal"]')).toBeNull();
    expect(host.querySelector('#sucursal-recoleccion-nacional')).toBeNull();
    expect(host.textContent).not.toMatch(/Sucursal de recolección|Elegir sucursal|Solicitar recolección en sucursal/i);
    expect(axios.post).not.toHaveBeenCalled();
});

it.each([false, true])('conserva las restricciones de un cambio registrado y el aviso de guía (cancelación confirmada: %s)', async cancelacionConfirmada => {
    await render({
        ...nacionalCerradoConRemision,
        catalogo_paqueteria_id: 9,
        solicitud_preparacion: {
            cambio_a_tienda: { estado: 'pendiente_recepcion', sucursal_destino_id: 4, saldo_envio: 55 },
            invalidacion_guia: { cancelacion_paqueteria_confirmada: cancelacionConfirmada },
        },
    });

    expect(host.querySelector('[aria-label="Recolección en sucursal"]')).toBeNull();
    expect(host.textContent).not.toMatch(/Recolección en sucursal|Costo de envío conservado/);
    expect(host.querySelector('#paqueteria-posterior')).toBeNull();
    expect([...host.querySelectorAll('p')].some(({ textContent }) => ['Pago de mercancía', 'Cotización y pago'].includes(textContent))).toBe(false);
    expect(host.querySelector('[aria-label="Anexo extraordinario"]')).toBeNull();
    const aviso = [...host.querySelectorAll('[role="status"]')].find(({ textContent }) => textContent.includes('Falta confirmar la cancelación de la guía con la paquetería.'));
    expect(Boolean(aviso)).toBe(!cancelacionConfirmada);
    expect(axios.post).not.toHaveBeenCalled();
});

it('transmite piezas precapturadas sin exigir recaptura ni una lista de SKU', async () => {
    const solicitud = { modalidad_entrega: 'nacional', captura_nacional: 'compartida', piezas_nacionales: [
        { id: 'sku-previo', sku: 'SKU-PREVIO', descripcion: 'Pieza precapturada', cantidad: 1 },
        { id: 'captura-previa', sku: '', descripcion: 'Pieza sin SKU', cantidad: 1, documento_ids: [40] },
    ] };
    const guardar = await render({ documentos: [], solicitud_preparacion: solicitud });
    expect(document.getElementById('mercancia-nacional-titulo')).toBeNull();
    await act(async () => {
        const select = document.getElementById('paqueteria-nacional');
        select.value = '9'; select.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await act(async () => botonEnviar().click());
    expect(guardar).toHaveBeenCalledWith({ catalogo_paqueteria_id: 9 });
    expect(axios.post.mock.calls[0][0]).toBe('/control-pedidos/9/solicitar-pesaje');
    expect(solicitud.piezas_nacionales).toHaveLength(2);
    expect(document.body.textContent).toContain('CEDIS tiene pendiente confirmar disponibilidad y peso');
    expect(document.getElementById('mercancia-nacional-titulo')).toBeNull();
});

it('muestra el aviso de una guía afectada y oculta cambios después de la salida', async () => {
    await render({
        pesaje_solicitado_at: '2026-11-20T12:00:00Z',
        consulta_cerrada_at: '2026-11-20T13:00:00Z',
        estatus: { fase_ciclo: 'ENVIADO' },
        invalidacion_guia: { cancelacion_paqueteria_confirmada: false },
        numero_remision: 'REM-9',
    });
    expect(document.body.textContent).toContain('Falta confirmar la cancelación');
    expect(document.getElementById('paqueteria-posterior')).toBeNull();
    expect(document.getElementById('sucursal-recoleccion-nacional')).toBeNull();
    expect(document.getElementById('importe-complemento')).toBeNull();
});

it('anexa sobre el mismo pedido nacional', async () => {
    const form = new FormData();
    const adaptador = adaptadorConsultaNacional({
        pedidoId: 7,
        pedido: { pesaje_respondido_at: '2026-11-20', revision_registro: 3, base_captura: 'c'.repeat(64) },
    });
    await adaptador.comandos.anexar({ archivos: [{ size: 10 }], armar: () => form });
    expect(axios.post).toHaveBeenCalledWith('/control-pedidos/7/ajuste-nacional', form, expect.anything());
    expect(form.get('tipo')).toBe('anexo');
    expect(String(axios.post.mock.calls[0][0])).not.toContain('anexo-tienda');
});

const cedisSalida = (id, nombre) => ({ id, nombre, tipo_almacen: { nombre: 'CEDIS' }, permite_salida_pedidos: true });
const salida = () => document.getElementById('almacen-salida-nacional');
const guardarBorrador = () => [...document.querySelectorAll('button')].find(b => b.textContent === 'Guardar borrador');
const elegirSalida = id => act(async () => {
    salida().value = String(id);
    salida().dispatchEvent(new Event('change', { bubbles: true }));
});

it('preselecciona el único CEDIS elegible del catálogo sin fijar su id o nombre', async () => {
    await render({}, { ...catalogos, almacenes: [
        cedisSalida(47, 'Centro logístico del catálogo'),
        { ...cedisSalida(48, 'Sin salida'), permite_salida_pedidos: false },
        { ...cedisSalida(49, 'Inactivo'), activo: false },
        { ...cedisSalida(50, 'Oculto'), visible_en_pedidos: false },
        { ...cedisSalida(51, 'Sucursal PDV'), tipo_almacen: { nombre: 'PDV' } },
    ] });
    expect(salida().value).toBe('47');
    expect([...salida().options].filter(o => o.value).map(o => o.textContent)).toEqual(['Centro logístico del catálogo']);
    expect(salida().disabled).toBe(false);
});

it('no inventa una salida cuando el catálogo está vacío o es ambiguo', async () => {
    await render();
    expect(salida().value).toBe('');
    expect([...salida().options].filter(o => o.value)).toHaveLength(0);
    await render({}, { ...catalogos, almacenes: [cedisSalida(47, 'CEDIS Norte'), cedisSalida(73, 'CEDIS Sur')] });
    expect(salida().value).toBe('');
});

it('guarda almacen_id, conserva la elección al confirmar y la recupera al reabrir', async () => {
    const opciones = { ...catalogos, almacenes: [cedisSalida(47, 'CEDIS Norte'), cedisSalida(73, 'CEDIS Sur')] };
    const guardar = await render({ almacen_id: 47, catalogo_paqueteria_id: 8 }, opciones);
    await elegirSalida(73);
    await act(async () => guardarBorrador().click());
    expect(guardar).toHaveBeenCalledWith({ catalogo_paqueteria_id: 8, almacen_id: 73 });
    // La respuesta simulada omite almacen_id, igual que el autoguardado actual.
    expect(salida().value).toBe('73');
    expect(axios.post).not.toHaveBeenCalled();
    await act(() => root.unmount()); root = createRoot(host);
    await render({ almacen_id: 73, catalogo_paqueteria_id: 8 }, opciones);
    expect(salida().value).toBe('73');
});

it('guarda la salida antes de consultar y bloquea el selector después del envío', async () => {
    const guardar = await render({ catalogo_paqueteria_id: 8 }, { ...catalogos, almacenes: [cedisSalida(47, 'CEDIS Norte')] });
    await act(async () => botonEnviar().click());
    expect(guardar).toHaveBeenCalledWith({ catalogo_paqueteria_id: 8, almacen_id: 47 });
    expect(axios.post).toHaveBeenCalledWith('/control-pedidos/9/solicitar-pesaje', expect.anything(), expect.anything());
    expect(salida().value).toBe('47'); expect(salida().disabled).toBe(true);
});

it('mantiene una salida previamente guardada si deja de estar disponible', async () => {
    await render({ almacen_id: 73, almacen: { id: 73, nombre: 'CEDIS anterior' }, pesaje_solicitado_at: '2026-11-21T12:00:00Z' },
        { ...catalogos, almacenes: [cedisSalida(47, 'CEDIS Norte')] });
    expect(salida().value).toBe('73'); expect(salida().disabled).toBe(true);
    expect(salida().selectedOptions[0].textContent).toContain('CEDIS anterior');
    expect(salida().selectedOptions[0].disabled).toBe(true);
});

it('conserva la elección local cuando falla el guardado', async () => {
    const guardar = await render({ almacen_id: 47 }, { ...catalogos, almacenes: [cedisSalida(47, 'CEDIS Norte'), cedisSalida(73, 'CEDIS Sur')] });
    guardar.mockRejectedValueOnce(new Error('Fallo simulado'));
    await elegirSalida(73);
    await act(async () => guardarBorrador().click());
    expect(salida().value).toBe('73'); expect(salida().disabled).toBe(false);
    expect(axios.post).not.toHaveBeenCalled();
});

it.each([
    ['registro', { id: undefined }],
    ['borrador', {}],
    ['consulta pendiente', { pesaje_solicitado_at: '2026-11-21T12:00:00Z' }],
    ['actualización pendiente', { pesaje_solicitado_at: '2026-11-21T12:00:00Z', pesaje_respondido_at: '2026-11-21T13:00:00Z', consulta_actualizacion_pendiente: true }],
])('oculta Mercancía en %s aunque conserve piezas precapturadas', async (_, estado) => {
    await render({ ...estado, solicitud_preparacion: { modalidad_entrega: 'nacional', captura_nacional: 'compartida',
        piezas_nacionales: [{ id: 'previa', sku: 'SKU-PREVIO', descripcion: 'Pieza precapturada', cantidad: 1 }] } });
    expect(document.getElementById('mercancia-nacional-titulo')).toBeNull();
    expect(document.querySelector('[aria-label="Ajuste de piezas"]')).toBeNull();
    expect(document.getElementById('nacional-buscar-producto')).toBeNull();
    expect(axios.post).not.toHaveBeenCalled();
});

it('muestra piezas y ajustes nacionales solo al recibir una respuesta vigente y los oculta al actualizar', async () => {
    const consulta = { pesaje_solicitado_at: '2026-11-21T12:00:00Z', pesaje_respondido_at: '2026-11-21T13:00:00Z',
        solicitud_preparacion: { modalidad_entrega: 'nacional', captura_nacional: 'compartida',
            piezas_nacionales: [{ id: 'previa', sku: 'SKU-PREVIO', descripcion: 'Pieza precapturada', cantidad: 1 }] } };
    await render(consulta);
    const mercancia = document.querySelector('[aria-labelledby="mercancia-nacional-titulo"]');
    expect(mercancia.textContent).toContain('Pieza precapturada');
    expect(mercancia.textContent).toContain('Agregar piezas');
    expect(mercancia.querySelector('[aria-label="Ajuste de piezas"]')).toBeTruthy();
    expect(document.getElementById('nacional-buscar-producto')).toBeNull();
    await render({ ...consulta, consulta_actualizacion_pendiente: true });
    expect(document.getElementById('mercancia-nacional-titulo')).toBeNull();
    await render({ ...consulta, consulta_actualizacion_pendiente: false });
    expect(document.querySelector('[aria-labelledby="mercancia-nacional-titulo"]').textContent).toContain('Pieza precapturada');
    expect(axios.post).not.toHaveBeenCalled();
});

it.each([
    { consulta_cerrada_at: '2026-11-21T14:00:00Z' },
    { pago_validado_at: '2026-11-21T14:00:00Z' },
    { numero_remision: 'REM-PREVIA' },
])('conserva las restricciones de ajustes después de la respuesta: %j', async bloqueo => {
    await render({ ...bloqueo, pesaje_solicitado_at: '2026-11-21T12:00:00Z', pesaje_respondido_at: '2026-11-21T13:00:00Z' });
    const mercancia = document.querySelector('[aria-labelledby="mercancia-nacional-titulo"]');
    expect(mercancia).toBeTruthy();
    expect(mercancia.querySelector('[aria-label="Ajuste de piezas"]')).toBeNull();
    expect(axios.post).not.toHaveBeenCalled();
});
