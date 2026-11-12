# Integridad Wizerp — Fase 1

Fecha: 10 de noviembre de 2026. Base: `develop`, `f87541656afc8d56b822ff84ccfa2009ddb9e70e`.

**Estado de entrega: PR en borrador, despliegue bloqueado.** No fusionar ni desplegar mientras no se resuelvan las dependencias descritas abajo. La aprobación de pruebas de importación no acredita conciliación documental ni reversión segura de beneficios históricos.

GELIA decide con sus reglas. El archivo importado es una entrada, no un control remoto. No hay API ni sincronización con el origen del reporte. Ese nombre se conserva aquí y en el incidente del 12 de noviembre de 2026.

## Contrato implementado

GELIA decide las transiciones de estado comercial. Una celda vacía no inactiva ni degrada. Pagos, cotizaciones y cierres no reactivan ni inactivan por inferencia. La autoridad de Escalonamiento se consulta mediante el servicio operativo; esta entrega no la activa. Cuando está activa, el archivo conserva monto y lista publicados por ese módulo, y no pisa un estado que GELIA no haya aceptado con una señal explícita. Se conserva la protección global preexistente del archivo; los cierres necesitan además autoridad sobre el cliente específico.

| Caso | Antes | Después |
| --- | --- | --- |
| Sin columna de lista | Positivo diferente podía reactivar | Conserva estado; actualiza monto válido y permite ascensos explícitos |
| Código vacío + monto ausente/vacío | Inactivaba | Omite estado y lista; conserva actualización parcial de otros campos |
| Código vacío + cero | Inactivaba salvo bloqueo/autoridad | No inactiva y no cambia la lista. El cero solo actualiza el monto autorizado. Ajuste del 12 de noviembre de 2026 |
| Código vacío + positivo | Primera/repetida podían terminar distintas | No es error ni baja. Actualiza el monto autorizado y conserva lista y estado. Ajuste del 12 de noviembre de 2026 |
| Código válido + monto vacío | Activaba salvo bloqueo/autoridad | Activa; aplica lista permitida; conserva monto |
| Código válido + cero | Activaba salvo bloqueo/autoridad | Activa; aplica lista permitida y monto autorizado |
| Código válido + positivo | Ascenso dependía del cambio de monto | Decisión determinista, incluyendo ascensos y exclusiones comerciales |
| Código desconocido/fraccionario | Podía activar sin resolver lista | Error explícito; equivalencias enteras `.0` existentes siguen admitidas |
| Monto inválido / `-` | Omitía / convertía a cero | Error explícito, sin coerción |
| Lista bloqueada | Impedía estado, pero podía reactivarse por monto | Conserva lista; permite estado y monto autorizados |
| Alta sin nombre/estado suficiente | Se omitía o nacía activa | Falla explícita; no crea registro |
| Repetir CSV | Podía cambiar estado/cobertura | Sin nueva escritura de cliente, historial o publicación si no hay cambios |
| Cabeceras duplicadas/alias en colisión | Ganaba la última columna | Rechaza archivo antes de escribir clientes/catálogos |
| Filas cortas/largas | Rellenaba/recortaba | Rechaza archivo completo antes de aplicar |
| Duplicado idéntico / contradictorio | Procesaba ambos | Omite repetición idéntica tras trim / rechaza archivo contradictorio |
| Fallo de fila | Podía conservar escrituras previas | Rollback de cliente, historial, auditoría de lista y publicación |
| CSV con solicitudes | Recalculaba coberturas por delta | No cambia solicitudes, marcas ni evidencias históricas |
| Cierre sin autoridad | Reiniciaba monto oficial | Bloqueado con rollback del cierre |
| Cierre con propuesta de inactividad | Aplicaba estado y PG | Bloqueado; requiere sincronización autoritativa |
| Alta indirecta desde incidencia/remisión | Creaba cliente activo sin estado | Bloqueada; importar primero cliente con estado explícito |

