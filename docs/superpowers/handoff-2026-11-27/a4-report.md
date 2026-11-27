# A4 – Recorrido de un miembro hasta Auditar (informe)

Archivo nuevo: tests/Feature/ControlPedidos/MultienvioNacionalRecorridoVentasTest.php (1 test, 58 aserciones). Sin cambios en código de producción.

## RED / GREEN
- RED 1: enviar a Auxiliar -> 422 "código postal, domicilio de entrega". Causa: fixture (el origen del escenario no trae esos campos; en producción los proyecta el snapshot de dirección). No es brecha de MaterializarEnvioMultienvio: se añadió codigo_postal/domicilio_entrega al origen del escenario.
- RED 2: listado de Auditar vacío. Causa: el alcance del Auxiliar es por departamento (VisibilidadPedidoBma::idsVendedoresVisibles). Fixture: vendedora y Auxiliar en el mismo departamento. Los miembros heredan vendedor_id, por lo que cumplen el alcance.
- GREEN: test completo. Regresión del filtro pedido: 206 passed, 2 skipped (1698 aserciones), 0 fallos.

## Brechas encontradas en MaterializarEnvioMultienvio
Ninguna: los tres miembros (origen + 2 nuevos) cotizan por /cotizacion-nacional/vista-previa y /paqueteria-nacional, se envían con PUT /enviar (estatus, pesaje_*, soporte copiado, snapshot de dirección, paquetería: todo aceptado), aparecen en control_pedidos.auditar.listado (tab PENDIENTES), abren su detalle y validar-pago de uno no valida a los otros.

## Números reales vs ejemplo del spec
Coinciden exactamente (DHL 2% de flete+mercancía + $51): seguros 65.00 / 63.40 / 61.60; totales 765.00 / 683.40 / 591.60 (mercancía 600/500/400, fletes 100/120/130). Vista previa == guardado (seguro, flete, total). total_mercancia por miembro sin mezcla; aplica_seguro=true en cada uno; 1 caja por miembro; BarreraSeguroCajaNacional no bloquea.

## Notas
- La decisión «activar» se hace con el servicio (escenario()); su ruta HTTP ya la cubre MultienvioNacionalHttpTest. Borrador y confirmar van por HTTP (PUT multienvio/borrador, POST multienvio/confirmar).
- El pago de mercancía se registra con RegistrarPagoPedidoBmaService (efectivo) antes de enviar; enviar exige cobertura. Registrar un pago extra en el miembro 2 no cambia el pendiente de los miembros 1 y 3.
- Ruta de validación de pago responde 302 (redirect) por no ser JSON.
