import { expect, it } from 'vitest';
import { etiquetaMarcarEnviado } from './etiquetaEnvioCedis';

it('usa «Enviado a sucursal» cuando el pedido es resguardo en tienda', () => {
    expect(etiquetaMarcarEnviado({ es_resguardo: true })).toBe('Enviado a sucursal');
});

it('conserva «Marcar enviado» para un envío regular', () => {
    expect(etiquetaMarcarEnviado({ es_resguardo: false })).toBe('Marcar enviado');
    expect(etiquetaMarcarEnviado({})).toBe('Marcar enviado');
});
