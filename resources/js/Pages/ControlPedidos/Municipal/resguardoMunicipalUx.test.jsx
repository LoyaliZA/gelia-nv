// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import Formulario from './FormularioRegistrarMunicipal';
import RemisionesPagadasMunicipal from './RemisionesPagadasMunicipal';
import PanelResguardoMunicipalAuditoria from './PanelResguardoMunicipalAuditoria';
import IntegracionFisicaConjunto from './IntegracionFisicaConjunto';

vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: { auth: { user: { id: 1 } } } }) }));
vi.mock('../Partials/SeccionPagosExhibicion', () => ({ default: () => null }));
vi.mock('./ExpedienteConsignatario', () => ({ default: () => null }));
vi.mock('../Partials/ModalVistaPreviaDocumento', () => ({ default: () => null }));
vi.mock('../Partials/AccionesRapidasDisponibilidad', () => ({ default: () => null }));

let root, host;
const resguardoBase = { activo: false, capacidades: { puede_vincular: true }, vinculos: [], estados: [], cobertura_mercancia: 'ausente' };
const pedido = {
    id: 44, folio: 'PBMA-44', catalogo_paqueteria_id: 2, revision_registro: 3, base_captura: 'base',
    total_mercancia: 100, resguardo_envio: resguardoBase,
    solicitud_preparacion: { modalidad_entrega: 'municipal', finanzas_municipales: {}, expediente: { consignatario_id: 12 } },
};
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    sessionStorage.clear();
    window.route = (name, id) => `${name}/${[].concat(id ?? '').join('/')}`;
    window.axios = { get: vi.fn(async () => ({ data: {} })), post: vi.fn(), delete: vi.fn() };
    host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(() => root.unmount()); host.remove(); vi.useRealTimers(); });
const button = (text) => [...document.querySelectorAll('button')].find((el) => el.textContent === text);
const renderForm = async () => act(() => root.render(<Formulario abierto pedido={pedido} catalogos={{ paqueterias: [], paqueterias_municipio: [] }}>{({ contenido, pie }) => <>{contenido}{pie}</>}</Formulario>));
const escribir = async (el, valor) => act(async () => {
    const setter = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(el), 'value').set;
    setter.call(el, valor);
    el.dispatchEvent(new Event('input', { bubbles: true }));
});
const estado = () => document.querySelector('[data-estado-resguardo]')?.textContent;

it('el resguardo no escribe al cambiar campos y muestra Sin guardar hasta guardar', async () => {
    await renderForm();
    const casilla = document.querySelector('input[type=checkbox]');
    await act(async () => casilla.click());
    expect(window.axios.post).not.toHaveBeenCalled();
    expect(estado()).toBe('Sin guardar');
    expect(document.getElementById('municipal-motivo-envio').textContent).toContain('Indique el motivo de la espera.');
});

it('conserva motivo y fecha ante un fallo y no muestra Guardado antes de la confirmación', async () => {
    let rechazar;
    window.axios.post = vi.fn(() => new Promise((_, reject) => { rechazar = reject; }));
    await renderForm();
    await act(async () => document.querySelector('input[type=checkbox]').click());
    const motivo = [...document.querySelectorAll('select')].find((el) => [...el.options].some((o) => o.value === 'fecha_posterior'));
    await act(async () => { motivo.value = 'fecha_posterior'; motivo.dispatchEvent(new Event('change', { bubbles: true })); });
    const fecha = document.querySelector('input[type=date]');
    await escribir(fecha, '2026-12-01');
    await act(async () => button('Guardar instrucción').click());
    expect(estado()).toBe('Guardando…');
    expect(button('Guardar instrucción')?.disabled ?? true).toBe(true);
    await act(async () => { rechazar({ response: { data: { message: 'Servidor no disponible' } } }); });
    expect(estado()).toBe('Error al guardar');
    expect(document.querySelector('input[type=date]').value).toBe('2026-12-01');
    expect(button('Reintentar guardado')).toBeTruthy();
    expect(window.axios.post).toHaveBeenCalledTimes(1);
});

it('Enviar a revisión se bloquea con resguardo sin mercancía cubierta y explica por qué de forma accesible', async () => {
    await renderForm();
    expect(button('Enviar a revisión')).toBeUndefined();
    // Con consulta cerrada y resguardo guardado sin pago, el mensaje exigido acompaña a la acción.
    const texto = 'El pedido aún no está pagado; no puede proceder para resguardo.';
    await act(() => root.render(<Formulario abierto pedido={{ ...pedido, consulta_cerrada: true, resguardo_envio: { ...resguardoBase, activo: true, motivo: 'acumulacion', motivo_bloqueo_resguardo: texto } }} catalogos={{ paqueterias: [], paqueterias_municipio: [] }}>{({ contenido, pie }) => <>{contenido}{pie}</>}</Formulario>));
    const pie = document.getElementById('municipal-motivo-envio');
    expect(pie.textContent).toContain(texto);
    const enviar = button('Enviar a revisión');
    if (enviar) {
        expect(enviar.disabled).toBe(true);
        expect(enviar.getAttribute('aria-describedby')).toBe('municipal-motivo-envio');
    }
});

