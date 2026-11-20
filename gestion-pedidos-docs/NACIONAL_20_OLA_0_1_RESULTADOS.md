# Nacional 2.0 — resultados de Ola 0 y Ola 1

Corte del 20 de noviembre de 2026. Rama `develop`, sin commit, sin merge, sin rebase y sin push. Ola 2 no se inició.

## Archivos modificados

De este corte:

- `app/Services/ControlPedidos/FusionarSolicitudPreparacionPedido.php`
- `app/Models/ControlPedidos/PedidoBma.php`
- `app/Http/Requests/ControlPedidos/PedidoBmaRequestBase.php`
- `app/Services/ControlPedidos/SolicitarPesajePedidoBmaService.php`
- `app/Http/Controllers/ControlPedidos/PedidoBmaController.php`
- `app/Support/ControlPedidos/Nacional/CapturaNacional.php`
- `app/Support/ControlPedidos/Nacional/LecturaConsultaNacional.php`
- `app/Services/ControlPedidos/Nacional/RegistrarAjusteConsultaNacional.php`
- `routes/web/modules/control-pedidos/core.php`
- `resources/js/Pages/ControlPedidos/Partials/flujoCapturaPedido.js`
- `resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx`
- `resources/js/Pages/ControlPedidos/Partials/FormularioRegistrarTienda.jsx`
- `resources/js/Pages/ControlPedidos/Partials/adaptadoresConsulta.js`
- `resources/js/Pages/ControlPedidos/Partials/AjustePiezas.jsx`
- `resources/js/Pages/ControlPedidos/Nacional/FormularioRegistrarNacional.jsx`
- `resources/js/Pages/ControlPedidos/Nacional/SeguimientoSolicitudNacional.jsx`
- `docs/ai/modulos/REGISTRO_PEDIDOS.md`

Pruebas nuevas o ajustadas:

- `tests/Unit/ControlPedidos/MarcaNacionalCapturaTest.php`
- `tests/Feature/ControlPedidos/NacionalAltaConsultaTest.php`
- `resources/js/Pages/ControlPedidos/Nacional/FormularioRegistrarNacional.test.jsx`
- `resources/js/Pages/ControlPedidos/Partials/flujoCapturaPedido.test.js`
- `resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.seleccion.test.jsx`
- `resources/js/Pages/ControlPedidos/Municipal/continuidadRegistro.test.jsx`
- `resources/js/Pages/ControlPedidos/Municipal/envioDirectoMunicipal.test.jsx`

Quedaron sin tocar los archivos ya sucios de cliente, monto y permisos, y las políticas de conversión.

## Pruebas y resultados

PHPUnit con `APP_CONFIG_CACHE=/tmp/gelia-test-config-ola1.php` y `APP_ROUTES_CACHE=/tmp/gelia-test-routes-ola1.php`. La prueba de alta comprueba SQLite en memoria. 46 pruebas, 184 aserciones, en verde, 8.93 s:

- Seguro, paquetería pendiente, pesaje y cajas: baseline de Ola 0, otra vez en verde dentro de este filtro.
- Marca nacional: 4 pruebas. El histórico no recibe la marca; el alta nueva la guarda, usa `ENVIO_BODEGA_NORMAL` y no deja revisión sin pesaje.
- Alta nacional: el mismo pedido pasa de consulta a respuesta, anexo, reintento, conflicto 409 y cierre. La respuesta de CEDIS no cierra. El reintento no duplica el anexo. Con revisión sin pesaje, la consulta se rechaza.

Vitest, 25 pruebas en verde: formulario nacional, marca de captura, selección del modal, continuidad y elección vacía sin persistir. Otras 14 de conversión, confirmación y ajustes de Tienda, en verde.

Build `./vendor/bin/sail npm run build` publicado en 1.74 s. `public/hot` no quedó presente.

## Regresiones

Las suites de seguro, pesaje, cajas y Tienda citadas quedaron en verde. El destino nacional de una conversión sigue deshabilitado. Abrir o guardar un nacional histórico no escribe `captura_nacional`. No se ejecutó `migrate:fresh` ni `ALLOW_DESTRUCTIVE_DB`.

