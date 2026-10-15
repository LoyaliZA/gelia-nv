# CHECKPOINT Q1 — Corrección verificada de confirmación de carátulas

Fecha: 14 de octubre de 2026. Base de aplicación: `861cb19d0e7ffbdb3695f9443723145e286fb8d1`.
Alcance: exclusivamente Q1/H07. Estado: **corrección implementada y verificada en entorno aislado; pendiente de revisión**. No se inició Q7.

## 1. Defecto reproducido

Se creó `tests/Feature/ControlPedidos/ConfirmarCaratulaIdentidadTest.php` antes de editar el servicio. Su primera prueba invoca el servicio real con tarea municipal LISTA_PARA_CARATULA versión 4, usuario con permiso real, carátula GENERADA versión 1 perteneciente a la tarea, archivo en almacenamiento falso y dos piezas preparadas. Usa modelos, relaciones, transiciones y persistencia reales sobre SQLite en memoria; no sustituye el servicio de confirmación.

La reproducción individual, con el código original, alcanzó la consulta de la carátula vigente y falló con:

```text
ErrorException: Undefined variable $caratulaId
app/Services/ControlPedidos/ConfirmarCaratulaColocadaService.php:47
Tests: 1, Assertions: 6, Errors: 1, Risky: 1
```

La traza identifica la rama interna de la transacción; no fue un error de tablas, permisos o bootstrap. El aviso adicional de handlers no retirados se produjo durante esta ejecución fallida y desapareció después del parche; no se utilizó como prueba del defecto funcional.

Antes del parche también se ejecutó el conjunto completo de 14 pruebas nuevas y 24 existentes relacionadas para conservar una comparación directa (§6).

## 2. Causa raíz e inspección del contrato actual

`ConfirmarCaratulaColocadaService::ejecutar` recibe `?int $caratulaId`, pero su closure transaccional solo capturaba tarea, usuario y versión esperada. La variable usada en el condicional interno no existía en ese ámbito. El manejador de Laravel convierte el aviso en `ErrorException`; desde HTTP el controlador captura el error y devuelve un redirect con mensaje, por lo que tampoco se confirma la operación válida.

Se inspeccionaron:

- `app/Http/Controllers/ControlPedidos/PedidoBmaTiendaController.php::confirmarCaratula`: `Request` genérico, permiso/visibilidad y conversión opcional de `version`/`caratula_id` a entero. No existe un FormRequest exclusivo para esta acción. Se conservó su interpretación actual de valores omitidos.
- `app/Models/ControlPedidos/PedidoBmaCaratula.php`: estados, pertenencia a tarea/pedido, versión, ruta PDF y datos de colocación. El esquema tiene unicidad tarea+versión.
- `TransicionEstadoTareaPreparacionService` y `MaquinaEstadosTareaPreparacion`: bloqueo de tarea, versión esperada, transición LISTA_PARA_CARATULA→RESPONDIDA e historial.
- `GenerarCaratulaPedidoService`, `RegenerarCaratulaPedidoService`, `InvalidarCaratulasVigentesService` y `PedidoBmaTareaPreparacionCaratulaObserver`: generación por versiones, invalidación y restricciones de una ya colocada. Ninguno se modificó.
- `ResponderPreparacionTiendaService::aplicarSincronizacionPedido`, `AsegurarCumplimientoFisicoService`, historial y notificación: efectos internos posteriores a la colocación y su reversión transaccional.
- Rutas municipales y pruebas existentes `EnvioMunicipioCaratulaTest`, `PreparacionTiendaP0Test`, `Fase5MunicipioEmpaqueTest`, `PreparacionTiendaWorkspaceTest`.

Las comprobaciones de pertenencia y vigencia ya estaban escritas: búsqueda mediante la relación de la tarea, estado GENERADA y coincidencia con la última GENERADA por versión. El defecto de alcance impedía ejecutarlas correctamente. No fue necesario cambiar el contrato operativo ni agregar una máquina o servicio.

## 3. Archivos modificados o creados

| Archivo | Cambio |
| --- | --- |
| `app/Services/ControlPedidos/ConfirmarCaratulaColocadaService.php` | Capturar `$caratulaId` en la closure; importar Storage y comprobar existencia del archivo asociado antes de colocar |
| `tests/Feature/ControlPedidos/ConfirmarCaratulaIdentidadTest.php` | Nueva cobertura de integración, 14 pruebas |
| `CHECKPOINT_Q1_CORRECCION_CARATULA_PEDIDOS.md` | Este informe |