Montos: decimal exacto, punto decimal y hasta dos decimales, signo `$` opcional y comas únicamente en grupos válidos de miles. No se aceptan negativos, exponentes, `-`, coma decimal ni redondeo implícito. Límites conforme al esquema: venta `DECIMAL(12,2)`, crédito `DECIMAL(15,2)`. Debe verificarse el contrato definitivo del exportador Wizerp; no se cambió el significado temporal del acumulado.

## Persistencia y resultados

Validación estructural completa antes de tocar clientes/catálogos. Nombre obligatorio solo para alta; identificador requerido y formado por dígitos, conservando ceros iniciales. Nuevos clientes necesitan nombre y un código de lista válido. Un código vacío no crea el cliente ni se interpreta como baja: no se inventa activo ni inactivo. Una fila rechazada no detiene las demás.

MySQL utiliza un bloqueo asesor de sesión para excluir importaciones simultáneas, sin depender de una concesión de caché que expire a mitad del archivo. Cada fila consulta y bloquea el cliente dentro de su transacción. La restricción única mantiene la identidad ante carreras de alta. Los consumidores de SQLite de pruebas usan bloqueo de caché.

Historial monetario decimal, cambios de lista, publicación durable y progreso del registro se confirman junto a la fila. Los avisos móviles se difieren hasta commit; si falla solo el aviso, la publicación durable permite recuperar por cursor y se registra advertencia. Las notificaciones no acreditan entrega al dispositivo.

Errores de contenido permiten continuar. Fallos de infraestructura/programación detienen la carga, preservan las filas confirmadas y registran estado parcial/fallido y filas no aplicadas. No se promete rollback de todo el archivo. Una interrupción abrupta del proceso puede dejar `en_curso` como evidencia de ejecución incompleta; no debe interpretarse como éxito.

Resumen: leídas, creados, actualizados, sin cambios, omitidas, fallidas, activados, inactivados y cambios de lista. En cargas terminadas, leídas = creados + actualizados + sin cambios + omitidas + fallidas. En una interrupción se añaden las no aplicadas. Activaciones/inactivaciones incluyen altas con estado explícito. Cada error de fila registra fila, número de cliente, campo y causa. La respuesta inmediata limita a 100 mensajes; la auditoría persistente conserva el detalle completo en la ruta administrativa.

La migración es aditiva: estado/resumen/mensaje fatal de importación y campo del error. Los históricos mantienen valores nuevos nulos; no se inventan métricas antiguas ni se repara información retrospectivamente. La migración solo se ejecutó en entornos de pruebas.

## Bloqueos y Fase 2 obligatoria

1. **H4 no es conciliación resuelta.** El CSV dejó de inferir cobertura. Las marcas históricas permanecen, pero no acreditan documentos. `proyectarMonto` todavía depende de esas marcas: CSV antes/después de confirmar pago puede producir una proyección duplicada o insuficiente. El pago no incrementa el acumulado oficial, pero una proyección incorrecta puede afectar decisiones de lista/alertas. No desplegar ese recorrido hasta conectar conciliación documental idempotente y disponibilidad entre solicitudes.
2. **Procedencia y vigencia de beneficios/TAG.** Rechazo/vencimiento/cancelación todavía requieren resolución integral por propietario del beneficio para preservar ratificaciones y cambios posteriores. No se añadió una reversión indiscriminada ni se presenta esa protección como terminada.
3. **Inactivos y consumidores externos.** La proyección de lista devuelve PG para inactivos y solicitudes/pagos no conceden lista/tipo por esa vía. La lista almacenada bloqueada sigue intacta. Los consumidores que lean directamente la lista almacenada —incluido el contrato móvil actual— deben respetar el estado; no se certifica que todo consumidor externo ya lo haga. Verificar ese contrato antes del despliegue.
4. **Cierres y altas indirectas bloqueados.** La simulación mensual heredada todavía puede mostrar propuestas de inactividad. No se rediseñó; su aplicación queda impedida. Clientes faltantes de remisiones deben pasar por importación con estado explícito; la remisión por sí sola no basta para crearlos.
5. **Fecha de corte/versionado.** Repetición idempotente no evita que una fotografía antigua sobrescriba otra más reciente. Se requiere contrato temporal/versionado de Wizerp. No se cambió en esta fase.
6. **Alineación explícita de incidencias.** El recorrido administrativo heredado que copia monto/lista desde un resumen no se rediseñó; debe revisarse junto al contrato de autoridad antes de habilitar el conjunto en producción.