## Pendientes

Ola 2 no se inició: dirección, guías, `PAQ. PENDIENTE` completo, seguro, crédito y cobro siguen fuera.

## Implementado y probado

- La marca `compartida` entra solo en un alta nacional nueva y se conserva si el guardado posterior no la envía.
- La consulta usa paquetería comercial con peso. No abre terminal municipal ni el formulario legado.
- CEDIS puede responder unidades y el pedido sigue abierto. Ventas cierra en un paso distinto. El servicio de cierre quedó ejecutado en la prueba de alta.
- El anexo sin remisión permanece en el mismo pedido, pide otra respuesta y no crea tarea de preparación ni un pedido de Tienda.
- El mismo `solicitud_id` no duplica el documento. Una solicitud nueva con base obsoleta responde 409.
- Un nacional histórico, al leerse o al guardarse, no recibe la marca.

## No verificado

- Retiro de piezas de punta a punta. El servicio y el adaptador lo aceptan; la prueba de ciclo usó anexo.
- El botón «Cerrar consulta» dentro del modal. El cierre se probó por el servicio, no con un clic.
- Recorrido en navegador con dos sesiones.
- Anchos 375 px y 1280 px del modal.

## Pendiente bloqueado

- E04: si ya hay remisión o pago, el ajuste se rechaza. El complemento pagado no está hecho.
- E05, E11 y E15 no se implementaron. Nacional a Tienda no enciende la política de Tienda ni relaja el primer cierre.
- N-11: no hay tabla de remisión por caja. Una distribución asegurada inválida no se resolvió en este corte.
- Invalidación de guía del pedido y de las cajas, y el comportamiento completo de `PAQ. PENDIENTE`, quedan para Ola 2.

## Verificación de aceptación — 2026-11-20

HEAD `ba830404`. Sin commit, merge, rebase ni push. Ola 2 no se inició. El diseño no se replanteó.

Skill usada en esta verificación: `verification-before-completion`. No se ejecutó auditoría visual ni de dos sesiones.

### Archivos de esta verificación

- `tests/Feature/ControlPedidos/NacionalAltaConsultaTest.php`
- `resources/js/Pages/ControlPedidos/Nacional/SeguimientoSolicitudNacional.jsx`
- `resources/js/Pages/ControlPedidos/Nacional/SeguimientoSolicitudNacional.test.jsx`

El botón «Cerrar consulta» envía `modalidad_esperada: nacional` y, si la lectura trae `version_consulta` mayor que cero, también esa versión.

### Comandos y resultados

```bash
./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config-ola1e.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes-ola1e.php laravel.test php artisan test --filter=NacionalAltaConsultaTest
```

6 pruebas, 91 aserciones, en verde, 7.62 s. Incluye retiro HTTP, cierre HTTP y el candado de pago.

```bash
./vendor/bin/sail npm test -- resources/js/Pages/ControlPedidos/Nacional/SeguimientoSolicitudNacional.test.jsx
```

2 pruebas en verde. El clic envía el contexto, pide la lectura nueva y, ante 409 `vista_atrasada`, muestra el mensaje sin marcar el cierre.

```bash
./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config-ola1d.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes-ola1d.php laravel.test php artisan test --filter='MunicipalOrdinarioM01Test|RegistrarPedidosTiendaTest|CorteMismoDiaTiendaTest|ElegibilidadPesajePedidoTest|UnidadesRevisionConsultaTest::test_aplicar_una_unidad_no_toca_a_la_hermana_y_conserva_historial|SolicitudRegistrarTiendaTest::test_guardar_una_captura_deja_pendiente_a_la_hermana_sin_retiro_fisico'
```

64 pruebas en verde y 1 en rojo, 557 aserciones, 14.67 s. El rojo es `MunicipalOrdinarioM01Test::test_resolver_faltante_y_responder_actualizacion_permite_cierre_sin_pisar_flete`: tras retirar el faltante, el cierre respondió 200 y la prueba esperaba 422. `ActualizacionConsulta`, `AccionesDisponibilidadTiendaService`, `ConsultaMunicipal` y esa prueba no tienen diff contra `ba830404`. Un retiro municipal no deja actualización pendiente; solo un anexo sin responder la exige. No se cambió esa regla: no es comportamiento de Ola 1.

