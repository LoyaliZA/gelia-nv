/** Un resguardo en tienda se entrega a la sucursal; «Marcar enviado» sugeriría un envío regular. */
export function etiquetaMarcarEnviado(pedido) {
    return pedido?.es_resguardo ? 'Enviado a sucursal' : 'Marcar enviado';
}
