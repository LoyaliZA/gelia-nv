// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import PropuestaAccionCard from './PropuestaAccionCard';
import ResultadoAccionCard from './ResultadoAccionCard';
import { GeliaAiAviso } from './GeliaAiEstado';
import { estadoTarjetaDesdeServidor, etiquetaArchivo, mensajeErrorHttp, textoPrepararDeNuevo } from './geliaAiEstados';
import Index from '@/Pages/GeliaAi/Index';
import GeliaAiMarkdown from './GeliaAiMarkdown';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }));
vi.mock('@inertiajs/react', () => ({ Head: () => null }));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/GeliaLogo', () => ({ default: () => null }));

const propuesta = {
    id: '6f0c2a8e-1c1b-4b8e-9d55-2f6d6b5d8a10',
    accion: 'importar_costos',
    resumen_corto: 'Importar costos en CEDIS',
    advertencias: ['Se actualizarán 12 costos existentes.'],
    detalle: {
        operacion: 'Importar costos',
        almacen: 'CEDIS · Centro de distribución',
        archivos: [{ nombre: 'costos.xlsx', filas: 1200 }],
        efecto: 'Se enviará a la cola de importaciones de Almacenes.',
        confirmar: 'Confirmar importación',
    },
    expira_en: '2026-10-17T18:30:00Z',
    puede: true,
};

let container;
let root;
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = (nombre) => (nombre === undefined ? { has: () => true } : `/${nombre}`);
    Element.prototype.scrollIntoView = vi.fn();
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
    vi.clearAllMocks();
});

const boton = (texto) => [...container.querySelectorAll('button, a')].find((b) => b.textContent.trim() === texto);
const render = (el) => act(() => root.render(el));

describe('PropuestaAccionCard', () => {
    it('muestra operación, destino, archivos, advertencias y que aún no se ejecuta', async () => {
        await render(<PropuestaAccionCard propuesta={propuesta} estado="pendiente" />);
        const texto = container.textContent;
        expect(texto).toContain('Pendiente de confirmar');
        expect(texto).toContain('Importar costos');
        expect(texto).toContain('CEDIS · Centro de distribución');
        expect(texto).toContain('costos.xlsx');
        expect(texto).toContain('Se actualizarán 12 costos existentes.');
        expect(texto).toContain('Aún no se ha ejecutado.');
        expect(texto).not.toContain('importar_costos');
        expect(boton('Confirmar importación')).toBeTruthy();
    });

    it('bloquea el doble clic y se marca ocupada mientras ejecuta', async () => {
        const onConfirmar = vi.fn();
        await render(<PropuestaAccionCard propuesta={propuesta} estado="pendiente" onConfirmar={onConfirmar} />);
        await act(() => {
            boton('Confirmar importación').click();
            boton('Confirmar importación').click();
        });
        expect(onConfirmar).toHaveBeenCalledTimes(1);

        await render(<PropuestaAccionCard propuesta={propuesta} estado="ejecutando" onConfirmar={onConfirmar} />);
        expect(boton('Ejecutando…').disabled).toBe(true);
        expect(boton('Cancelar propuesta').disabled).toBe(true);
        expect(container.querySelector('section').getAttribute('aria-busy')).toBe('true');
    });

    it('explica una propuesta vencida y ofrece prepararla de nuevo sin confirmar', async () => {
        const onPrepararDeNuevo = vi.fn();
        await render(
            <PropuestaAccionCard
                propuesta={propuesta}
                estado="vencida"
                mensaje="La propuesta venció sin ejecutarse."
                onPrepararDeNuevo={onPrepararDeNuevo}
            />,
        );
        expect(container.textContent).toContain('Vencida · no se ejecutó');
        expect(container.textContent).toContain('La propuesta venció sin ejecutarse.');
        expect(boton('Confirmar importación')).toBeUndefined();
        await act(() => boton('Preparar de nuevo').click());
        expect(onPrepararDeNuevo).toHaveBeenCalledTimes(1);
    });

    it('sin permiso deshabilita la confirmación y lo explica sin claves internas', async () => {
        await render(<PropuestaAccionCard propuesta={{ ...propuesta, puede: false, permiso: 'almacenes.importar' }} />);
        expect(boton('Confirmar importación').disabled).toBe(true);
        expect(container.textContent).toContain('no tiene permiso');
        expect(container.textContent).not.toContain('almacenes.importar');
    });
});

