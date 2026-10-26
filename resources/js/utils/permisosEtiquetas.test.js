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
        expect(etiquetaPermisoEnMatriz('control_pedidos.auditar.aprobar')).toBe('aprobar pedido');
        expect(etiquetaPermisoEnMatriz('control_pedidos.liberar_resguardo')).toBe('liberar resguardo');
        expect(etiquetaPermisoEnMatriz('control_pedidos.cedis.enviar')).toBe('confirmar recolección');
        expect(etiquetaPermisoEnMatriz('control_pedidos.delegado.importar')).toBe('importar guías');
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
    it('marca cambiar_despues_* y cierres de estación', () => {
        expect(esPermisoExcepcion('control_pedidos.direccion.cambiar_despues_remision')).toBe(true);
        expect(esPermisoExcepcion('control_pedidos.direccion.cambiar_despues_guia')).toBe(true);
        expect(esPermisoExcepcion('control_pedidos.auditar.aprobar')).toBe(true);
        expect(esPermisoExcepcion('control_pedidos.liberar_resguardo')).toBe(true);
        expect(esPermisoExcepcion('control_pedidos.cedis.enviar')).toBe(true);
        expect(esPermisoExcepcion('control_pedidos.delegado.importar')).toBe(true);
        expect(esPermisoExcepcion('control_pedidos.direccion.cambiar')).toBe(false);
        expect(esPermisoExcepcion('control_pedidos.cedis')).toBe(false);
    });
});

describe('agruparPermisosPorSubmodulo', () => {
    it('separa control_pedidos en paneles del sidebar', () => {
        const permisos = [
            'control_pedidos.ver_listado',
            'control_pedidos.cedis',
            'control_pedidos.reabrir',
            'control_pedidos.cedis.enviar',
            'control_pedidos.auditar',
            'control_pedidos.auditar.aprobar',
            'control_pedidos.liberar_resguardo',
            'control_pedidos.delegado',
            'control_pedidos.delegado.importar',
            'control_pedidos.configurar_catalogos',
            'control_pedidos.configurar_plazos',
            'control_pedidos.direccion.seleccionar',
        ].map((name) => ({ name, id: name }));

        const grupos = agruparPermisosPorSubmodulo('control_pedidos', permisos);
        expect(grupos.map((g) => g.id)).toEqual([
            'registrar', 'auditar', 'cedis', 'delegado', 'catalogos', 'plazos',
        ]);
        expect(grupos.find((g) => g.id === 'otros')).toBeUndefined();
        expect(grupos.find((g) => g.id === 'cedis').permisos.map((p) => p.name))
            .toEqual(['control_pedidos.cedis', 'control_pedidos.cedis.enviar', 'control_pedidos.reabrir']);
        expect(grupos.find((g) => g.id === 'auditar').permisos.map((p) => p.name))
            .toEqual(['control_pedidos.auditar', 'control_pedidos.auditar.aprobar', 'control_pedidos.liberar_resguardo']);
        expect(grupos.find((g) => g.id === 'delegado').permisos.map((p) => p.name))
            .toEqual(['control_pedidos.delegado', 'control_pedidos.delegado.importar']);
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
        expect(descripcionPermiso('control_pedidos.auditar.aprobar')).toMatch(/aprobar/i);
        expect(descripcionPermiso('control_pedidos.cedis.enviar')).toMatch(/recog/i);
        expect(descripcionPermiso('control_pedidos.delegado.importar')).toMatch(/importar/i);
        expect(descripcionPermiso('control_pedidos.liberar_resguardo')).toMatch(/resguardo/i);
        expect(descripcionPermiso('control_pedidos.pagos.validar')).toMatch(/validar/i);
        expect(descripcionPermiso('control_pedidos.cancelacion_operativa.solicitar')).toMatch(/cancelaci/i);
        expect(descripcionPermiso('cobranza.ver')).toMatch(/cobranza|Credibox/i);
        expect(descripcionPermiso('rh.ver')).toMatch(/Humanos|RH/i);
    });

    it('cubre el catálogo completo de control_pedidos en submódulos y descripciones', () => {
        const catalogo = [
            'control_pedidos.auditar', 'control_pedidos.auditar.aprobar',
            'control_pedidos.bancos_departamento.administrar',
            'control_pedidos.cancelacion_operativa.concluir_admin',
            'control_pedidos.cancelacion_operativa.reactivar',
            'control_pedidos.cancelacion_operativa.resolver_financiera',
            'control_pedidos.cancelacion_operativa.solicitar', 'control_pedidos.cancelar',
            'control_pedidos.cedis', 'control_pedidos.cedis.enviar', 'control_pedidos.cedis.liberar',
            'control_pedidos.cedis.ver_todos', 'control_pedidos.configurar_catalogos',
            'control_pedidos.configurar_plazos', 'control_pedidos.crear', 'control_pedidos.delegado',
            'control_pedidos.delegado.importar', 'control_pedidos.direccion.cambiar',
            'control_pedidos.direccion.cambiar_despues_guia',
            'control_pedidos.direccion.cambiar_despues_remision', 'control_pedidos.direccion.seleccionar',
            'control_pedidos.direccion.usar_manual', 'control_pedidos.editar', 'control_pedidos.eliminados',
            'control_pedidos.eliminar', 'control_pedidos.eliminar_registro',
            'control_pedidos.envios.editar_costos_pago_validado', 'control_pedidos.envios.reabrir_caja',
            'control_pedidos.envios.retirar_caja', 'control_pedidos.espera_pago', 'control_pedidos.exportar',
            'control_pedidos.liberar_resguardo', 'control_pedidos.pagos.adjuntar',
            'control_pedidos.pagos.evidencia_historica', 'control_pedidos.pagos.rechazar',
            'control_pedidos.pagos.sustituir', 'control_pedidos.pagos.validar', 'control_pedidos.pagos.ver',
            'control_pedidos.preparacion.corregir', 'control_pedidos.preparacion.destinatario',
            'control_pedidos.preparacion.solicitar', 'control_pedidos.reabrir',
            'control_pedidos.salida.autorizar', 'control_pedidos.salida.confirmar_entrega',
            'control_pedidos.salida.validar_pago', 'control_pedidos.tienda.apartado.aprobar_prorroga',
            'control_pedidos.tienda.apartado.confirmar_devolucion', 'control_pedidos.tienda.apartado.separar',
            'control_pedidos.tienda.apartado.ver', 'control_pedidos.tienda.cargar_identificacion',
            'control_pedidos.tienda.confirmar_caratula', 'control_pedidos.tienda.despachar_municipio',
            'control_pedidos.tienda.empacar_municipio', 'control_pedidos.tienda.evidencias',
            'control_pedidos.tienda.generar_caratula', 'control_pedidos.tienda.imprimir_caratula',
            'control_pedidos.tienda.liberar', 'control_pedidos.tienda.regenerar_caratula',
            'control_pedidos.tienda.reportar_error', 'control_pedidos.tienda.responder',
            'control_pedidos.tienda.tomar', 'control_pedidos.tienda.trasladar', 'control_pedidos.tienda.ver',
            'control_pedidos.tienda.ver_identificacion', 'control_pedidos.ver_detalle',
            'control_pedidos.ver_listado',
        ];
        const grupos = agruparPermisosPorSubmodulo(
            'control_pedidos',
            catalogo.map((name) => ({ name, id: name })),
        );
        expect(grupos.some((g) => g.id === 'otros')).toBe(false);
        catalogo.forEach((name) => {
            expect(descripcionPermiso(name), name).toBeTruthy();
        });
    });

    it('agrupa todos los permisos de control_pedidos sin bloque Otros', () => {
        const nombres = [
            'control_pedidos.ver_listado', 'control_pedidos.crear', 'control_pedidos.pagos.ver',
            'control_pedidos.tienda.ver', 'control_pedidos.cancelacion_operativa.solicitar',
            'control_pedidos.cedis.ver_todos',
        ];
        const grupos = agruparPermisosPorSubmodulo(
            'control_pedidos',
            nombres.map((name) => ({ name, id: name })),
        );
        expect(grupos.some((g) => g.id === 'otros')).toBe(false);
        expect(grupos.find((g) => g.id === 'pagos')?.permisos.map((p) => p.name))
            .toContain('control_pedidos.pagos.ver');
    });
});

