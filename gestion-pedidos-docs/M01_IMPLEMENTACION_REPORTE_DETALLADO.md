# M-01 — Reporte de implementación

Fecha: 14 de noviembre de 2026. Sin commit y sin push. Rama local `develop`.

Este cierre cubre el alta municipal, la consulta de disponibilidad y el expediente de consignatarios. No cierra M-01 completo: no hay carátula PDF ni logística CEDIS → PDV → terminal.

## 1. Estado inicial

- Rama `develop`, commit de partida `9e821679`. El árbol ya traía sin seguimiento `gestion-pedidos-docs/PROCESO PARA PEDIDOS.md`.
- `origin/develop` estaba tres commits adelante (`0155c780`, `129f9ac6`, `52208b50`: gráfica, contraseña visible y activos). No se hizo pull.
- Sail estaba disponible. Las pruebas usaron SQLite en memoria dentro del contenedor, con caché de configuración y rutas en `/tmp`. No se usó `migrate:fresh` ni `ALLOW_DESTRUCTIVE_DB`.

## 2. Fuentes y decisiones aplicadas

M-01 pide consulta, aceptación de Ventas, pago de lo exigible y remisión, con el flete posterior todavía abierto. El proceso operativo dice que la paquetería es la terminal y que la INE viaja con la carátula. Jaguar sale con leyenda PAGADO y el cliente paga el flete después.

El código anterior mezclaba el municipal en el formulario legado y en una tarea de Tienda, y trataba `costo_envio` nulo como cero. Esta implementación no usa ese formulario como base.

Decisiones aplicadas:

- Un pedido con `solicitud_preparacion_json.modalidad_entrega = municipal` abre el formulario nuevo. Si esa marca no está, sigue el legado.
- `consulta_municipal` guarda área, almacén, estado visible y cierre. `revision_disponibilidad` sigue guardando la respuesta, las evidencias y las excepciones. Se escriben en la misma operación.
- Responder no escribe `estatus_envio` ni `costo_envio`. Cerrar tampoco. `PESAJE_RESPONDIDO` puede avanzar por la máquina existente y no autoriza el pago.
- El diferido lo elige Ventas y solo si la paquetería tiene `permite_costo_diferido`. Si el guardado no envía `costo_transporte`, `ResuelveDatosPedidoBma` conserva el comportamiento anterior.
- La leyenda PAGADO o POR COBRAR vive en `finanzas_municipales` y no es el pago del cliente.
- El consignatario es una identidad estable. El teléfono y la identificación se versionan aparte. El pedido guarda los ids de la versión que usó.

## 3. Arquitectura

- Expediente: `app/Services/Clientes/Expediente/GestionarExpedienteClienteService.php`. Una fuente para Clientes y para el pedido.
- Alta: `RegistrarSolicitudMunicipalService`. No crea tarea de preparación.
- Consulta: `EnviarConsultaMunicipalService` y la rama municipal de `ResponderDisponibilidadInicialService`.
- Cierre: `CerrarConsultaPedidoBmaService::cerrarMunicipal`.
- Finanzas: `PoliticaFinancieraMunicipal`. No escribe `cubierto`.
- Etiqueta: `EtiquetaConsultaMunicipal`. Solo pedidos municipales.
- El estado compartido de la consulta es `app/Support/ControlPedidos/ConsultaMunicipal.php`.

`CoberturaPagoPedidoBmaService` no se modificó. El cero que hoy produce un `costo_envio` nulo sigue ahí para Tienda y Nacional. La política municipal no usa ese cero como transporte liquidado.

## 4. Archivos

### Creados