describe('ResultadoAccionCard y avisos', () => {
    it('una importación en cola dice «En cola» y enlaza a importaciones', async () => {
        await render(<ResultadoAccionCard reporte={{ estado: 'en_cola', resumen: 'Importación enviada.' }} enlaceImportaciones="/imp" />);
        expect(container.textContent).toContain('En cola');
        expect(container.textContent).toContain('aún no se aplica');
        expect(boton('Ver importaciones').getAttribute('href')).toBe('/imp');
    });

    it('un listado generado ofrece su descarga', async () => {
        await render(<ResultadoAccionCard reporte={{ estado: 'completado', resumen: 'Listado listo.', conteos: { ok: 30, error: 2 }, download_url: '/descarga/1' }} />);
        expect(container.textContent).toContain('Completado');
        expect(container.textContent).toContain('2 con inconsistencia');
        expect(boton('Descargar listado').getAttribute('href')).toBe('/descarga/1');
    });

    it('el aviso de error se anuncia y lleva título, no solo color', async () => {
        await render(<GeliaAiAviso tono="error" titulo="No se pudo completar la consulta">Sin conexión.</GeliaAiAviso>);
        const aviso = container.querySelector('[role="alert"]');
        expect(aviso.textContent).toContain('No se pudo completar la consulta');
        expect(aviso.getAttribute('data-tono')).toBe('error');
    });

    it('traduce estados y errores del servidor', () => {
        expect(estadoTarjetaDesdeServidor('ejecutada')).toBe('usada');
        expect(estadoTarjetaDesdeServidor('ocupada')).toBe('procesando');
        expect(estadoTarjetaDesdeServidor('desconocido')).toBe('fallida');
        expect(etiquetaArchivo('costos')).toBe('Hoja de cálculo · Costos');
        expect(etiquetaArchivo('raro')).toBe('Hoja de cálculo');
        expect(textoPrepararDeNuevo(propuesta)).toBe('Prepara de nuevo: Importar costos en CEDIS');
        expect(mensajeErrorHttp({ response: { status: 422, data: { errors: { propuesta_id: ['Falta la propuesta.'] } } } }, 'x')).toBe('Falta la propuesta.');
        expect(mensajeErrorHttp({}, 'x')).toContain('No hay conexión');
    });
});

