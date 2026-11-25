import { expect, it } from 'vitest';
import { accionPrincipalTienda } from './tiendaUi';

it('una consulta municipal pendiente pide revisar la disponibilidad', () => {
    expect(accionPrincipalTienda({ tipo_fila: 'consulta_municipal', estado: 'PENDIENTE', pedido: {} }))
        .toEqual({ label: 'Revisar disponibilidad', atender: false });
});

it('una consulta municipal ya respondida o cerrada solo se puede ver', () => {
    expect(accionPrincipalTienda({ tipo_fila: 'consulta_municipal', estado: 'RESPONDIDA', pedido: {} }))
        .toEqual({ label: 'Ver', atender: false });
});
