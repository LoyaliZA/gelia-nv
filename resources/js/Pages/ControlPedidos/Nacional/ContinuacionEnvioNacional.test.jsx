// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import ContinuacionEnvioNacional from './ContinuacionEnvioNacional';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }));
vi.mock('../Partials/ModalGenerarLinkDireccion', () => ({
    default: ({ abierto, clientePreseleccionado, onEnlaceGenerado }) => (abierto
        ? <div role="dialog" aria-label="Link de dirección">Enlace para {clientePreseleccionado?.nombre}<button type="button" onClick={onEnlaceGenerado}>Enlace generado</button></div>
        : null),
}));
vi.mock('../Partials/SeccionPagosExhibicion', () => ({
    default: ({ omiteEnvio, costoEnvio }) => <p>{omiteEnvio ? 'Solo mercancía' : `Flete guardado ${costoEnvio}`}</p>,
}));

const pedido = {
    id: 9, cliente_id: 4, total_mercancia: '100.00', catalogo_paqueteria_id: 2,
    estatus: { fase_ciclo: 'PESAJE_RESPONDIDO' },
    revision_registro: 3, base_captura: 'a'.repeat(64),
    cajas: [{ id: 5, uuid_operativo: 'caja-1', estado_operativo: 'activa' }],
};
const catalogos = {
    tipos_guia: [{ id: 3, nombre: 'Guía nacional' }],
    zonas: [{ id: 6, nombre: 'Entrega directa' }, { id: 7, nombre: 'Con reexpedición' }],
    paqueterias: [{ id: 2, nombre: 'DHL', cobertura_seguro: true }],
};

let host;
let root;
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
    vi.resetAllMocks();
    axios.get.mockResolvedValue({ data: { direcciones: [{
        id: 8, codigo_postal: '86000', etiqueta: 'Casa', es_principal: true,
        nombre_destinatario: 'Ana Ruiz', telefono_destinatario: '9931112233',
        calle: 'Calle 1', colonia: 'Centro', municipio: 'Centro', estado: 'Tabasco',
    }] } });
});

afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

async function render(extra = {}, pendiente = false, onPedido = vi.fn(), permisos = {}) {
    await act(async () => root.render(
        <ContinuacionEnvioNacional pedido={{ ...pedido, ...extra }} catalogos={catalogos} pendiente={pendiente} onPedido={onPedido} {...permisos} />,
    ));
    return onPedido;
}