`./vendor/bin/sail npm run build` publicó el manifiesto. `public/hot` ausente.

### Retiro

`POST /control-pedidos/{id}/ajuste-nacional` con `tipo=retiro` deja el ajuste en el mismo pedido, guarda la evidencia y marca `consulta_actualizacion_pendiente`. El `POST /cerrar-consulta` siguiente responde 422 y `consulta_cerrada_at` sigue vacío. La respuesta anterior no autoriza el cierre.

### Cierre

Sin el permiso `control_pedidos.crear`, el cierre responde 403. Con permiso, `modalidad_esperada=municipal` responde 409 `contexto_obsoleto`. Una `version_consulta` distinta del historial responde 409 `vista_atrasada`. El contexto vigente cierra y la respuesta JSON trae `consulta_cerrada: true`.

### Aislamiento de unidades

No se duplicó. Ya lo cubren, y quedaron en verde en este filtro:

- `UnidadesRevisionConsultaTest::test_aplicar_una_unidad_no_toca_a_la_hermana_y_conserva_historial`
- `SolicitudRegistrarTiendaTest::test_guardar_una_captura_deja_pendiente_a_la_hermana_sin_retiro_fisico`

Responder una captura no escribe el resultado de su hermana.

### Candado de pago

El ajuste se rechaza si `numero_remision` tiene valor o si `pago_validado_at` tiene fecha. Cada condición basta por sí sola. Un `costo_envio` distinto de cero, sin remisión y sin `pago_validado_at`, no usa ese mensaje. El candado no crea el complemento.

### Sigue sin verificar

- Recorrido en navegador con dos sesiones.
- Anchos 375 px y 1280 px del modal.

### Sigue pendiente y bloqueado

Complemento con remisión o pago validado, E05, E11, E15, asignación de remisión por caja, guías y `PAQ. PENDIENTE` completo. Ola 2 no arrancó.

## Cierre Ola 2 — 2026-11-20

HEAD `ba830404`. Sin commit, merge, rebase ni push. Ola 3 no se inició. El formulario compartido y `FormularioRegistrarNacional.jsx` se conservan. No hay wizard ni legado nuevo.

Skills usadas: `systematic-debugging` para clasificar la incidencia municipal, sin corregirla; `test-driven-development` en la logística; `forms`, `labels` y `verbs` en el cambio de paquetería posterior. `executing-plans` se leyó y no se usó worktree ni cierre de rama, porque este corte no pide merge ni un plan nuevo. `verification-before-completion` con los comandos de abajo.

### Incidencia municipal

Con estos archivos restaurados a `ba830404` — controlador, request, modelo, fusión, pesaje y rutas — y el resto del árbol como estaba:

```bash
./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config-m01head.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes-m01head.php laravel.test php artisan test --filter=MunicipalOrdinarioM01Test::test_resolver_faltante_y_responder_actualizacion_permite_cierre_sin_pisar_flete
```

Falla: se esperaba 422 y el cierre respondió 200, en la línea 526. Es el mismo resultado que con el diff de Nacionales presente. No queda demostrada una interacción con esos archivos. Las reglas municipales no se modificaron. Sigue pendiente de clasificación. `composer.json` y los archivos de cliente ajenos seguían sucios durante esa corrida; no participan en el cierre municipal.

### Archivos de esta ola