- `database/migrations/2026_11_14_120000_create_cliente_expediente_tables.php`. Tablas del expediente, eventos y permisos. Los asigna a Super Admin si el rol existe.
- `app/Models/ClienteConsignatario.php`. Identidad estable.
- `app/Models/ClienteConsignatarioVersion.php`. Nombre y teléfono versionados.
- `app/Models/ClienteDocumento.php`. Identificación en disco `local`. La ruta no sale en el JSON del modelo.
- `app/Models/ClienteExpedienteEvento.php`. Alta, cambio, sustitución y baja.
- `app/Services/Clientes/Expediente/GestionarExpedienteClienteService.php`. CRUD, versión y descarga autorizada.
- `app/Http/Controllers/Clientes/ExpedienteClienteController.php`. Ficha y JSON. El mismo controlador atiende Clientes y Gestión de Pedidos.
- `app/Support/ControlPedidos/ConsultaMunicipal.php`. Estado visible derivado de la revisión y del cierre.
- `app/Services/ControlPedidos/Municipal/RegistrarSolicitudMunicipalService.php`. Municipio, paquetería, leyenda, costo y copia del consignatario.
- `app/Services/ControlPedidos/Municipal/EnviarConsultaMunicipalService.php`. Área CEDIS o Tienda según `AlmacenPdv`. No escribe `estatus_envio`.
- `app/Services/ControlPedidos/Municipal/PoliticaFinancieraMunicipal.php`. Exigible, costo conocido o desconocido, cobertura y leyenda. Exige consulta cerrada.
- `app/Services/ControlPedidos/Municipal/EtiquetaConsultaMunicipal.php`. Textos «Consulta pendiente: CEDIS» y «Consulta pendiente: Tienda».
- `app/Http/Controllers/ControlPedidos/Municipal/SolicitudMunicipalController.php`. Guardar y enviar consulta.
- `app/Http/Controllers/ControlPedidos/Municipal/ConsultaMunicipalController.php`. Cola de Tienda y respuesta.
- `resources/js/Pages/ControlPedidos/Municipal/FormularioRegistrarMunicipal.jsx`. Alta. No cobra ni empaca.
- `resources/js/Pages/ControlPedidos/Municipal/SeguimientoSolicitudMunicipal.jsx`. Respuesta, excepciones y cierre.
- `resources/js/Pages/ControlPedidos/Municipal/ExpedienteConsignatario.jsx`. Selector usado en el alta y en Clientes.
- `resources/js/Pages/ControlPedidos/Municipal/ConsultasTienda.jsx`. Cola de disponibilidad en PDV.
- `resources/js/Pages/Admin/ExpedienteCliente.jsx`. Ficha administrativa.
- `tests/Feature/ControlPedidos/MunicipalOrdinarioM01Test.php`. Recorrido Jaguar y excepción pendiente.

### Modificados

- `database/seeders/data/permisos_actuales.php`. Permisos del expediente y `control_pedidos.municipal.responder_consulta`.
- `app/Models/Cliente.php`. Relación `consignatarios`.
- `routes/web/admin/clientes.php`. Rutas del expediente bajo `clientes.ver` y `clientes.expediente.ver`.
- `routes/web/modules/control-pedidos/core.php`. Rutas municipales antes del comodín del pedido, para que también las use quien opera el pedido sin entrar a la ficha administrativa.
- `resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.jsx`. Alta y reapertura municipal. Nacional y legado no cambian de destino.
- `resources/js/Pages/ControlPedidos/Cedis/Index.jsx`. La consulta municipal CEDIS abre el panel de disponibilidad, no el de pesaje.
- `resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis.jsx`. Botón «Consulta pendiente: CEDIS». No muestra empacar como paso de esa consulta.
- `resources/js/Pages/Admin/Clientes.jsx`. Enlace Expediente junto a Direcciones.
- `app/Services/ControlPedidos/ResponderDisponibilidadInicialService.php`. Rama sin tarea para municipal. Actualiza `revision_disponibilidad` y el estado de `consulta_municipal`. No escribe pesaje ni costo.
- `app/Services/ControlPedidos/CerrarConsultaPedidoBmaService.php`. El municipal valida la revisión en backend y no asigna `pesaje_listo`.
- `app/Services/ControlPedidos/ListarPedidosCedisService.php`. La bandeja Todos incluye consultas municipales CEDIS en estado pendiente o con excepciones.
- `app/Services/ControlPedidos/ResuelveDatosPedidoBma.php`. `MUNICIPIO_DIFERIDO` por elección explícita `costo_transporte=diferido`. Sin esa clave, el comportamiento anterior se conserva.
- `app/Services/ControlPedidos/ObtenerCatalogosPedidoBmaService.php`. El catálogo municipal incluye `permite_costo_diferido`.
- `app/Services/SaldosAFavor/RegistrarPagoPedidoBmaService.php`. Antes de registrar, la política municipal exige consulta cerrada.
- `app/Services/ControlPedidos/ValidarPagoPedidoBmaService.php`. Exige consulta cerrada y el importe exigible. Un diferido no se valida como transporte cubierto.
- `app/Services/ControlPedidos/GestionarRemisionPedidoBmaService.php`. Exige consulta cerrada y mercancía exigible cubierta.
- `app/Services/Reportes/PagosPedidos/RegistrarCierrePagoPedidoService.php`. En municipal agrega `metadata_snapshot.transporte_municipal`. Si el costo es desconocido y la columna `monto_envio` queda en 0, la marca `monto_envio_no_es_gratuito` lo dice. La columna no admite nulo.