No se modificaron el controlador, requests, modelos, generador, invalidación, permisos, despacho, modal, configuración ni pruebas existentes. Los documentos P0/P1/P1.1/P1.2 mantienen sus hashes de inicio (§9).

## 4. Solución aplicada

La closure incorpora `$caratulaId` en su lista `use`. Con ello se ejecutan las validaciones existentes del identificador:

1. La tarea debe estar LISTA_PARA_CARATULA.
2. Con id explícito, la carátula debe pertenecer a esa tarea, estar GENERADA y coincidir con la última GENERADA por versión.
3. Sin id explícito se conserva el fallback a la última GENERADA de la tarea.
4. Se exige ruta PDF y ahora también que exista en el disco `local`, igual que la comprobación de disponibilidad que ya usa la descarga del controlador. Una ruta registrada cuyo archivo desapareció no permite confirmar colocación.
5. La transición y todos los efectos de base de datos permanecen dentro de la transacción original. La validación existente de versión se mantiene en el servicio de transición; si rechaza, revierte también la actualización previa de la carátula.

El cambio productivo consiste en una importación y dos líneas ajustadas. No incorpora análisis binario de PDF, verificación nueva de hash ni renderizado en la confirmación; no se refactorizó el generador. La prueba de disponibilidad comprueba ruta/archivo, no certifica que un documento arbitrariamente corrupto sea renderizable.

## 5. Pruebas agregadas y oráculos

Todas están en `ConfirmarCaratulaIdentidadTest.php`. Las fixtures son sintéticas y no constituyen evidencia de producción.

| Caso solicitado | Prueba / comprobación |
| --- | --- |
| 1. Vigente | `test_confirma_caratula_vigente_y_sincroniza_transicion_historial_y_cumplimiento`: COLOCADA con actor/fecha, tarea RESPONDIDA v5, historial con id correcto, pedido sincronizado, dos revisiones y cumplimiento SEPARADA con dos piezas; notificación invocada una vez |
| 2. Id omitido | `test_endpoint_acepta_identificador_omitido_y_elige_la_ultima_generada`: HTTP sin caratula_id elige versión 2, conserva versión 1 y devuelve redirect de éxito actual |
| 3. Otra tarea | `test_endpoint_rechaza_caratula_de_otra_tarea_sin_alterar_datos`: HTTP con id ajeno devuelve mensaje de indisponibilidad, no un error genérico de variable |
| 4. Invalidada | `test_rechaza_caratula_invalidada_por_el_servicio_existente`: invalida usando el servicio real, luego exige rechazo por caratula_id |
| 5. Obsoleta | `test_rechaza_una_generada_anterior_a_la_vigente`: dos versiones GENERADA para alcanzar expresamente la defensa de obsolescencia, sin asumir que la generación normal deja dos vigentes |
| 6. Versión tarea incorrecta | `test_version_incorrecta_revierte_incluso_la_colocacion`: error por version y snapshot intacto |
| 7. Documento no disponible | `test_rechaza_identificador_inexistente`, `test_rechaza_tarea_sin_caratulas`, `test_rechaza_caratula_sin_ruta_pdf`, `test_rechaza_caratula_cuyo_archivo_pdf_no_existe` |
| 8. Sin permisos | `test_endpoint_sin_permiso_retorna_403_sin_transicion` y `test_servicio_conserva_su_validacion_de_permiso`: conservar tanto middleware/Gate como defensa interna; usuario sin confirmar, aunque puede ver |
| 9. Reintento | `test_reintento_conserva_rechazo_actual_sin_segunda_transicion_ni_historial`: segunda llamada rechazada por estado; no se convierte en nuevo éxito ni duplica hechos |
| 10. Fallo transaccional | `test_fallo_tardio_revierte_caratula_tarea_pedido_cumplimiento_e_historial`: notificador sustituido por fallo controlado después de verificar que ya ocurrieron los writes; rollback íntegro |