- `app/Services/ControlPedidos/Direcciones/InvalidarGuiaPorCambioDireccion.php`
- `app/Services/ControlPedidos/Nacional/CambiarPaqueteriaNacional.php`
- `app/Support/ControlPedidos/Nacional/BarreraCreditoNacional.php`
- `app/Services/ControlPedidos/MarcarEnviadoPedidoBmaService.php`
- `app/Services/ControlPedidos/AsignarGuiaPedidoBmaService.php`
- `app/Services/ControlPedidos/GestionarRemisionPedidoBmaService.php`
- `app/Services/SaldosAFavor/RegistrarPagoPedidoBmaService.php`
- `app/Services/ControlPedidos/FusionarSolicitudPreparacionPedido.php`
- `app/Models/ControlPedidos/PedidoBma.php`
- `app/Http/Controllers/ControlPedidos/PedidoBmaController.php`
- `routes/web/modules/control-pedidos/core.php`
- `resources/js/Pages/ControlPedidos/Nacional/FormularioRegistrarNacional.jsx` y su prueba
- `tests/Feature/ControlPedidos/NacionalLogisticaCobroTest.php`
- `docs/ai/modulos/REGISTRO_PEDIDOS.md`, `DIRECCIONES.md` y `DELEGADO_GUIAS.md`

### Comandos y resultados

```bash
./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config-ola2g.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes-ola2g.php laravel.test php artisan test --filter=NacionalLogisticaCobroTest
```

11 pruebas, 100 aserciones, en verde, 7.78 s.

```bash
./vendor/bin/sail npm test -- resources/js/Pages/ControlPedidos/Nacional/FormularioRegistrarNacional.test.jsx
```

4 pruebas en verde.

```bash
./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config-ola2h.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes-ola2h.php laravel.test php artisan test --filter='NacionalAltaConsultaTest|MarcaNacionalCapturaTest|CalcularSeguroPedidoServiceTest|ControlPedidosPaqPendienteTest|CandadoCostoEnvioPedidoTest|SnapshotDireccionPedidoTest|ControlPedidosActualizarGuiasTest'
```

44 pruebas en verde y 1 en rojo, 208 aserciones, 10.33 s. El rojo es `SnapshotDireccionPedidoTest::test_enviar_crea_snapshot_y_proyecta_domicilio`: exige cerrar la consulta CEDIS antes de enviar. `EnviarPedidoBmaService` y `ValidacionCamposPedidoBma` no tienen diff contra HEAD. No se cambió esa regla. Seguro, paquetería pendiente, candado de costo, guías de Delegado, marca y alta nacional quedaron en verde.

`./vendor/bin/sail npm run build` publicó el manifiesto. `public/hot` ausente.

### Comportamiento probado

La invalidación deja `activo=false` la guía del pedido y de la caja no recolectada, limpia ambos rastreos y conserva el archivo. Confirmar la cancelación ante la paquetería solo marca ese hecho. Con el PDF inactivo, la salida responde que se requiere guía vigente y la caja no queda recolectada.

Antes de la salida, cambiar la paquetería invalida la guía, anula `pago_validado_at` y rechaza pasar un costo de 80 a cero. El reintento con la misma solicitud no vuelve a cambiarla. Después de la salida, o con una caja recolectada, el cambio se rechaza y guía y costo siguen iguales. `PAQ. PENDIENTE` conserva 70 y Delegado no asigna guía.

DHL, flete 200, mercancía 1000 y reexpedición de zona 150: el seguro queda en 75 y el costo de envío en 350. La reexpedición entra una vez y no forma parte de la base del seguro. Dos cajas de 10.10 y 10.25, sin seguro, totalizan 20.35.

El crédito con resultado `bloqueado` no captura remisión, no asigna guía, no marca la salida y no acepta una transferencia. Una caja sin peso no recibe guía. Sin permiso, `POST /paqueteria-nacional` responde 403. Con permiso y pedido ya salido, responde 422 y el rastreo no cambia.

El domicilio sigue en `ModalCambiarDireccion` y en el snapshot existente. El cambio de dirección usa la misma invalidación de guía.

### Sigue sin verificar

- Recorrido en navegador con dos sesiones.
- Anchos 375 px y 1280 px del modal.
- El modal de dirección y la carga de un PDF nuevo de Delegado, en navegador.

### Sigue pendiente y bloqueado

Complemento con remisión, E05, E11 y E15. El seguro de varias cajas se rechaza hasta la asignación de remisión por caja; esa tabla no se creó. La cancelación al día siguiente por crédito bloqueado no se automatiza. Ola 3 no arrancó. La incidencia municipal y el envío del snapshot que exige consulta cerrada quedan fuera de este corte.
