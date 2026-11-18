# Consultas compartidas — implementación y autoevaluación

Fecha: 18 de noviembre de 2026. Rama `develop`, HEAD `c63dd847`, con cambios locales de esta etapa. Etapas 1 y 2: [corte](M01_CORTE_ETAPAS_1_2.md). Diagnóstico: [propuesta](M01_AJUSTES_FINALES_CEDIS_ACTUALIZACION.md).

## Etapa 3

Núcleo documental y editor compartido. Tienda sigue en sus rutas. Municipal monta el mismo editor. Nacional queda definido y no tiene interfaz.

### Arquitectura

El editor `AjustePiezas` no conoce la modalidad. Recibe un adaptador con capacidades, bloqueos, revisión, evidencias vigentes y comandos. Las acciones visibles salen de esas capacidades.

| Adaptador | Estado en esta etapa |
| --- | --- |
| Tienda | Conectado a `anexo-tienda`, `retiro-piezas-tienda`, acciones rápidas, cierre y reapertura |
| Municipal | Mismo editor en el seguimiento de Editar pedido. Comandos reales sobre el núcleo, sin simular |
| Nacional | Contrato en `contratoConsultaNacional`. Sin pantalla y sin comandos |

Ventas registra el ajuste y el cierre comercial. CEDIS o Tienda responden la revisión. Una respuesta operativa no cierra la consulta. El sistema conserva versión, historial y autorización.

`PersistenciaAdjuntosConsulta` guarda archivos nuevos y, si el lote falla, borra los ya copiados. Rechaza escribir tipos de origen (`pdf_pedido`, remisión, guía, cotización). El cupo, la huella y la idempotencia siguen en `AdjuntosConsultaTienda` y `EjecutarSolicitudAjusteTienda`. No hay un segundo mecanismo de deduplicación.

Queda fuera del núcleo: pedido relacionado de Tienda, retiro automático de faltantes, pesaje, tareas, apartado y custodia.

### Archivos

Creados:

- `resources/js/Pages/ControlPedidos/Partials/AjustePiezas.jsx`
- `resources/js/Pages/ControlPedidos/Partials/adaptadoresConsulta.js`
- `app/Services/ControlPedidos/PersistenciaAdjuntosConsulta.php`

Modificados:

- `SeguimientoSolicitudTienda.jsx` reexporta el editor, lo mantiene montado al plegar y usa el adaptador de Tienda
- `Municipal/SeguimientoSolicitudMunicipal.jsx` monta el editor, el historial y la sustitución según capacidad
- `RegistrarAnexoTiendaService.php` y `RegistrarRetiroPiezasTiendaService.php` delegan el guardado. Si la modalidad es municipal, no crean pedido Tienda ni mueven el pedido a pesaje
- `ConsultaMunicipal.php` exige respuesta previa y reabre la decisión comercial sin cerrarla
- Pruebas de editor, seguimiento municipal, `MunicipalOrdinarioM01Test` y este informe
- `docs/ai/modulos/CONSULTAS_DISPONIBILIDAD.md` y `docs/ai/ESTADO.md`

### Contratos conservados

Rutas y JSON de Tienda no cambiaron: `anexo_sin_remision`, `retiro_piezas`, `solicitud_id`, `lote`, `ciclo_id`, `actualizacion_id`. Permisos vigentes: `control_pedidos.crear` y titularidad. Municipal reutiliza esas rutas; la política municipal vive en el servicio, no en un endpoint nuevo.

### Inventario municipal

| Operación | Situación |
| --- | --- |
| Consulta, respuesta, acciones rápidas, cierre y reapertura | Ya existían en M-01 |
| Anexo y retiro detallados | Implementados sobre el núcleo. No crean pedido Tienda, no generan tarea ni pesaje, no pisan el Wizerp |
| Sustitución | Sigue en acciones rápidas, visible solo si el adaptador la habilita |
| Cierre | Lo hace Ventas. Un anexo o retiro deja la aceptación sin vigencia y el cierre responde 422 |
| Foto nueva del conjunto al responder la actualización | Sigue exigiéndose en el servicio de respuesta. Pendiente de etapa 4 si la evidencia anterior sigue vigente |

