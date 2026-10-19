# Comisiones de empaque — casos pendientes de Gestión interna

Fecha: 18 de octubre de 2026, America/Mexico_City.

## Entrega y alcance

Se corrigieron dos casos sobre la implementación actual de Gestión interna → Comisiones de empaque. No se reconstruyó el módulo ni se añadieron funciones fuera de esas correcciones. Se conservaron cortes, pagos, revisiones, historial y la independencia respecto de Recursos Humanos. No se modificaron registros ni reglas operativas de pedidos, empaques o del reporte de pagos.

No hubo commit, push ni despliegue. No se usó `migrate:fresh` ni `ALLOW_DESTRUCTIVE_DB=1`. No se importaron históricos, no se registraron pagos reales y no se cambió la fecha de inicio operativa. Los cambios ajenos de GELIA AI no se modificaron ni se revirtieron.

Rama inicial y final: `main`. Commit de partida: `811f5fe9be42a282a67e23c215dd100b81809f0e`. El módulo sigue sin seguimiento en git; este encargo no creó una migración adicional.

**El módulo no se declara listo para un piloto controlado.** Falta el recorrido autenticado de escritorio y de anchura móvil. Esa conclusión es independiente de los pagos reales: este módulo no queda autorizado para pagar comisiones reales.

## Implementado

### Límites conservados

- El módulo no consulta ni altera Recursos Humanos.
- La sincronización sigue sin escribir en pedidos, empaques ni en el reporte de pagos.
- Un corte confirmado no se reescribe. Un cambio posterior queda como incidencia y, si corresponde, como ajuste.
- La excepción de resguardo se mantiene: un empaque de CEDIS y el empaque final de tienda pueden comisionar los dos. El mismo trabajo dentro de una sola área no se duplica.
- La clave de idempotencia sigue devolviendo la misma captura y rechazando la misma clave con datos distintos.

### Cancelación de un manual vinculado a un pedido

La comprobación de fuentes rechazaba cualquier unidad manual si el pedido tenía `cancelado_at`, aunque la persona responsable ya hubiera decidido conservar la comisión. La sincronización solo aplicaba la cancelación a unidades sincronizadas, así que el manual podía quedarse sin una revisión visible.

El hecho revisado quedó separado de la huella de captura. `HuellaFuenteComisionEmpaque::deCancelacion` identifica pedido y fecha de cancelación. La detección, la decisión y la comprobación del corte usan ese identificador. La huella de la captura manual no se compara con la de la cancelación.

- Sin decisión, la unidad queda fuera del corte.
- Una decisión vigente de conservar permite incluirla si cumple los demás requisitos.
- Una decisión de excluir la mantiene fuera.
- Repetir la actualización no deshace la decisión.
- Otra fecha de cancelación exige una revisión nueva. Una decisión sobre un hecho anterior no autoriza el corte.
- Si la cancelación ocurre antes de aceptar el manual, la sincronización la deja visible. Aceptar el registro no lo vuelve elegible hasta decidir conservar o excluir.
- Si el corte ya está confirmado, se conserva el original y se abre una incidencia.

### Duplicados de captura manual

La comparación anterior usaba área, referencia y día, y podía omitirse si no había pedido ni remisión. Dos fechas distintas no se trataban como posible coincidencia.

- Si el folio o la remisión corresponden a un solo pedido existente, la captura se vincula.
- Sin pedido vinculado hace falta folio o remisión para contrastar la captura.
- Sin una referencia verificable, el registro se guarda pendiente. La aceptación normal queda bloqueada hasta una revisión explícita por falta de referencia. La pantalla indica qué falta y qué debe resolver la persona revisora.
- La clave interna `manual:` no se usa como evidencia de que el trabajo sea distinto.
- **Conflicto:** misma área, misma referencia verificable y mismo día civil. Bloquea la aceptación normal.
- **Antecedente informativo:** misma área y misma referencia, con otra fecha. Se muestra en la revisión porque una fecha distinta no demuestra otro trabajo. No bloquea de forma permanente: un trabajo distinto se reconoce con motivo, responsable y la fecha de cada registro.
- La coincidencia se vuelve a comprobar al aceptar, no solo al crear la captura.
- Si después de aceptar aparece un empaque sincronizado de la misma área, las dos unidades no se suman. Un corte confirmado se conserva y el conflicto posterior queda como incidencia.

### Ajustes de pantalla usados por esas correcciones

En el acumulado, la revisión muestra conflicto o antecedente, con fecha, pedido, remisión, cantidad y situación. La captura manual explica cuándo hace falta pedido, folio o remisión. Los botones de guardar revisión, guardar el manual y registrar la decisión de cancelación se deshabilitan mientras la petición está en curso. No se rediseñó el módulo.

## Investigación del reporte de pagos

Se reprodujeron, con Sail y SQLite en memoria, estas pruebas del reporte de pagos de pedidos:

- `Tests\Feature\Reportes\CierresPagoPedidoBmaTest::test_validar_crea_cierre_v1`
- `Tests\Feature\Reportes\CierresPagoPedidoBmaTest::test_rechazo_revoca_cierre_y_revalidacion_crea_v2`

