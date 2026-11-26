import { describe, it, expect, afterEach } from 'vitest';
import {
    AJUSTES_OPEN_EVENT,
    AJUSTES_TAB_DEFAULT,
    abrirAjustes,
    normalizarTabAjustes,
} from './ajustesEvents';
import {
    capturarInstantaneaTema,
    construirTemaVisual,
    huellaAjustes,
    resolverAcento,
    restaurarInstantaneaTema,
} from './ajustesTema';

function almacenFalso(inicial = {}) {
    const datos = { ...inicial };
    return {
        datos,
        getItem: (k) => (k in datos ? datos[k] : null),
        setItem: (k, v) => { datos[k] = String(v); },
        removeItem: (k) => { delete datos[k]; },
    };
}

const estadoBase = {
    color: 'azul', dark: true, bg: 'blob', font: 'mono', scale: 1, glass: false,
    layout: 'professional_left', mobileLayout: 'mobile_bottom', sidebarMode: 'expanded', fixedPosition: 'left',
    contentDensity: 'completo', contentMaxRem: 80, contentPaddingRem: 1,
    alertas: { canales: { sonido: true }, tipos: {}, tono_id: 'default', mensajeria_voz: 'solo_aviso' },
};

describe('eventos de Ajustes', () => {
    afterEach(() => { delete globalThis.window; });

    it('normaliza pestañas desconocidas a Apariencia', () => {
        expect(normalizarTabAjustes('alertas')).toBe('alertas');
        expect(normalizarTabAjustes('navegacion')).toBe('navegacion');
        expect(normalizarTabAjustes('otra')).toBe(AJUSTES_TAB_DEFAULT);
        expect(normalizarTabAjustes(undefined)).toBe(AJUSTES_TAB_DEFAULT);
    });

    it('abrirAjustes emite el evento con la pestaña normalizada', () => {
        const ventana = new EventTarget();
        globalThis.window = ventana;
        const recibidos = [];
        ventana.addEventListener(AJUSTES_OPEN_EVENT, (e) => recibidos.push(e.detail.tab));
        abrirAjustes('alertas');
        abrirAjustes('inexistente');
        abrirAjustes();
        expect(recibidos).toEqual(['alertas', 'apariencia', 'apariencia']);
    });
});

describe('tema de Ajustes', () => {
    it('construye la carga tema_visual que valida ProfileController::update', () => {
        const tema = construirTemaVisual(estadoBase);
        expect(tema).toMatchObject({
            modo: 'dark',
            color_nombre: 'azul',
            fondo_base: 'blob',
            layout_sidebar: 'professional_left',
            sidebar_modo: 'expanded',
            densidad_contenido: 'completo',
            efecto_cristal: false,
        });
        expect(tema.alertas_prefs).toEqual(estadoBase.alertas);
    });

    it('el sidebar profesional fuerza barra superior en móvil; los demás respetan la elección', () => {
        expect(construirTemaVisual(estadoBase).layout_sidebar_mobile).toBe('mobile_topbar');
        const flotante = construirTemaVisual({ ...estadoBase, layout: 'floating_left' });
        expect(flotante.layout_sidebar_mobile).toBe('mobile_bottom');
    });

    it('marca el fondo como «subiendo_archivo» cuando hay una imagen pendiente', () => {
        expect(construirTemaVisual(estadoBase, { archivoFondo: true }).fondo_base).toBe('subiendo_archivo');
    });

    it('la huella cambia solo cuando cambia algo editable', () => {
        const base = huellaAjustes(estadoBase);
        expect(huellaAjustes({ ...estadoBase })).toBe(base);
        expect(huellaAjustes({ ...estadoBase, color: 'verde' })).not.toBe(base);
        expect(huellaAjustes({ ...estadoBase, alertas: { ...estadoBase.alertas, tono_id: 'otro' } })).not.toBe(base);
    });

    it('resuelve acentos con nombre, hex y valor desconocido', () => {
        expect(resolverAcento('verde')).toBe('#10b981');
        expect(resolverAcento('#123456')).toBe('#123456');
        expect(resolverAcento('nada')).toBe('#ec4899');
        expect(resolverAcento('')).toBe('#ec4899');
    });

    it('descartar restablece exactamente lo capturado, incluidas las claves que no existían', () => {
        const almacen = almacenFalso({ theme: 'light', theme_color: 'rosa' });
        const instantanea = capturarInstantaneaTema(almacen);
        expect(instantanea.theme).toBe('light');
        expect(instantanea.theme_font).toBeNull();

        almacen.setItem('theme', 'dark');
        almacen.setItem('theme_font', 'inter');
        restaurarInstantaneaTema(instantanea, almacen);

        expect(almacen.getItem('theme')).toBe('light');
        expect(almacen.getItem('theme_color')).toBe('rosa');
        expect(almacen.getItem('theme_font')).toBeNull();
    });
});