describe('Página GELIA', () => {
    it('personaliza la bienvenida sin enviar una consulta y prepara sugerencias editables', async () => {
        await render(<Index auth={{ user: { name: 'Ana' } }} configurado conversaciones={[]} />);
        expect(container.querySelector('h2').textContent).toContain('Hola, Ana.');
        const sugerencia = [...container.querySelectorAll('button')].find((b) => b.textContent.includes('Consultar inventario'));
        await act(() => sugerencia.click());
        expect(container.querySelector('textarea').value).toBe('Quiero consultar el inventario de ');
        expect(document.activeElement).toBe(container.querySelector('textarea'));
        expect(axios.post).not.toHaveBeenCalled();
    });

    it('no muestra un nombre inventado cuando la cuenta no tiene nombre', async () => {
        await render(<Index auth={{ user: { name: ' ', email: 'privado@example.test' } }} configurado />);
        expect(container.querySelector('h2').textContent).toContain('Hola, soy GELIA.');
        expect(container.textContent).not.toContain('privado@example.test');
    });

    const enviar = async (texto) => {
        const textarea = container.querySelector('textarea');
        await act(() => {
            Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set.call(textarea, texto);
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        });
        await act(async () => {
            container.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        });
    };

    const conPropuesta = async () => {
        axios.post.mockResolvedValueOnce({
            data: { reply: 'Preparé la importación.', conversacion_id: 7, propuesta_accion: propuesta, archivos: [] },
        });
        await render(<Index auth={{ user: {} }} configurado conversaciones={[]} />);
        await enviar('importa costos en CEDIS');
    };

    it('confirma solo con propuesta_id y muestra el resultado real', async () => {
        await conPropuesta();
        let resolver;
        axios.post.mockReturnValueOnce(new Promise((r) => { resolver = r; }));
        await act(() => boton('Confirmar importación').click());

        expect(axios.post).toHaveBeenLastCalledWith('/gelia_ai.acciones.ejecutar', {
            propuesta_id: propuesta.id,
            conversacion_id: 7,
            confirmado: true,
        });
        expect(container.querySelector('[role="status"]').textContent).toContain('Ejecutando la acción confirmada');
        expect(boton('Ejecutando…').disabled).toBe(true);

        await act(async () => resolver({ data: { mensaje: 'Importación enviada.', reporte: { estado: 'en_cola', resumen: 'Importación enviada a la cola.' } } }));
        expect(container.textContent).toContain('Confirmada');
        expect(container.textContent).toContain('En cola');
        expect(container.querySelector('[role="status"]')).toBeNull();
    });

    it('una propuesta vencida explica el motivo y prepara el texto sin reenviar', async () => {
        await conPropuesta();
        axios.post.mockRejectedValueOnce({ response: { status: 410, data: { message: 'La propuesta venció sin ejecutarse.', estado_propuesta: 'vencida' } } });
        await act(async () => boton('Confirmar importación').click());
        expect(container.textContent).toContain('La propuesta venció sin ejecutarse.');

        const llamadas = axios.post.mock.calls.length;
        await act(() => boton('Preparar de nuevo').click());
        expect(container.querySelector('textarea').value).toBe('Prepara de nuevo: Importar costos en CEDIS');
        expect(axios.post.mock.calls.length).toBe(llamadas);
    });

    it('cancelar invalida la propuesta en el servidor', async () => {
        await conPropuesta();
        axios.post.mockResolvedValueOnce({ data: { ok: true, estado_propuesta: 'cancelada' } });
        await act(async () => boton('Cancelar propuesta').click());
        expect(axios.post).toHaveBeenLastCalledWith('/gelia_ai.acciones.cancelar', { propuesta_id: propuesta.id, conversacion_id: 7 });
        expect(container.textContent).toContain('Cancelada · no se ejecutó');
        expect(boton('Confirmar importación')).toBeUndefined();
    });

    it('sin respuesta del servidor no ofrece repetir la acción', async () => {
        await conPropuesta();
        axios.post.mockRejectedValueOnce(new Error('Network Error'));
        await act(async () => boton('Confirmar importación').click());
        expect(container.textContent).toContain('pudo haberse ejecutado');
        expect(boton('Confirmar importación')).toBeUndefined();
        expect(boton('Preparar de nuevo')).toBeUndefined();
    });

    it('un error de consulta se muestra como aviso con opción de volver a enviar', async () => {
        axios.post.mockRejectedValueOnce({ response: { status: 500, data: { message: 'GELIA no respondió a tiempo.' } } });
        await render(<Index auth={{ user: {} }} configurado conversaciones={[]} />);
        await enviar('¿cuánto hay de 123?');
        const aviso = container.querySelector('[role="alert"]');
        expect(aviso.textContent).toContain('GELIA no respondió a tiempo.');
        expect(boton('Volver a enviar')).toBeTruthy();
    });
});

describe('Lectura de respuestas', () => {
    it('presenta listas y tablas semánticas y conserva el texto como datos seguros', async () => {
        await render(<GeliaAiMarkdown text={'## Inventario\n\n- **Producto A**\n- Producto B\n\n| SKU | Disponible |\n| --- | ---: |\n| ABC | 12 |\n\n<img src=x onerror=alert(1)>'} />);
        expect(container.querySelectorAll('ul li')).toHaveLength(2);
        expect(container.querySelector('li strong').textContent).toBe('Producto A');
        expect(container.querySelector('tbody td').textContent).toBe('ABC');
        expect(container.querySelectorAll('th[scope="col"]')).toHaveLength(2);
        expect(container.querySelector('img')).toBeNull();
        expect(container.textContent).toContain('<img src=x onerror=alert(1)>');
    });
});