### Contrato Nacional

Puntos de enganche previstos: `ModalFormPedidoLegado`, `ActualizarConsultaPedidoBmaService` y `solicitar-repesaje`. Debe conservar pesos, medidas, cajas, costos y documentos de transporte. No usar `area_revision = control_pedidos` para convertir un envío en consulta sin peso. No crear un relacionado Tienda. No montar el editor en esta etapa.

### Pruebas

Sail, cachés aisladas, SQLite en memoria. Sin suite completa ni `migrate:fresh`.

| Comprobación | Resultado |
| --- | --- |
| Vitest `ajustesRespuestaTienda` y `SeguimientoSolicitudMunicipal` | 7 casos, incluidos nota al plegar y editor municipal |
| `MunicipalOrdinarioM01Test`: anexo/retiro sin pedido Tienda, Wizerp intacto, cierre bloqueado, fallo parcial sin archivos ni referencias | 2 casos, 30 aserciones |
| `SolicitudRegistrarTiendaTest`: anexo, retiro, faltante, cupo e idempotencia | 9 casos, 97 aserciones |
| `RecorridoTiendaIntegralTest`: anexo con remisión | 2 casos, 23 aserciones |

### Evidencia funcional

Build publicado por Sail; no hay `public/hot`. El puerto 9193 de QA no estaba activo y no se levantó.

En el navegador del servidor compartido, ya con el build nuevo, se abrió Editar pedido de un envío municipal que aún espera respuesta de CEDIS. El seguimiento mostró la espera y no ofreció «Modificar pedido». No se envió anexo, retiro ni cierre. No se inspeccionó la consola de desarrollo. El plegado del editor y el envío quedaron cubiertos por Vitest y PHPUnit, no por ese pedido.

## Etapa 3.1

Revisión por unidad (SKU o captura), persistencia parcial y captura del conjunto al final del seguimiento. No se rehicieron adaptadores ni se abrió la etapa 4.

`UnidadesRevisionConsulta` materializa `unidad_id` antes de guardar. Una captura se responde sola; las hermanas siguen pendientes. Los anexos ya atendidos (`pendiente_revision=false` / `disponible`) no vuelven a pedirse. `documentos_soporte` y `documentos_peticion` no son unidades. Registrar detalle sigue en el editor de Productos con detalle, con `origen_unidad_id` explícito.

PHP del contenedor: usuario `sail`, uid/gid 1000. Los directorios `pedidos_bma/{14,20,27,38,40,42}` estaban `root:root` 755 y bloqueaban `store()`. Corrección de pruebas: `chown -R sail:sail` solo esos ids. No se tocó producción ni se usó `chmod 777`.

Sustitución, disponibilidad, anexo de atención y excepciones de unidad pasan por `PersistenciaAdjuntosConsulta`. Tipos Wizerp (`pdf_pedido`, remisión, guía, cotización) siguen rechazados.

### Riesgos y pendientes de etapa 4

- La respuesta de actualización todavía pide una captura nueva del conjunto. No se eliminó esa regla para no cambiar el contrato de CEDIS en esta etapa.
- El editor municipal llama las rutas históricas `anexo-tienda` y `retiro-piezas-tienda`. El comportamiento municipal está en el servicio.
- Plegar conserva el draft porque el editor no se desmonta. Si el seguimiento completo se desmonta, la captura local se pierde. No hay autoguardado.
- No se comprobó cámara, escáner ni dos sesiones en navegador.
- Etapa 4: circuito comercial municipal y retroalimentación CEDIS/PDV. Etapa 5: editor Nacional. Etapa 6: retirar duplicados.

Sin commit ni push.