Los rechazos comparan snapshots ordenados de siete tablas: tarea, carátula, pedido, historial de tarea, historial comercial, revisiones de producto y cumplimiento físico. La prueba de fallo tardío comprueba dentro de la transacción que ya existen colocación, transición, revisiones, cumplimiento e historial antes de lanzar la excepción, y después confirma que la base vuelve al snapshot inicial. No basta con lanzar una excepción antes de escribir.

## 6. Entorno, ejecuciones y resultados comparados

### Aislamiento y preparación

PHP 8.4.25 y PHPUnit 12.5.25 en el contenedor existente `gelianv-laravel.test-1`, directorio `/var/www/html`. Cada ejecución fija `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` y `APP_ENV=testing`; `phpunit.xml` fuerza la conexión en memoria y `Tests\TestCase` aplica el override de configuración frente a configuración cacheada.

La prueba nueva comprueba **antes de RefreshDatabase** que la configuración y la variable de entorno son de testing y la conexión por defecto es SQLite en memoria. Solo entonces permite preparar el esquema efímero. Usa `Storage::fake('local')`, reloj fijo y notificador sustituido; conserva usuarios/permisos y servicios de dominio reales. Solo se omite protección CSRF en las solicitudes sintéticas, no autorización ni visibilidad.

No se conectó a una base operativa, no se modificaron archivos de entorno ni configuración productiva y no se ejecutaron migraciones sobre datos operativos. La preparación de esquema propia de RefreshDatabase ocurrió exclusivamente en memoria.

### Comandos reproducibles ejecutados

Prefijo común:

```sh
docker exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e APP_ENV=testing \
  -w /var/www/html gelianv-laravel.test-1 php vendor/bin/phpunit
```

Argumentos de la reproducción individual anterior al parche:

```text
tests/Feature/ControlPedidos/ConfirmarCaratulaIdentidadTest.php
--filter test_confirma_caratula_vigente_y_sincroniza_transicion_historial_y_cumplimiento
```

Argumento de la nueva cobertura posterior al parche:

```text
tests/Feature/ControlPedidos/ConfirmarCaratulaIdentidadTest.php
```

Mismo conjunto combinado antes y después:

```text
tests/Feature/ControlPedidos/ConfirmarCaratulaIdentidadTest.php
tests/Unit/ControlPedidos/EnvioMunicipioCaratulaTest.php
tests/Unit/ControlPedidos/PreparacionTiendaP0Test.php
tests/Unit/ControlPedidos/Fase5MunicipioEmpaqueTest.php
tests/Feature/ControlPedidos/PreparacionTiendaWorkspaceTest.php
```

| Ejecución | Resultado |
| --- | --- |
| Reproducción individual antes | 1 prueba, 6 aserciones, 1 error Undefined variable, 1 marcada risky por handlers; alcanza línea 47 original |
| Conjunto combinado antes | 38 pruebas, 167 aserciones; 11 errores, 3 fallos, 2 risky. En cobertura nueva: 10 errores por variable, 2 fallos HTTP derivados del mismo error y 2 casos de permisos aprobados. En existente: 1 error de bootstrap y 1 fallo de expectativa ya presentes |
| Nuevas después | **14 pruebas, 126 aserciones, todas aprobadas**, sin errores, fallos ni risky; incluye la reproducción inicialmente fallida |
| Mismo conjunto combinado después | **38 pruebas, 211 aserciones; 36 aprobadas, 1 error y 1 fallo preexistentes**, sin risky. Los 14 casos nuevos y 22 existentes pasan |
| Sintaxis PHP 8.4, ambos archivos PHP nuevos/modificados | Sin errores de sintaxis |
| `git diff --check` | Sin errores de espacios en diff rastreado |

### Intentos de preparación excluidos de la reproducción

El primer intento con PHP local se detuvo antes de PHPUnit: host PHP 8.2.32 frente a requisito >=8.3. Se utilizó el runtime 8.4 existente, sin instalar dependencias. Un primer guard de la prueba consultaba `app()->environment()` y detectó el binding `local` de bootstrap pese al override de testing de configuración; se corrigió **solo el guard del test nuevo** para verificar configuración efectiva, APP_ENV y conexión en memoria antes de RefreshDatabase. Ese intento se detuvo antes de preparar tablas y no se contó como reproducción funcional. No se alteró `Tests\TestCase` ni se borró configuración cacheada del entorno.

## 7. Compatibilidad conservada