it('permite enviar la cobertura de mercancía al auxiliar sin abrir cotización', async () => {
    axios.put.mockResolvedValue({ data: { estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR' } } });
    const onPedido = await render({}, true);
    expect(host.textContent).toContain('Solo mercancía');
    expect(host.textContent).not.toContain('Capturar cotización');
    expect(axios.get).not.toHaveBeenCalled();
    await act(async () => host.querySelector('button').click());
    expect(axios.put).toHaveBeenCalledWith('/control-pedidos/9/enviar', {}, expect.anything());
    expect(onPedido).toHaveBeenCalledWith({ estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR' } });
});

it('registra dirección, guía y reexpedición sin cobrar flete', async () => {
    const respuesta = { costo_envio: '0.00', solicitud_preparacion: { cotizacion_nacional: { paqueteria_id: 2 } } };
    axios.post.mockResolvedValue({ data: respuesta });
    const onPedido = await render({ mercancia_pagada: true, estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR' } });
    expect(host.textContent).toContain('Mercancía pagada');
    expect(host.textContent).not.toContain('Flete guardado');

    await act(async () => {
        const selects = host.querySelectorAll('select');
        ['8', '3', '6'].forEach((id, i) => {
            selects[i].value = id;
            selects[i].dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
    await act(async () => [...host.querySelectorAll('button')].find((item) => item.textContent.includes('Guardar cotización')).click());
    expect(axios.post).toHaveBeenCalledWith('/control-pedidos/9/paqueteria-nacional', expect.objectContaining({
        catalogo_paqueteria_id: 2,
        revision_esperada: 3,
        base_captura: 'a'.repeat(64),
        cotizacion: { cliente_direccion_id: 8, catalogo_tipo_guia_id: 3, catalogo_zona_id: 6 },
        lineas: [{ uuid_operativo: 'caja-1', costo_envio: 0 }],
    }), expect.anything());
    expect(onPedido).toHaveBeenCalledWith(respuesta);
});

async function renderSinDirecciones(extra = {}) {
    axios.get.mockResolvedValue({ data: { direcciones: [] } });
    await act(async () => root.render(
        <ContinuacionEnvioNacional pedido={pedido} catalogos={catalogos} pendiente={false} onPedido={vi.fn()} {...extra} />,
    ));
}

const boton = (texto) => [...document.body.querySelectorAll('button')].find((b) => b.textContent.trim() === texto);
const escribir = async (campo, valor) => act(async () => {
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(campo, valor);
    campo.dispatchEvent(new Event('input', { bubbles: true }));
});
const abrirCotizacion = async () => act(async () => { host.querySelector('summary').click(); });

it('sin direcciones permite agregar una dentro del pedido y la deja elegida', async () => {
    await renderSinDirecciones({ puedeAgregarDireccion: true });
    await abrirCotizacion();
    expect(host.textContent).toContain('no tiene dirección principal ni adicional');
    await act(async () => boton('Agregar dirección').click());

    const campos = [...document.body.querySelectorAll('[role="dialog"] input[type="text"]')];
    // Orden del formulario: destinatario, teléfono, calle, ext., int., colonia, C.P., municipio, ciudad, estado, país.
    await escribir(campos[0], 'Ana Prueba');
    await escribir(campos[2], 'Calle 1');
    await escribir(campos[5], 'Centro');
    await escribir(campos[6], '86000');
    await escribir(campos[7], 'Centro');
    await escribir(campos[9], 'Tabasco');

    axios.post.mockResolvedValue({ data: { ok: true, direccion: { id: 12, nombre_destinatario: 'Ana Prueba', codigo_postal: '86000' } } });
    await act(async () => boton('Guardar dirección').click());

    expect(axios.post).toHaveBeenCalledWith('/control-pedidos/registrar-direccion-catalogo', expect.objectContaining({
        cliente_id: 4, pedido_id: 9, es_principal: true,
        nombre_destinatario: 'Ana Prueba', calle: 'Calle 1', colonia: 'Centro', codigo_postal: '86000', municipio: 'Centro', estado: 'Tabasco',
    }), expect.anything());
    expect(document.body.querySelector('[role="dialog"]')).toBeNull();
    expect(host.querySelector('select').value).toBe('12');
});

it('no guarda una dirección incompleta y dice qué falta', async () => {
    await renderSinDirecciones({ puedeAgregarDireccion: true });
    await abrirCotizacion();
    await act(async () => boton('Agregar dirección').click());
    await act(async () => boton('Guardar dirección').click());
    expect(axios.post).not.toHaveBeenCalled();
    expect(document.body.querySelector('[role="dialog"]').textContent).toContain('código postal');
});

it('sin permiso de direcciones no ofrece el alta y pide ayuda a un auxiliar', async () => {
    await renderSinDirecciones();
    await abrirCotizacion();
    expect(boton('Agregar dirección')).toBeUndefined();
    expect(host.textContent).toContain('auxiliar');
});

it('elige la dirección principal y muestra el nombre de quien recibe', async () => {
    axios.get.mockResolvedValue({ data: { direcciones: [
        { id: 9, es_principal: false, etiqueta: 'Oficina', nombre_destinatario: 'Luis Pérez', codigo_postal: '86100', municipio: 'Centro', estado: 'Tabasco' },
        {
            id: 8, es_principal: true, etiqueta: 'Casa', nombre_destinatario: 'Ana Ruiz',
            telefono_destinatario: '9931112233', calle: 'Calle 1', colonia: 'Centro',
            codigo_postal: '86000', municipio: 'Centro', estado: 'Tabasco',
        },
    ] } });
    await render();
    expect(host.querySelector('select').value).toBe('8');
    const preview = host.querySelector('[aria-label="Dirección elegida"]');
    expect(preview.textContent).toContain('Nombre de quien recibe');
    expect(preview.textContent).toContain('Ana Ruiz');
    expect(preview.textContent).toContain('Calle 1');
    expect(host.textContent).toContain('Principal · Ana Ruiz · 86000');
});

it('muestra el envío guardado y lo suma a la reexpedición para la cobertura', async () => {
    await render({
        total_mercancia: '2000.00',
        costo_envio: '356.00',
        costo_seguro: '0.00',
        aplica_seguro: false,
        solicitud_preparacion: { cotizacion_nacional: { paqueteria_id: 2 } },
        cajas: [{ id: 5, uuid_operativo: 'caja-1', estado_operativo: 'activa', costo_envio: '206.00' }],
    });
    const desglose = host.querySelector('[aria-label="Desglose de la cotización"]');
    expect(desglose).not.toBeNull();
    expect(desglose.textContent).toContain('Envío$206.00');
    expect(host.textContent).toContain('Flete guardado 356.00');
    expect(desglose.textContent).toContain('Reexpedición$150.00');
    expect(desglose.textContent).toContain('Seguro$0.00');
});

const avanzar = async (ms = 500) => act(async () => { await vi.advanceTimersByTimeAsync(ms); });
const previa = (extra = {}) => ({ data: { venta: 100, flete: 0, reexpedicion: 0, guia: 0, seguro: 0, total: 100, cobertura_seguro: true, ...extra } });

it('separa en contenedores propios la dirección y el costo de envío', async () => {
    await render();
    const direccion = host.querySelector('[aria-label="Dirección de entrega"]');
    const costo = host.querySelector('[aria-label="Costo de envío"]');
    expect(direccion).not.toBeNull();
    expect(costo).not.toBeNull();
    expect(direccion.contains(costo)).toBe(false);
    expect(direccion.querySelector('select')).not.toBeNull();
    expect(costo.textContent).toContain('No. de caja');
    expect(costo.textContent).toContain('1');
    expect(costo.querySelector('input[inputmode="decimal"]').labels[0].textContent.trim()).toBe('Envío (MXN)');
    expect(costo.textContent).toContain('Incluir seguro');
    expect(costo.textContent).toContain('Paquetería: DHL');
    expect(direccion.textContent).not.toContain('Flete de caja');
    expect(direccion.textContent).not.toContain('No. de caja');
});

it('deja el seguro apagado por defecto', async () => {
    await render();
    expect(host.querySelector('[aria-label="Costo de envío"] input[type="checkbox"]').checked).toBe(false);
});

it('no ofrece el seguro cuando la paquetería no tiene cobertura', async () => {
    await act(async () => root.render(
        <ContinuacionEnvioNacional pedido={pedido} onPedido={vi.fn()} pendiente={false}
            catalogos={{ ...catalogos, paqueterias: [{ id: 2, nombre: 'PAQUETEXPRESS', cobertura_seguro: false }] }} />,
    ));
    expect(host.querySelector('input[type="checkbox"]')).toBeNull();
});

it('muestra el desglose en vivo con el seguro que calcula el servidor', async () => {
    vi.useFakeTimers();
    try {
        axios.post.mockResolvedValue(previa({ seguro: 71, total: 171 }));
        await render();
        await act(async () => host.querySelector('[aria-label="Costo de envío"] input[type="checkbox"]').click());
        await avanzar();
        expect(axios.post).toHaveBeenCalledWith('/control-pedidos/9/cotizacion-nacional/vista-previa', expect.objectContaining({
            catalogo_paqueteria_id: 2,
            aplica_seguro: true,
            lineas: [{ uuid_operativo: 'caja-1', costo_envio: 0 }],
        }), expect.anything());
        const desglose = host.querySelector('[aria-label="Vista previa de la cotización"]');
        expect(desglose.textContent).toContain('Seguro$71.00');
        expect(desglose.textContent).toContain('Total$171.00');
        expect(desglose.textContent).toContain('Envío$0.00');
    } finally {
        vi.useRealTimers();
    }
});

it('permite corregir el envío de un pedido pendiente de auditoría sin usar el número de cajas como costo', async () => {
    vi.useFakeTimers();
    try {
        const actual = {
            total_mercancia: '2000.00',
            costo_envio: '150.00',
            cliente_direccion_id: 8,
            catalogo_tipo_guia_id: 3,
            catalogo_zona_id: 7,
            solicitud_preparacion: { cotizacion_nacional: { paqueteria_id: 2 } },
            estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR' },
        };
        const guardado = { ...actual, costo_envio: '356.00' };
        axios.post.mockResolvedValueOnce(previa({ venta: 2000, flete: 206, reexpedicion: 150, guia: 356, total: 2356 }))
            .mockResolvedValueOnce({ data: guardado });
        const onPedido = await render(actual);
        await escribir(host.querySelector('input[inputmode="decimal"]'), '206,00');
        await avanzar();

        const lineas = [{ uuid_operativo: 'caja-1', costo_envio: 206 }];
        expect(axios.post).toHaveBeenCalledWith('/control-pedidos/9/cotizacion-nacional/vista-previa',
            expect.objectContaining({ lineas }), expect.anything());
        const vistaPrevia = host.querySelector('[aria-label="Vista previa de la cotización"]');
        expect(vistaPrevia.textContent).toContain('Envío$206.00');
        expect(vistaPrevia.textContent).toContain('Total$2,356.00');
        expect(host.querySelector('[aria-label="Desglose de la cotización"]').textContent).toContain('Envío$0.00');

        await act(async () => boton('Guardar cotización').click());
        expect(axios.post).toHaveBeenCalledWith('/control-pedidos/9/paqueteria-nacional',
            expect.objectContaining({ lineas }), expect.anything());
        expect(onPedido).toHaveBeenCalledWith(guardado);
    } finally {
        vi.useRealTimers();
    }
});

it('consulta la vista previa con flete en cero y no muestra NaN', async () => {
    vi.useFakeTimers();
    try {
        axios.post.mockResolvedValue(previa());
        await render();
        await avanzar();
        expect(axios.post).toHaveBeenCalledWith('/control-pedidos/9/cotizacion-nacional/vista-previa', expect.objectContaining({
            lineas: [{ uuid_operativo: 'caja-1', costo_envio: 0 }],
        }), expect.anything());
        expect(host.textContent).not.toContain('NaN');
    } finally {
        vi.useRealTimers();
    }
});

it('descarta una respuesta vieja cuando llega después de una más reciente', async () => {
    vi.useFakeTimers();
    try {
        let soltarPrimera;
        axios.post
            .mockImplementationOnce(() => new Promise((resolver) => { soltarPrimera = () => resolver(previa({ total: 100 })); }))
            .mockResolvedValueOnce(previa({ seguro: 71, total: 171 }));
        await render();
        await avanzar();
        await act(async () => host.querySelector('[aria-label="Costo de envío"] input[type="checkbox"]').click());
        await avanzar();
        await act(async () => soltarPrimera());
        expect(host.querySelector('[aria-label="Vista previa de la cotización"]').textContent).toContain('Total$171.00');
    } finally {
        vi.useRealTimers();
    }
});

it('avisa dentro del pedido que falta una dirección y ofrece agregarla o enviar el enlace', async () => {
    await renderSinDirecciones({ puedeAgregarDireccion: true, puedeGenerarEnlace: true });
    await abrirCotizacion();
    const aviso = host.querySelector('[aria-label="Dirección de entrega"] [role="status"]');
    expect(aviso.textContent).toContain('no tiene dirección principal ni adicional');
    expect(boton('Agregar dirección')).toBeDefined();
    expect(boton('Enviar enlace al cliente')).toBeDefined();
});

it('con solo el permiso de enlaces ofrece el enlace y no el alta manual', async () => {
    await renderSinDirecciones({ puedeGenerarEnlace: true });
    await abrirCotizacion();
    expect(boton('Agregar dirección')).toBeUndefined();
    expect(boton('Enviar enlace al cliente')).toBeDefined();
});

it('sin ningún permiso de direcciones solo avisa y pide ayuda a un auxiliar', async () => {
    await renderSinDirecciones();
    await abrirCotizacion();
    expect(boton('Enviar enlace al cliente')).toBeUndefined();
    expect(host.textContent).toContain('auxiliar');
});

it('abre el enlace para el cliente del pedido', async () => {
    await renderSinDirecciones({ puedeGenerarEnlace: true, pedido: { ...pedido, cliente: { numero_cliente: '04950', nombre: 'Ana Ruiz' } } });
    await abrirCotizacion();
    await act(async () => boton('Enviar enlace al cliente').click());
    expect(document.body.querySelector('[role="dialog"]').textContent).toContain('Enlace para Ana Ruiz');
});

it('vuelve a consultar las direcciones cuando el cliente ya registró la suya', async () => {
    await renderSinDirecciones({ puedeGenerarEnlace: true });
    await abrirCotizacion();
    expect(axios.get).toHaveBeenCalledTimes(1);
    axios.get.mockResolvedValue({ data: { direcciones: [{ id: 20, es_principal: false, etiqueta: 'Casa', nombre_destinatario: 'Ana Ruiz', codigo_postal: '86000' }] } });
    await act(async () => boton('Actualizar direcciones').click());
    expect(axios.get).toHaveBeenCalledTimes(2);
    expect(host.querySelector('[aria-label="Dirección de entrega"] select').value).toBe('20');
    expect(boton('Actualizar direcciones')).toBeDefined();
});

it('con direcciones ya registradas conserva las opciones para agregar otra, enviar el enlace o actualizar', async () => {
    await render({}, false, vi.fn(), { puedeAgregarDireccion: true, puedeGenerarEnlace: true });
    await abrirCotizacion();
    expect(host.querySelector('[aria-label="Dirección de entrega"] select').options.length).toBeGreaterThan(1);
    expect(boton('Agregar dirección')).toBeDefined();
    expect(boton('Enviar enlace al cliente')).toBeDefined();
    expect(boton('Actualizar direcciones')).toBeDefined();
});