## Verificación reproducible

Todas las ejecuciones PHP/npm se realizan mediante Sail. No se usa la base operativa ni se activa autoridad en el servidor.

```bash
./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php laravel.test php artisan test tests/Feature/Clientes tests/Feature/Solicitudes tests/Feature/Escalonamiento/ProteccionWizerpCierreTest.php tests/Feature/Escalonamiento/AutoridadEscalonamientoTest.php tests/Feature/Escalonamiento/CierreEscalonamientoTest.php tests/Feature/Escalonamiento/ResolverIncidenciaEscalonamientoTest.php tests/Feature/Escalonamiento/ConciliacionSolicitudEscalonamientoTest.php tests/Feature/Mobile/MobileSyncChangesTest.php
./scripts/qa/wizerp-mysql.sh
./vendor/bin/sail exec -u sail -e VITE_OUT_DIR=/tmp/gelia-wizerp-build laravel.test npm exec vite build
./vendor/bin/sail exec -u sail laravel.test npm exec vite build -- --config scripts/qa/wizerp/vite.config.mjs
./vendor/bin/sail exec -u sail laravel.test node scripts/qa/wizerp/verificar.mjs
```

La tanda de integración final obtuvo **125 aprobadas y 5 fallidas, 532 aserciones**. Los mismos cinco fallos se reprodujeron sobre una copia temporal del commit base: búsqueda de Clientes por resolución de página Inertia; tres pruebas de importación de Direcciones con 403; una prueba de assets del formulario público. No se modificaron esas funcionalidades ni se ocultaron sus fallos. Una comprobación adicional dirigida cubre el límite monetario de crédito de su esquema.

**MySQL 8.4: 3 aprobadas, 15 aserciones.** Conexiones independientes para exclusión; proceso competidor con bloqueo real y actualización confirmada antes de leer; publicación/avisos comprobados antes y después de commit/rollback. Contenedor efímero sin volúmenes ni puertos publicados, eliminado al terminar. El runner protege nombre de host/base y no reutiliza un contenedor existente.

Se detectó además un defecto anterior en `down()` de `2026_10_06_220000_escalonamiento_cierre.php`: intenta eliminar una FK con nombre distinto del creado. No se modificó esa migración fuera de alcance. El entorno efímero usa migración inicial y truncado de pruebas, no rollback general del esquema heredado. Las comprobaciones de rollback de transacciones sí se ejecutaron.

Build aislado correcto, con aviso de chunks grandes. `public/build` y su manifiesto no se publicaron. Render del componente real de resumen con datos ficticios en 1440×1000 oscuro y 390×844 claro, sin desbordamiento horizontal; capturas y resultado en `/tmp/gelia-wizerp-evidencia` del contenedor. No acredita recorrido autenticado completo ni dispositivo móvil real.

Las dos pruebas originalmente fallidas se repararon conforme al contrato: reactivación monetaria ya no permitida; fixture de vencimiento con sus permisos explícitos. Se conserva prueba de que vencimiento/pago no restan/suman ventas oficiales.

## Archivos

Importación: `ImportarClientesWizerpService`, `ProcesarFilaClienteAction`, `ValidarArchivoClientesWizerp`, `MontoWizerp`, resolución de códigos y compatibilidad de reactivación.

Autoridad: edición y requests administrativos/API, historial, aplicación de cierre, proyección, resolución de altas desde incidencias y beneficios de solicitudes. Publicación móvil conserva el registro en transacción y difiere avisos.

Auditoría/UI: modelos de importación/error, migración aditiva, respuesta administrativa y flash, resumen de carga, modal de auditoría y estado de cliente de solo lectura. Pruebas de Clientes/Solicitudes/Escalonamiento y runner MySQL/render aislados acompañan el cambio. El diff del PR contiene el inventario completo.