- La firma del servicio y el endpoint permanecen iguales; caratula_id y version siguen siendo opcionales conforme al controlador actual.
- Sin identificador se usa la última GENERADA. No se agregó un obligatorio a clientes existentes.
- Se mantiene LISTA_PARA_CARATULA→RESPONDIDA, incremento de versión, actor/fecha, historial y sincronización actual de pedido/cumplimiento.
- Se mantienen estados y reglas de generación/regeneración/invalidación. La prueba de invalidada usa el servicio existente.
- Se conservan permiso específico, visibilidad y rechazo de segunda confirmación por estado. El reintento no agrega otro historial.
- Solo cambia adicionalmente el caso de ruta registrada sin archivo físico: se rechaza antes de escribir, porque no existe una carátula disponible para colocar. Usa el mismo disco y criterio de disponibilidad que la descarga actual.

## 8. Regresiones observadas y cobertura existente no verde

No se observaron regresiones nuevas en el conjunto relacionado comparado. **La suite combinada no está completamente verde** por estos dos casos que fallaron antes y después, sin modificar sus expectativas:

1. `EnvioMunicipioCaratulaTest::test_enmascarar_telefono`: espera `****5678`, pero el helper devuelve `******5678`. Es una discrepancia de expectativa/implementación de enmascaramiento, ajena al cambio de confirmación; no se atribuye a infraestructura ni se ajustó el teléfono para ocultarla.
2. `EnvioMunicipioCaratulaTest::test_render_pdf_texto_largo_acentos_smoke`: `Target [Illuminate\Contracts\View\Factory] is not instantiable`, línea 83. La clase hereda de PHPUnit puro y llama `view()` sin arrancar la aplicación Laravel. Es un problema preexistente de bootstrap de esa prueba; no demuestra un PDF roto ni valida el generador. Queda expresamente fuera del parche.

Las otras 22 pruebas existentes relacionadas pasan. No se ejecutó toda Gestión de Pedidos ni PDV porque este checkpoint está delimitado a confirmación de carátula y su cobertura inmediata.

## 9. Riesgos pendientes y límites

- La comprobación documental valida disponibilidad por ruta y archivo en disco local; no agrega un parser de PDF ni certifica contenido/hash. La fixture de archivo es sintética, no una validación del generador o de impresión.
- SQLite en memoria verifica persistencia y rollback; no acredita carreras MySQL ni entrega real de notificaciones externas. La notificación se sustituye para aislar efectos, comprobar invocación y forzar rollback tardío.
- La garantía transaccional comprobada cubre base de datos. No se afirma que una notificación externa ya enviada pudiera revertirse; no se cambió el sistema de eventos/notificaciones.
- Se conserva la coerción opcional de enteros del Request actual. Endurecer tipos o cambiar reintento a éxito idempotente sería un contrato distinto; no se hizo.
- Los dos fallos preexistentes de §8 siguen pendientes de revisión separada. No impiden demostrar y verificar este defecto, pero impiden presentar la cobertura relacionada como totalmente verde.
- No se evaluaron ni modificaron políticas de despacho, elegibilidad, evidencia/pesaje Aromas, captura, remisión o integración PDV en este paquete.

Hashes de documentos conservados, iguales al inicio de Q1:

```text
P0   8c8b7aef936cc2f3da0e7fae15b37fb45eb27cfdfa9c001ff009c41c5de8eaeb
P1   d4336aebb026a60e29e5ef797b21630084cc2ad3d9a23d7522713bbf64065bda
P1.1 45b40e9d1783f5972bb45e0b4a5c3ab6c72b4136dfd68d5c75d7d4d77ce58e9c
P1.2 9c51ae2cc5403771b24606cc9d8368f2d4fbb07afaecdd7e1a2c0b929eed58ce
```

## 10. Recomendación del siguiente checkpoint

Revisar este diff acotado y sus resultados. El siguiente checkpoint solicitado es **Q7: continuidad número de remisión→búsqueda Delegado→guías**, con fixture de identidades distintas, preservando filtros/permisos e importación por id como establece P1.2. Las cuestiones de captura y PDV mantienen sus paquetes propios; no se integran a este parche.

Q1 termina aquí. No se realizaron commits ni push, no se inició Q7 y no se aplicaron cambios ajenos al servicio, su nueva prueba y este informe.
