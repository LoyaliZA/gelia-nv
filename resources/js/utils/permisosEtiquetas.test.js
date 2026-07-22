import { describe, it, expect } from 'vitest';
import {
    etiquetaPermiso,
    etiquetaPermisoEnMatriz,
    esPermisoExcepcion,
    agruparPermisosPorSubmodulo,
    agruparModulosPorSeccionSidebar,
    descripcionPermiso,
    SUBMODULOS_UI_POR_MODULO,
} from './permisos';

describe('etiquetaPermisoEnMatriz', () => {
    it('omite la entidad cuando ya está en la fila', () => {
        expect(etiquetaPermisoEnMatriz('control_pedidos.direccion.seleccionar')).toBe('seleccionar');
        expect(etiquetaPermisoEnMatriz('control_pedidos.direccion.cambiar_despues_remision'))
            .toBe('cambiar despues remision');
    });

    it('aplica overrides de verbo', () => {
        expect(etiquetaPermisoEnMatriz('control_pedidos.cedis')).toBe('gestionar cedis');
        expect(etiquetaPermisoEnMatriz('control_pedidos.delegado')).toBe('asignar / actualizar guías');
    });
});

describe('etiquetaPermiso (fuera de matriz)', () => {
    it('conserva contexto entidad · acción', () => {
        expect(etiquetaPermiso('control_pedidos.direccion.seleccionar')).toBe('direccion · seleccionar');
    });

    it('aplica overrides de verbo', () => {
        expect(etiquetaPermiso('control_pedidos.cedis')).toBe('gestionar cedis');
    });
});

describe('esPermisoExcepcion', () => {
    it('marca solo los cambiar_despues_*', () => {
        expect(esPermisoExcepcion('control_pedidos.direccion.cambiar_despues_remision')).toBe(true);
        expect(esPermisoExcepcion('control_pedidos.direccion.cambiar_despues_guia')).toBe(true);
        expect(esPermisoExcepcion('control_pedidos.direccion.cambiar')).toBe(false);
        expect(esPermisoExcepcion('control_pedidos.cedis')).toBe(false);
    });
});

describe('agruparPermisosPorSubmodulo', () => {
    it('separa control_pedidos en paneles del sidebar', () => {
        const permisos = [
            'control_pedidos.ver_listado',
            'control_pedidos.cedis',
            'control_pedidos.auditar',
            'control_pedidos.delegado',
            'control_pedidos.configurar_catalogos',
            'control_pedidos.direccion.seleccionar',
        ].map((name) => ({ name, id: name }));

        const grupos = agruparPermisosPorSubmodulo('control_pedidos', permisos);
        expect(grupos.map((g) => g.id)).toEqual([
            'registrar', 'auditar', 'cedis', 'delegado', 'catalogos',
        ]);
        expect(grupos.find((g) => g.id === 'cedis').permisos.map((p) => p.name))
            .toEqual(['control_pedidos.cedis']);
        expect(grupos.find((g) => g.id === 'registrar').permisos.map((p) => p.name))
            .toContain('control_pedidos.direccion.seleccionar');
    });

    it('agrupa almacenes por submódulo real', () => {
        const permisos = [
            'almacenes.ver',
            'almacenes.productos.ver',
            'almacenes.inventarios.ver',
            'almacenes.costos.ver',
        ].map((name) => ({ name, id: name }));
        const grupos = agruparPermisosPorSubmodulo('almacenes', permisos);
        expect(grupos.map((g) => g.id)).toEqual(['grupo', 'productos', 'inventarios', 'costos']);
    });

    it('hace fallback por entidad si el módulo no tiene mapa', () => {
        expect(SUBMODULOS_UI_POR_MODULO.modulo_inexistente).toBeUndefined();
        const grupos = agruparPermisosPorSubmodulo('modulo_inexistente', [
            { name: 'modulo_inexistente.foo.crear', id: 1 },
            { name: 'modulo_inexistente.foo.editar', id: 2 },
        ]);
        expect(grupos.length).toBeGreaterThan(0);
        expect(grupos[0].permisos.length).toBeGreaterThan(0);
    });
});

describe('descripcionPermiso', () => {
    it('tiene descripción visible para permisos de control_pedidos y cobranza', () => {
        expect(descripcionPermiso('control_pedidos.cedis')).toMatch(/CEDIS/i);
        expect(descripcionPermiso('cobranza.ver')).toMatch(/cobranza|Credibox/i);
        expect(descripcionPermiso('rh.ver')).toMatch(/Humanos|RH/i);
    });
});

describe('agruparModulosPorSeccionSidebar', () => {
    it('ordena módulos según secciones del sidebar', () => {
        const agrupados = {
            facturas: [{ name: 'facturas.ver_listado' }],
            control_pedidos: [{ name: 'control_pedidos.cedis' }],
            usuarios: [{ name: 'usuarios.gestionar' }],
        };
        const secciones = agruparModulosPorSeccionSidebar(agrupados);
        expect(secciones.map((s) => s.id)).toEqual(['operaciones', 'finanzas', 'sistema']);
        expect(secciones[0].modulos[0].modulo).toBe('control_pedidos');
        expect(secciones[0].modulos[0].label).toBe('Gestión de pedidos');
        expect(secciones[1].label).toBe('Finanzas');
    });
});
