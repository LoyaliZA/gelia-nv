// @vitest-environment happy-dom
import React, { act, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import FormularioPublico from './FormularioPublico';

const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    useForm: (inicial) => {
        const [data, setDatos] = useState(inicial);
        return { data, setData: (campo, valor) => setDatos((previos) => ({ ...previos, [campo]: valor })), post, processing: false, errors: {} };
    },
}));

let host;
let root;
beforeEach(async () => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    vi.stubGlobal('route', (nombre) => nombre);
    post.mockReset();
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
    await act(async () => root.render(<FormularioPublico token="abcdefgh1234" cliente={{ nombre_enmascarado: 'A***', numero_enmascarado: '***50' }} accion_permitida="register_first_address" />));
});

afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

const campo = (etiqueta) => [...host.querySelectorAll('label')].find((l) => l.textContent.trim().startsWith(etiqueta))?.querySelector('input, textarea, select');
const escribir = async (elemento, valor) => act(async () => {
    Object.getOwnPropertyDescriptor(elemento.constructor.prototype, 'value').set.call(elemento, valor);
    elemento.dispatchEvent(new Event('input', { bubbles: true }));
});
const salir = async (elemento) => act(async () => { elemento.dispatchEvent(new FocusEvent('focusout', { bubbles: true })); });
const enviar = async () => act(async () => { host.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); });

async function completarDomicilio() {
    await escribir(campo('Nombre(s)'), 'Ana');
    await escribir(campo('Apellidos'), 'Prueba');
    await escribir(campo('Calle'), 'Calle Sol');
    await escribir(campo('Colonia'), 'Centro');
    await escribir(campo('Código postal'), '06000');
    await escribir(campo('Municipio'), 'CDMX');
    await escribir(campo('Estado'), 'CDMX');
}

it('muestra el teléfono con formato de teléfono aunque se pegue con lada y guiones', async () => {
    const telefono = campo('Teléfono del destinatario');
    expect(telefono.getAttribute('type')).toBe('tel');
    await escribir(telefono, '+52 (55) 1234-5678');
    expect(telefono.value).toBe('55 1234 5678');
});

it('avisa al salir del campo si el teléfono no tiene 10 dígitos y lo quita al corregirlo', async () => {
    const telefono = campo('Teléfono del destinatario');
    await escribir(telefono, '5512345');
    await salir(telefono);
    expect(host.textContent).toContain('Escriba un teléfono de 10 dígitos');
    await escribir(telefono, '5512345678');
    expect(host.textContent).not.toContain('Escriba un teléfono de 10 dígitos');
});

it('el teléfono vacío no es un error porque es opcional', async () => {
    const telefono = campo('Teléfono del destinatario');
    await salir(telefono);
    expect(host.textContent).not.toContain('Escriba un teléfono');
});

it('el código postal solo admite 5 dígitos y avisa si faltan', async () => {
    const cp = campo('Código postal');
    await escribir(cp, '06A00');
    expect(cp.value).toBe('0600');
    await salir(cp);
    expect(host.textContent).toContain('Escriba un código postal de 5 dígitos');
    await escribir(cp, '06000');
    expect(host.textContent).not.toContain('Escriba un código postal de 5 dígitos');
});

it('no envía con campos obligatorios vacíos y deja el foco en el primero', async () => {
    await enviar();
    expect(post).not.toHaveBeenCalled();
    expect(host.textContent).toContain('Complete este campo');
    expect(document.activeElement).toBe(campo('Nombre(s)'));
});

it('envía cuando los datos son válidos', async () => {
    await completarDomicilio();
    await escribir(campo('Teléfono del destinatario'), '5512345678');
    await enviar();
    expect(post).toHaveBeenCalledTimes(1);
    expect(post.mock.calls[0][0]).toBe('direcciones.publicas.store');
});
