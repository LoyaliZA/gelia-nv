import { describe, expect, it } from 'vitest';
import { nombreVisibleArchivoPedido, vistaArchivoPedido } from './vistasArchivoPedido';

describe('vistas ligeras de pedidos', () => {
    it('selecciona una variante sin cambiar las URLs de descarga originales', () => {
        const url = '/control-pedidos/pedidos/12/documentos/34';
        expect(vistaArchivoPedido(url)).toBe(`${url}?vista=previa`);
        expect(vistaArchivoPedido(`${url}?otra=1#imagen`, 'miniatura')).toBe(`${url}?otra=1&vista=miniatura#imagen`);
        expect(vistaArchivoPedido(`${url}?vista=previa`, 'miniatura')).toBe(`${url}?vista=miniatura`);
    });
    it('admite los documentos de preparación, solicitud y evidencias móviles', () => {
        for (const url of ['/control-pedidos/pedidos/1/preparacion/2/documentos/3', '/control-pedidos/tienda/tareas/1/solicitud/2', '/control-pedidos/tienda/tareas/1/evidencia/2/descargar', '/control-pedidos/cedis/1/sesion-evidencia/fotos/2', '/cedis-evidencia/abc/fotos/1', '/tienda-evidencia/abc/fotos/1']) {
            expect(vistaArchivoPedido(url, 'miniatura')).toBe(`${url}?vista=miniatura`);
        }
    });
    it('conserva archivos locales, otros módulos y URLs firmadas', () => {
        for (const url of ['blob:local', 'data:image/png;base64,a', '/facturas/1/documentos/2', 'https://cdn.test/foto.jpg?firma=123', undefined]) {
            expect(vistaArchivoPedido(url)).toBe(url);
        }
    });
});


describe('nombres locales de evidencia', () => {
    it('acorta un UUID antes de subir sin alterar nombres descriptivos', () => {
        expect(nombreVisibleArchivoPedido('captura-123e4567-e89b-12d3-a456-426614174000.png', 2)).toBe('evidencia-3.png');
        expect(nombreVisibleArchivoPedido('Lista del cliente.pdf')).toBe('Lista del cliente.pdf');
        expect(nombreVisibleArchivoPedido('a'.repeat(40) + '.jpg')).toBe('evidencia-1.jpg');
    });
});