describe('agruparModulosPorSeccionSidebar', () => {
    it('ordena módulos según secciones del sidebar', () => {
        const agrupados = {
            facturas: [{ name: 'facturas.ver_listado' }],
            saldos_favor: [{ name: 'saldos_favor.ver' }],
            control_pedidos: [{ name: 'control_pedidos.cedis' }],
            usuarios: [{ name: 'usuarios.gestionar' }],
        };
        const secciones = agruparModulosPorSeccionSidebar(agrupados);
        expect(secciones.map((s) => s.id)).toEqual(['logistica', 'finanzas', 'sistema']);
        expect(secciones[0].modulos[0].modulo).toBe('control_pedidos');
        expect(secciones[0].modulos[0].label).toBe('Gestión de pedidos');
        expect(secciones[0].label).toBe('Logística');
        expect(secciones[1].label).toBe('Finanzas');
        expect(secciones[1].modulos.map((m) => m.modulo)).toEqual(['facturas', 'saldos_favor']);
        expect(secciones.some((s) => s.id === 'otros')).toBe(false);
    });

    it('agrupa permisos PDV bajo sección Punto de venta', () => {
        const agrupados = {
            punto_venta: [{ name: 'punto_venta.acceder' }],
            pdv: [{ name: 'pdv.resguardos.ver' }],
            facturas: [{ name: 'facturas.ver_listado' }],
        };
        const secciones = agruparModulosPorSeccionSidebar(agrupados);
        const puntoVenta = secciones.find((s) => s.id === 'punto_venta');
        expect(puntoVenta?.label).toBe('Punto de venta');
        expect(puntoVenta?.modulos.map((m) => m.modulo)).toEqual(['punto_venta', 'pdv']);
        expect(secciones.some((s) => s.id === 'otros')).toBe(false);
    });

    it('coloca reportes en la sección Reportes y deja lo no mapeado como pendiente', () => {
        const secciones = agruparModulosPorSeccionSidebar({
            reportes: [{ name: 'reportes.ventas.ver' }],
            modulo_sin_menu: [{ name: 'modulo_sin_menu.ver' }],
        });
        expect(secciones.map((seccion) => seccion.id)).toEqual(['reportes', 'otros']);
        expect(secciones[1].descripcion).toContain('Pendiente de clasificación');
    });
});