## 5. Recorridos

### Registro municipal

Ventas elige Envío municipal en el alta de Tienda. El pedido ya guardado, con `modalidad_entrega = municipal`, abre `FormularioRegistrarMunicipal`. Ahí captura municipio, paquetería, consignatario vigente, leyenda y costo conocido o diferido. Guardar escribe el JSON y, si es diferido, `costo_envio` nulo, tipo `MUNICIPIO_DIFERIDO` y `estatus_envio = pendiente_regularizacion`.

### Consulta

Enviar consulta lee el almacén. Si es PDV, el área es `tienda` y la etiqueta es «Consulta pendiente: Tienda». Si no, el área es `cedis`. La fase puede pasar de borrador a `PESAJE_PENDIENTE`. `estatus_envio` no cambia. CEDIS la ve en su bandeja y responde con el permiso `control_pedidos.cedis`. Tienda la ve en `/control-pedidos/municipal/consultas-tienda` con `control_pedidos.municipal.responder_consulta`. La respuesta no aparta ni empaca.

### Cierre

Ventas cierra desde el seguimiento. El backend rechaza excepción pendiente, respuesta sustituida o revisión ausente. Una respuesta sin excepciones no abre el pago. Al cerrar se escriben `consulta_municipal.estado = cerrada` y `consulta_cerrada_at`. No se toca el costo ni `estatus_envio`.

### Pago conocido y diferido

Conocido: el importe entra al exigible de la política. Diferido: el exigible es la mercancía; el transporte queda desconocido y no cubierto; la leyenda puede ser PAGADO. Registrar un pago antes del cierre lo detiene el controlador existente (`requiereConsultaCerradaParaProceder`) y, si llegara al servicio, la política. Validar y asociar remisión también pasan por la política. La remisión no se genera en GELIA.

### Expediente

Desde Clientes, la ficha `/admin/clientes/{id}/expediente`. Desde el pedido, los mismos métodos bajo `/control-pedidos/municipal/clientes/{id}`. Crear, editar teléfono, sustituir identificación y descargar usan el mismo servicio. Cambiar el teléfono crea otra versión de datos y deja la identificación vigente en la misma identidad. El pedido conserva nombre, teléfono y `documento_id` de cuando se guardó.

## 6. Modelo de datos

- `cliente_consignatarios`: `cliente_id`, `activo`, baja lógica.
- `cliente_consignatario_versiones`: `nombre`, `telefono`, `vigente`, `version`, `anterior_id`.
- `cliente_documentos`: archivo en `local`, hash, `vigente`, `version`, `anterior_id`, baja lógica. Un archivo por versión. El pedido no copia el archivo.
- `cliente_expediente_eventos`: acción, usuario y detalle. No guarda la ruta ni el binario.
- El pedido no tiene columnas nuevas. Guarda `consulta_municipal`, `finanzas_municipales` y `expediente` dentro de `solicitud_preparacion_json`.
- No hay fecha de borrado permanente. Un documento citado sigue en disco privado después de la baja lógica.

## 7. Seguridad

Permisos: `clientes.expediente.ver`, `editar`, `documentos`, `ver_identificacion`, `eliminar`, `ver_historico` y `control_pedidos.municipal.responder_consulta`.

La ficha administrativa exige además `clientes.ver`. Desde el pedido, el servicio exige el permiso de la operación y que la persona pueda consultar o modificar ese pedido, del mismo cliente. Quien no cumple recibe error de validación. La descarga de una versión no vigente exige `ver_historico` o que ese documento sea el citado por un pedido que la persona puede consultar. No hay URL pública. El modelo oculta `ruta`.

Límite: el usuario no tiene almacén asignado. La cola de Tienda muestra las consultas de área `tienda` a quien tiene el permiso, sin filtrar por sucursal.

## 8. Pruebas ejecutadas