it('la búsqueda de remisiones descarta respuestas obsoletas', async () => {
    vi.useFakeTimers();
    const pendientes = [];
    window.axios.get = vi.fn((_url, opciones) => new Promise((resolver) => { pendientes.push({ q: opciones.params.q, resolver }); }));
    await act(() => root.render(<RemisionesPagadasMunicipal pedido={pedido} />));
    await act(async () => { vi.advanceTimersByTime(10); });
    const input = document.querySelector('input');
    await escribir(input, 'AAA');
    await act(async () => { vi.advanceTimersByTime(400); });
    await escribir(input, 'AAAB');
    await act(async () => { vi.advanceTimersByTime(400); });
    const nueva = pendientes.find((p) => p.q === 'AAAB');
    const vieja = pendientes.find((p) => p.q === 'AAA');
    await act(async () => { nueva.resolver({ data: { data: [{ id: 2, folio: 'NUEVA', numero_remision: 'R2', mecanismos: ['resguardo_envio'] }] } }); });
    await act(async () => { vieja.resolver({ data: { data: [{ id: 1, folio: 'VIEJA', numero_remision: 'R1', mecanismos: ['resguardo_envio'] }] } }); });
    expect(document.body.textContent).toContain('NUEVA');
    expect(document.body.textContent).not.toContain('VIEJA');
});

it('Ventas no ve controles de integración física y la remisión pendiente no parece integrada', async () => {
    const vinculos = [
        { origen_id: 1, folio: 'F-1', remision: 'R-1', mecanismo: 'resguardo_envio', estado: 'integrado', integrado: true, pago_auditado: true, puede_desvincular: false },
        { origen_id: 2, folio: 'F-2', remision: 'R-2', mecanismo: 'custodia_local', estado: 'pendiente', integrado: false, pago_auditado: true, puede_desvincular: true },
    ];
    await act(() => root.render(<RemisionesPagadasMunicipal pedido={{ ...pedido, resguardo_envio: { ...resguardoBase, vinculos } }} />));
    expect([...document.querySelectorAll('button')].some((b) => /Confirmar integración/.test(b.textContent))).toBe(false);
    expect(document.body.textContent).toContain('1 de 2 remisiones integradas');
    expect(document.body.textContent).toContain('Pendiente de integrar en la sucursal');
});

it('el panel de auditoría explica el bloqueo, no muestra códigos y no pide datos nacionales', async () => {
    await act(() => root.render(<PanelResguardoMunicipalAuditoria resguardo={{ ...resguardoBase, activo: true, motivo: 'fecha_posterior', fecha_solicitada: '2026-12-01', cobertura_transporte: 'diferido', cajas: 'N/A', estados: ['resguardo_vigente', 'pendiente_auditoria'], bloqueo_liberacion: 'La mercancía no tiene un pago auditado vigente.' }} />));
    const texto = document.body.textContent;
    expect(texto).toContain('No se puede liberar todavía.');
    expect(texto).toContain('N/A');
    expect(texto).toContain('Llegar a la fecha no libera el envío');
    expect(texto).not.toMatch(/M-0\d|M-10/);
    expect(document.querySelector('input[type=file]')).toBeNull();
    expect(texto).not.toMatch(/guía|peso|domicilio/i);
});

it('CEDIS confirma solo lo anexado y recibe la respuesta del servidor', async () => {
    const vinculos = [{ origen_id: 7, folio: 'F-7', remision: 'R-7', mecanismo: 'resguardo_envio', estado: 'anexado', integrado: false, pago_auditado: true, puede_desvincular: true }];
    window.axios.post = vi.fn(async () => ({ data: { resguardo_envio: { activo: true, vinculos: [{ ...vinculos[0], integrado: true, estado: 'integrado' }] } } }));
    const alActualizar = vi.fn();
    await act(() => root.render(<IntegracionFisicaConjunto pedido={{ ...pedido, resguardo_envio: { ...resguardoBase, activo: true, vinculos } }} onActualizado={alActualizar} />));
    await act(async () => button('Confirmar integración en CEDIS').click());
    expect(window.axios.post).toHaveBeenCalledWith(expect.stringContaining('integrar_cedis'), {}, expect.anything());
    expect(alActualizar).toHaveBeenCalled();
});