Las dos fallan con `InvalidArgumentException: Cada exhibición de pago debe incluir su comprobante`. El origen de la excepción actual es `RegistrarPagoPedidoBmaService.php:193`, llamado desde `ValidarPagoPedidoBmaService.php:54`. El fixture crea una exhibición `efectivo` sin `ruta_archivo`.

La comparación se hizo con `git show` de `cd1782d3362483afe6e36c8d3e55ac0d2c17952d` (29 de agosto de 2026, revisión que introdujo esas pruebas), sin mover el árbol de trabajo. En esa revisión, `assertPagoListoParaAvanzar` ya rechazaba una exhibición activa sin `ruta_archivo`, y el fixture tampoco lo tenía. El 2 de octubre de 2026 (`9e0c8fa`) el control pasó a depender de la forma de pago. Efectivo sigue exigiendo comprobante en `ClasificacionIngresoBancario` y en `config/reportes_pagos.php`. Aunque esa configuración marcara efectivo como exento, `CoberturaPagoPedidoBmaService` mantiene el mismo bloqueo para toda exhibición activa sin archivo.

El módulo de comisiones no modifica esos servicios ni esas pruebas. No se debilitó la validación de comprobantes. El fallo queda identificado como anterior y ajeno a este módulo: el dato de prueba no cumple el contrato de comprobante que ya existía al escribir la prueba.

## Entorno y esquema efectivos

| Elemento | Resultado observado |
|---|---|
| Aplicación | Laravel en Sail, entorno `local`, PHP del contenedor 8.4.25; el código se mantiene compatible con PHP 8.2 |
| URL configurada | `http://100.75.11.59:9080` |
| Conexión de trabajo | MySQL, host `mysql`, base `laravel` |
| Pruebas del módulo | SQLite `:memory:`, `RefreshDatabase`, mediante `./vendor/bin/sail artisan test` |
| Concurrencia | MySQL aislado `gelia_ce_test`, 344 tablas. `migrate` informó que no había migraciones pendientes. No se ejecutó `migrate:fresh` |
| Migración de este encargo | Ninguna. No hizo falta cambiar la estructura |
| `public/hot` | No existía al cerrar la comprobación |
| Datos de operación | No se usaron para crear cortes ni pagos de prueba |

## Recursos y navegador

- Se compiló el frontend con Vite 8.1.3. La compilación terminó y escribió `public/build`. No hubo despliegue.
- `npm run check:style` no pasa por literales hexadecimales en `resources/js/Pages/Profile/Edit.jsx`, `resources/js/Pages/Solicitudes/Partials/ModalFormSolicitud.jsx`, `resources/css/gelia/features/dashboard.css` y `resources/css/gelia/features/dashboard-queues.css`. Esos archivos no forman parte de este cambio. `npm run check:style-css` repite los dos CSS de dashboard.
- No se abrió una sesión autenticada. La aplicación en ejecución usa la base de trabajo `laravel`. No hay una instancia aislada con sesión autorizada, y crear cortes o pagos en esa base usaría datos de operación.
- La redirección a `/login` solo confirma que la ruta exige autenticación. No se usó como verificación de la pantalla.

## Verificado automáticamente

| Comprobación | Resultado final |
|---|---|
| `ComisionesEmpaqueTest` | **31 pruebas, 151 aserciones, pasan** |
| `RepartirCentavosComisionEmpaqueTest` | **2 pruebas, 7 aserciones, pasan** |
| Concurrencia MySQL `gelia_ce_test` | Captura, confirmación, confirmación contra clasificación y pago: las cuatro responden `VERIFICADO` |
| Compilación Vite | Termina |
| Guardias de estilos del proyecto | Fallan en archivos ajenos, citados arriba |
| Pruebas del reporte de pagos | **2 errores**, origen ajeno documentado arriba |

No se ejecutó toda la suite del repositorio. Los dos fallos del reporte de pagos no se atribuyen a este módulo y no se declaran corregidos.

Las pruebas nuevas del módulo cubren: cancelación anterior a la aceptación; cancelación posterior; conservar; excluir; sincronizaciones repetidas; otra fecha de cancelación; corte confirmado que no se reescribe; manual sin referencia; vínculo por folio de un pedido existente; misma referencia y mismo día; misma referencia en otra fecha reconocida como trabajo distinto; fecha capturada de forma incorrecta y visible como antecedente; coincidencia aparecida entre captura y aceptación; equivalente sincronizado después de aprobar; duplicado posterior a un corte confirmado. Las pruebas ya existentes de idempotencia, resguardo y de no sumar el manual con el empaque sincronizado de la misma área siguen pasando.

## Verificado visual y funcionalmente

No hubo recorrido autenticado. No hay capturas de escritorio ni de anchura móvil, ni comprobación de foco, filtros, importes, mensajes de error ni de botones durante el envío en una sesión real.