Entorno: `./vendor/bin/sail exec` con `APP_CONFIG_CACHE=/tmp/gelia-test-config.php` y `APP_ROUTES_CACHE=/tmp/gelia-test-routes.php`. SQLite `:memory:`.

1. `php artisan test --filter=MunicipalOrdinarioM01Test`. Primera corrida: 2 pruebas, 34 afirmaciones, correcto. Después de exigir que la excepción quede guardada antes de impedir el cierre, segunda corrida: 2 pruebas, 37 afirmaciones, correcto, unos 7 segundos.
   - Jaguar: alta de consignatario, identificación, solicitud diferida con leyenda PAGADO, pago rechazado antes del cierre, consulta, respuesta con foto, `estatus_envio` sigue `pendiente_regularizacion`, `costo_envio` sigue nulo, cierre, cambio de teléfono sin desvincular la INE, sustitución de INE sin cambiar el `documento_id` del pedido, y otra persona no lista el expediente.
   - Excepción `faltante`: el estado visible queda `excepciones_pendientes`, el flete no se toca y el cierre responde 422.
2. `php artisan test --filter=ControlPedidosEnvioDiferidoFase1Test`. 5 pruebas, correcto. El cálculo anterior de diferido y anexo no cambió.
3. `npm exec vitest run resources/js/Pages/ControlPedidos/Partials/ModalFormPedido.seleccion.test.jsx`. 3 pruebas, correcto. El alta de Tienda y el nacional siguen abriendo el legado.

No se ejecutó la suite completa de Control de Pedidos.

## 9. Interfaz

No se recorrió el navegador con una sesión real. No hay captura de guardar, responder CEDIS ni abrir el expediente en pantalla. Lo comprobado de interfaz es la prueba de Vitest del selector de formulario y la revisión del código de las vistas nuevas. Hace falta una pasada manual: alta municipal, expediente en Clientes y en el pedido, respuesta CEDIS, cola de Tienda y cierre.

## 10. Estado por funcionalidad

- Expediente CRUD, versiones y descarga autorizada: implementado. El recorrido de identidad, teléfono e INE quedó comprobado por la prueba de servidor. La ficha en navegador, no.
- Alta, borrador por reapertura de la marca `municipal`, consulta CEDIS, excepción y cierre: implementado y comprobado en la prueba de servidor, salvo la reapertura visual desde Registro, auxiliar y detalle.
- Cola de Tienda y permiso de respuesta: implementados. No hubo prueba HTTP de esa página ni de un almacén PDV.
- Costo diferido, leyenda y bloqueo de pago antes del cierre: implementado y comprobado en Jaguar.
- Costo conocido insuficiente o cubierto, validación del auxiliar y asociación de remisión: la política y los ganchos están en los servicios. No hubo prueba que recorra esos dos servicios hasta el final.
- Cierre de reporte con `monto_envio_no_es_gratuito`: implementado en `RegistrarCierrePagoPedidoService`. No se ejecutó un cierre real en la prueba.
- Carátula, empaque, traslado, salida, entrega en terminal y ficha de recolección: no implementados.
- M-02 a M-12: no implementados.

## 11. Limitaciones

- `CoberturaPagoPedidoBmaService` sigue contando un costo nulo como 0 para quien no pase por la política. Una pantalla vieja que solo lea `cubierto` puede verse cubierta en un diferido cuando la mercancía ya se pagó. La política y el metadato del cierre dicen que el transporte no está liquidado.
- La columna `monto_envio` del cierre no acepta nulo. El 0 del diferido se distingue solo con `transporte_municipal.monto_envio_no_es_gratuito`.
- No hay plazo de impago del flete, ni cargo a crédito del costo posterior, ni retención definitiva de la INE, ni decisión de incrustarla en la carátula.
- Los permisos nuevos quedan en el catálogo y, si existe, en Super Admin. Hay que asignarlos a Ventas, CEDIS y quien responda en Tienda.
- La cola de Tienda no filtra por sucursal de la persona.

## 12. Continuidad

Siguiente bloque, sin rehacer este modelo: generar la carátula leyendo `expediente` del pedido y la leyenda, sin tomar la ficha vigente. Después, preparación municipal, traslado CEDIS → PDV, recepción, salida y entrega en terminal como hechos distintos de `ENTREGADO`. P-01 puede usar el anexo de envío ya existente, con evidencia de terminal y comprobante del cliente en disco privado, mientras `pendiente_regularizacion` siga abierto.
