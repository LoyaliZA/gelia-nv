import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, it, expect } from 'vitest';
import AjustesApariencia from './AjustesApariencia';
import AjustesAlertas from './AjustesAlertas';
import AjustesNavegacion from './AjustesNavegacion';
import { AjustesFila, AjustesInterruptor, AjustesPaso, AjustesSegmentos } from './AjustesPrimitivas';
import { Sun } from 'lucide-react';

const noop = () => {};

function ajustesFalsos(extra = {}) {
    return {
        usuario: { name: 'Persona' },
        presets: [],
        fondos: [],
        tonos: [],
        estado: {
            color: 'azul', dark: false, bg: 'none', font: 'mono', scale: 1, glass: true,
            layout: 'professional_left', mobileLayout: 'mobile_topbar', sidebarMode: 'expanded', fixedPosition: 'left',
            contentDensity: 'personalizado', contentMaxRem: 80, contentPaddingRem: 1,
            alertas: {
                canales: { sonido: true, voz: true, escritorio: false, app: true },
                tipos: { nueva: false },
                tono_id: 'default',
                mensajeria_voz: 'solo_aviso',
            },
        },
        sinGuardar: false,
        guardando: false,
        comprimiendo: false,
        aviso: null,
        bgInputRef: { current: null },
        acciones: new Proxy({}, { get: () => noop }),
        ...extra,
    };
}

describe('Ajustes: render de los paneles', () => {
    it('Apariencia muestra temas, colores accesibles, fondos y los pasos del modo personalizado', () => {
        const html = renderToStaticMarkup(<AjustesApariencia ajustes={ajustesFalsos()} />);
        expect(html).toContain('Gelia Signature');
        expect(html).toContain('role="radiogroup"');
        expect(html).toContain('aria-label="azul"');
        expect(html).toContain('Ancho máximo');
        expect(html).toContain('Margen lateral');
        expect(html).toContain('Usar fondo del sistema');
    });

    it('Apariencia oculta ancho y margen cuando el abarcamiento no es personalizado', () => {
        const base = ajustesFalsos();
        const html = renderToStaticMarkup(
            <AjustesApariencia ajustes={{ ...base, estado: { ...base.estado, contentDensity: 'compacto' } }} />
        );
        expect(html).not.toContain('Ancho máximo');
    });

    it('Alertas expone interruptores con rol switch y estado, y deja el acordeón cerrado', () => {
        const html = renderToStaticMarkup(<AjustesAlertas ajustes={ajustesFalsos()} />);
        expect(html).toContain('role="switch"');
        expect(html).toContain('aria-checked="false"');
        expect(html).toContain('Tipos de alerta a recibir');
        expect(html).toContain('aria-expanded="false"');
        expect(html).not.toContain('id="ajustes-tipos-alerta"');
    });

    it('Navegación incluye el selector de tipo de sidebar', () => {
        const html = renderToStaticMarkup(<AjustesNavegacion ajustes={ajustesFalsos()} />);
        expect(html).toContain('Tipo de sidebar');
        expect(html).toContain('Profesional');
    });

    it('las primitivas llevan nombre accesible y botones de 44 px de lado (clase)', () => {
        const html = renderToStaticMarkup(
            <>
                <AjustesFila icon={Sun} title="Modo" subtitle="Claro u oscuro"><span /></AjustesFila>
                <AjustesInterruptor activo label="Sonido" onChange={noop} />
                <AjustesPaso nombre="Tamaño de letra" etiqueta="100 %" onMenos={noop} onMas={noop} />
                <AjustesSegmentos nombre="Modo" valor="a" opciones={[['a', 'A'], ['b', 'B']]} onChange={noop} />
            </>
        );
        expect(html).toContain('aria-label="Sonido"');
        expect(html).toContain('aria-label="Reducir tamaño de letra"');
        expect(html).toContain('aria-pressed="true"');
        expect(html).toContain('gelia-ajustes__paso-btn');
    });
});