Los cambios de pantalla quedan limitados a los textos y controles necesarios para la falta de referencia, el conflicto, el antecedente y la deshabilitación durante el envío. No se revisaron en el navegador.

## Límite del recorrido funcional

No se recorrieron con una sesión autorizada y datos de prueba:

1. Cuenta sin áreas.
2. Cuenta de Tienda, limitada a su área.
3. Cuenta con autorización global, con ambas áreas.
4. Captura manual con pedido y referencias visibles.
5. Captura sin referencia suficiente y su revisión.
6. Detección y resolución de un posible duplicado.
7. Cancelación de un manual y decisión de conservar.
8. Preparación y confirmación del corte.
9. Pago parcial.
10. Exportación del corte y de sus saldos.
11. Actualizaciones repetidas que conservan las decisiones.

Las pruebas PHP cubren las reglas de esos casos en base aislada. No equivalen a un recorrido integrado con sesión, foco, filtros y anchuras de pantalla.

## Archivos de código modificados

Sobre el módulo ya presente en el árbol, sin seguimiento respecto de `811f5fe`:

- `app/Support/ComisionesEmpaque/HuellaFuenteComisionEmpaque.php`
- `app/Services/ComisionesEmpaque/ComprobarFuenteCorteComisionEmpaque.php`
- `app/Services/ComisionesEmpaque/ValorarUnidadComisionEmpaque.php`
- `app/Services/ComisionesEmpaque/GestionarUnidadComisionEmpaqueService.php`
- `app/Services/ComisionesEmpaque/SincronizarComisionesEmpaqueService.php`
- `app/Services/ComisionesEmpaque/ConsultarComisionesEmpaqueService.php`
- `app/Http/Controllers/GestionInterna/ComisionesEmpaqueController.php`
- `resources/js/Pages/GestionInterna/ComisionesEmpaque/Index.jsx`
- `tests/Feature/ComisionesEmpaque/ComisionesEmpaqueTest.php`

Adicional nuevo: este checkpoint. No se modificaron servicios, modelos ni pruebas del reporte de pagos. No se tocó GELIA AI.

## Riesgos y revisión pendiente

La persona revisora debe distinguir un conflicto del mismo día de un antecedente de otra fecha. Un trabajo legítimo en días distintos exige reconocimiento explícito; ya no entra por el solo hecho de tener otra fecha. Una captura sin pedido, folio ni remisión no entra al corte hasta la revisión explícita por falta de referencia.

La decisión de conservar solo cubre la cancelación identificada por pedido y `cancelado_at`. Otra cancelación vuelve a dejar la unidad fuera del corte. Un corte ya confirmado no cambia de folio ni de importe por esa revisión.

Pendientes concretos:

1. Disponer de una aplicación con base aislada y una sesión autorizada de prueba, sin usar la base `laravel` para cortes ni pagos.
2. Recorrer los once puntos de la sección anterior en escritorio y en una anchura móvil, incluidos foco, mensajes, filtros, importes, botones durante el envío y la ausencia de duplicados al reenviar.
3. Mantener el fallo del reporte de pagos como asunto externo. Corregirlo exige un encargo sobre ese contrato, no un cambio de comisiones de empaque.

## Reproducción de comprobaciones automáticas

```bash
./vendor/bin/sail artisan test --filter=ComisionesEmpaqueTest
./vendor/bin/sail artisan test tests/Unit/ComisionesEmpaque
./vendor/bin/sail artisan test --filter='CierresPagoPedidoBmaTest::test_validar_crea_cierre_v1|CierresPagoPedidoBmaTest::test_rechazo_revoca_cierre_y_revalidacion_crea_v2'
./vendor/bin/sail exec -e DB_DATABASE=gelia_ce_test laravel.test php artisan migrate --force --no-interaction --database=mysql
for caso in capturar confirmar confirmar-clasificar pagar; do
  ./vendor/bin/sail exec laravel.test php tests/Feature/ComisionesEmpaque/concurrencia.php "$caso"
done
npm run check:style
npx vite build
```

La comparación histórica del reporte de pagos, sin cambiar el árbol de trabajo:

```bash
git show cd1782d:app/Services/SaldosAFavor/RegistrarPagoPedidoBmaService.php
git show 9e0c8fa -- app/Support/Reportes/ClasificacionIngresoBancario.php
```

## Distinción de cierre

1. **Implementado:** conservación de una comisión manual solo cuando la decisión corresponde a la cancelación vigente; detección de duplicados con referencia verificable, conflicto, antecedente y nueva comprobación al aceptar.
2. **Verificado automáticamente:** 31 pruebas del módulo y 2 unitarias en verde; cuatro carreras en MySQL aislado verificadas. Compilación de Vite terminada.
3. **Identificado y no corregido:** los dos fallos del reporte de pagos. Son anteriores a este módulo y responden a un fixture sin comprobante frente a un contrato que ya exigía el archivo.
4. **Pendiente de entorno:** recorrido autenticado de escritorio y móvil. Sin esa comprobación, el módulo no está listo para un piloto controlado con datos de prueba y no queda autorizado para pagos reales.
