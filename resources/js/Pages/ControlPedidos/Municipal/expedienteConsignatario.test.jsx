// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, it, expect, vi } from 'vitest';
import Expediente from './ExpedienteConsignatario';
let root, host, documento;
const boton = texto => [...document.querySelectorAll('button')].find(el => el.textContent === texto);
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    documento = null;
    window.axios = { get: vi.fn(async () => ({ data: { permisos: { editar: true, documentos: true }, consignatarios: [{ id: 2, activo: true, version: { nombre: 'Persona ficticia', telefono: '3311111111' }, documento }] } })), post: vi.fn(async () => { documento = { id: 3, version: 1 }; return { data: { id: 3 } }; }) };
    host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(async () => root.unmount()); host.remove(); });
const montar = () => act(async () => root.render(<Expediente clienteId={1} pedidoId={4} seleccionado={2} onSeleccionar={() => {}} compacto />));
const adjuntar = () => act(async () => { const el = document.querySelector('input[type=file]'); Object.defineProperty(el, 'files', { configurable: true, value: [new File(['imagen'], 'identificacion.png', { type: 'image/png' })] }); el.dispatchEvent(new Event('change', { bubbles: true })); });
it('ofrece botones para adjuntar y sustituir, y separa el alta del guardado', async () => {
    await montar();
    expect(boton('Adjuntar identificación')).toBeTruthy();
    await adjuntar();
    expect(document.body.textContent).toContain('Identificación guardada.');
    expect(boton('Sustituir identificación')).toBeTruthy();
    await act(async () => boton('Agregar consignatario').click());
    expect(document.body.textContent).toContain('Nuevo consignatario');
    expect(boton('Guardar consignatario')).toBeTruthy();
});
it('un error técnico no expone rutas y permite volver a subir el archivo', async () => {
    await montar();
    window.axios.post.mockRejectedValueOnce({ response: { data: { message: 'Unable to create a directory at /var/www/html/storage/private' } } });
    await adjuntar();
    expect(document.querySelector('[role=alert]').textContent).toContain('No se pudo guardar la identificación');
    expect(document.body.textContent).not.toContain('/var/www');
    expect(boton('Adjuntar identificación').disabled).toBe(false);
});
