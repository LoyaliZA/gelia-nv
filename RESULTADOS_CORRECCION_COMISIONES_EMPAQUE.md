# Comisiones de empaque — resultados de la corrección

Rama `main`. No hay commit, push ni despliegue. No se reconstruyó el módulo ni se amplió su alcance. No se usó `migrate:fresh` ni `ALLOW_DESTRUCTIVE_DB=1`. No se importaron históricos, no se registraron pagos reales y no se cambió la configuración operativa de pedidos.

La migración inicial `2026_10_18_191000_create_comisiones_empaque` ya estaba aplicada. El ajuste de estructura es otra migración y también quedó aplicada en la base MySQL de Sail.

En el árbol siguen cambios de GELIA AI ajenos a este módulo. No se modificaron ni se revirtieron.

## Conclusión

Las reglas corregidas aguantan las pruebas automáticas y cuatro carreras reales en MySQL aislado. El recorrido autenticado en el navegador no se pudo ejecutar: la ruta redirige a `/login`.

El módulo no está listo para pagos reales. Antes de un piloto controlado hace falta ese recorrido de escritorio, definir la fecha de inicio y asignar áreas y permisos.

## Observaciones confirmadas

| Observación | En el código anterior | Corrección |
| --- | --- | --- |
| Una cancelación resuelta volvía a quedar pendiente al actualizar | `aplicarCancelacion` reabría la incidencia si la situación ya no era excluida o rechazada | La decisión queda anclada a la huella del hecho revisado. Conservar y excluir se mantienen si la fuente no cambia |
| Una exclusión o un descarte se perdían al recalcular | Valorar no trataba `excluida` como decisión humana y podía promoverla a elegible | Excluir y descartar siguen vigentes mientras la huella coincida. Solape, reversión y reempaque descartados no vuelven solos al acumulado |
| Las bolsas podían volver a valorarse | La exclusión de bolsas era solo la situación calculada | Siguen en importe cero después de sincronizar |
| Repetir la sincronización reabría incidencias resueltas | Solo se evitaba duplicar una incidencia pendiente | Si la huella no cambió, la incidencia resuelta no se reabre |
| Un cambio material no se distinguía de la decisión anterior | Se pisaba la situación | Se abre una revisión nueva y se explica el cambio. La decisión anterior permanece |
| La clave aleatoria no identificaba la captura | `manual:{hex}` en cada envío | `clave_idempotencia` única en base. El mismo envío devuelve el registro. Datos distintos con la misma clave se rechazan |
| No había contraste de trabajo físico | No se comparaba contra manuales, tienda ni cortes | Misma área, misma referencia y mismo día: Posible duplicado. Se listan los relacionados y se bloquea la aceptación normal hasta un reconocimiento justificado y auditado |
| Un manual aceptado y su equivalente sincronizado se podían sumar | Cada fuente creaba su unidad elegible | No se suman. Si uno ya está cortado, el corte se conserva y el conflicto queda en revisión |
| El formulario aceptaba área y persona empacadora | El cliente enviaba `area_id` y `empacador_user_id` | El servidor fija Tienda y la cuenta autenticada. Alterar la petición no cambia esos valores |
| Pedido y remisión no se veían | Estaban en el estado del formulario y no en la pantalla | Fecha y hora reales, cajas, pedido, cliente, remisión y observaciones quedan visibles. Con pedido, las referencias salen del servidor |
| La captura de cajas exigía tipo para valorarse | Sin líneas no había importe | La tarifa plana valora las cajas declaradas. El tipo queda opcional. Los orígenes que solo traen bultos siguen exigiendo separar cajas y bolsas |
| Capturar y revisar compartían el permiso de clasificar | Las dos acciones usaban `clasificar` | `capturar` y `clasificar` quedan separados. La migración no concede ninguno |
| Confirmar no releía la fuente operativa | El hash solo miraba filas ya importadas | Sin pulsar Actualizar, una cancelación, reversión o cambio de cantidad en el origen rechaza la confirmación e indica qué cambió |
| Sin áreas asignadas se veían todas | El arreglo vacío significaba acceso global | Sin filas no hay acceso y la pantalla dice «No tienes áreas asignadas». Tienda o CEDIS limitan a esa área |

No se agregó captura por terceros. En el repositorio no había un uso autorizado y documentado distinto del permiso general de clasificación.

## Decisiones que se conservan

La decisión humana no es un estado más de la lista. Vive en `ancla_decision`, `ancla_huella`, `ancla_motivo`, `ancla_por_id` y `ancla_at`, y también en la incidencia resuelta.

Si la cantidad, la fecha, la cancelación o el tipo cambian, la huella deja de coincidir. La unidad vuelve a revisión y la incidencia anterior sigue resuelta. Un corte confirmado no se reescribe: el cambio posterior es incidencia y, si corresponde, ajuste.

La confirmación relee solo las fuentes de las unidades incluidas en la vista previa. No importa el histórico ni escribe en pedidos. Si una fuente no se puede comprobar, no se confirma con los datos anteriores. Un cambio de cajas, clasificación, tarifa o reparto invalida la vista previa. Lo que ocurra después de confirmar no altera el folio ni las unidades ya congeladas.

La excepción de resguardo sigue permitiendo el empaque de CEDIS y el empaque final de tienda. No permite contar dos veces el mismo trabajo dentro de la misma área. Dos trabajos del mismo pedido en días distintos siguen siendo legítimos. El mismo día, la misma área y la misma referencia quedan como posible duplicado.

## Acceso por área

Consultar el módulo no abre todas las áreas.

| Cuenta | Alcance |
| --- | --- |
| Sin filas en `comisiones_empaque_usuario_areas` | Ninguna área |
| Asignación Tienda | Solo Tienda |
| Asignación CEDIS | Solo CEDIS |
| Super Admin o Administrador | Todas, por el patrón ya usado en el proyecto |
| Permiso `gestion_interna.comisiones_empaque.ver_todas` | Todas. No se asignó a ningún rol |

El servidor aplica el alcance en consultas, detalle, filtros por área, vista previa, cortes, pagos, ajustes, exportaciones y en la configuración de tarifas, repartos y asignación de áreas. Un identificador de otra área responde 403.

La asignación de áreas está en Configuración y exige `configurar`. Quien solo administra un área no puede quitar asignaciones que no ve.

## Migración y archivos

Aplicada: `database/migrations/2026_10_18_221500_ajustar_comisiones_empaque_decisiones.php`.

Agrega el ancla de la decisión, quién capturó, la clave de idempotencia (`ce_unidades_idem_uq`) y las marcas de posible duplicado. Registra en el catálogo, sin asignar a roles:

- `gestion_interna.comisiones_empaque.capturar`
- `gestion_interna.comisiones_empaque.ver_todas`

Código tocado para esta corrección:

- `app/Services/ComisionesEmpaque/SincronizarComisionesEmpaqueService.php`
- `app/Services/ComisionesEmpaque/ValorarUnidadComisionEmpaque.php`
- `app/Services/ComisionesEmpaque/GestionarUnidadComisionEmpaqueService.php`
- `app/Services/ComisionesEmpaque/ConfirmarCorteComisionEmpaqueService.php`
- `app/Services/ComisionesEmpaque/ComprobarFuenteCorteComisionEmpaque.php`
- `app/Services/ComisionesEmpaque/AlcanceAreaComisionEmpaque.php`
- `app/Services/ComisionesEmpaque/RegistrarPagoComisionEmpaqueService.php`
- `app/Services/ComisionesEmpaque/ConfigurarComisionEmpaqueService.php`
- `app/Services/ComisionesEmpaque/ConsultarComisionesEmpaqueService.php`
- `app/Support/ComisionesEmpaque/HuellaFuenteComisionEmpaque.php`
- `app/Http/Controllers/GestionInterna/ComisionesEmpaqueController.php`
- `app/Models/ComisionesEmpaque/UnidadComisionEmpaque.php`
- `app/Models/ComisionesEmpaque/UsuarioAreaComisionEmpaque.php`
- `resources/js/Pages/GestionInterna/ComisionesEmpaque/Index.jsx`
- `resources/js/utils/permisos.js`
- `routes/web/modules/gestion-interna.php`
- `tests/Feature/ComisionesEmpaque/ComisionesEmpaqueTest.php`
- `tests/Feature/ComisionesEmpaque/ComisionesEmpaqueConcurrenciaMysqlTest.php`
- `tests/Feature/ComisionesEmpaque/concurrencia.php`

La caché de rutas se regeneró. `npm run build` terminó.

## Pruebas

### SQLite

`./vendor/bin/sail artisan test tests/Feature/ComisionesEmpaque/ComisionesEmpaqueTest.php tests/Unit/ComisionesEmpaque/RepartirCentavosComisionEmpaqueTest.php`

26 pruebas. En la primera corrida pasaron 25 y falló la de exportación: la cuenta de prueba no tenía área y el alcance nuevo responde 403. Se le asignó CEDIS en esa prueba, porque el caso comprueba el permiso de exportar, no el acceso global. Al repetirla, pasó.

Cubren, además de las 15 originales y el reparto de centavos: conservar y excluir seguidos de varias sincronizaciones, descarte de una reversión, cambio de cantidad que abre otra revisión, reintento de captura, dos manuales equivalentes, manual frente a sincronizado, dos trabajos del mismo pedido en días distintos, confirmación sin actualizar tras una cancelación en origen, y cuenta sin áreas frente a solo Tienda y frente a `ver_todas`.

La prueba de carreras MySQL de ese directorio se omite si no está `COMISIONES_EMPAQUE_MYSQL=1`. No se ejecutó como PHPUnit. La carrera real se corrió con el script de abajo.

### MySQL

Base aislada `gelia_ce_test`, creada para esta validación. No es la base de trabajo `laravel`. El esquema se armó con `migrate`, no con `migrate:fresh`.

`./vendor/bin/sail php tests/Feature/ComisionesEmpaque/concurrencia.php` con dos procesos y una barrera de arranque, en cuatro casos:

| Caso | Resultado |
| --- | --- |
| Dos confirmaciones de la misma unidad | Una inclusión. Importe del corte igual a la unidad y a las asignaciones |
| Confirmación mientras otra conexión clasifica la unidad | Como máximo una inclusión. Sin corte a medias |
| Dos pagos con la misma clave | Un pago. El importe aplicado una sola vez |
| Dos capturas con la misma clave | Un registro |

Los cuatro imprimieron `VERIFICADO` y salieron en 0. La operación que perdía la carrera recibió el error controlado o el resultado ya existente.

### Regresiones

`EmpaqueBultosCedisTest`: 7 pruebas, todas en verde.

`CierresPagoPedidoBmaTest`: 4 pruebas. Pasaron 2. Fallaron `test_validar_crea_cierre_v1` y `test_rechazo_revoca_cierre_y_revalidacion_crea_v2` con «Cada exhibición de pago debe incluir su comprobante». Ese flujo no se modificó en esta corrección. Quedan como fallos ajenos al módulo.

`npm run check:style-jsx` señala literales hex en `Profile/Edit.jsx` y `Solicitudes/Partials/ModalFormSolicitud.jsx`. La pantalla de comisiones de empaque no aparece en esa lista.

### Navegador

No hubo recorrido autenticado. `http://100.75.11.59:9080/gestion-interna/comisiones-empaque` redirige a `/login`. No se comprobó en sesión el foco, los filtros, la tabla, el corte, el pago, la exportación ni el móvil. La compilación no sustituye ese recorrido.

## Configuración antes de un piloto

1. Definir la fecha de inicio. Mientras esté vacía, Actualizar no importa empaques.
2. Asignar Tienda, CEDIS o ambas a cada cuenta que vaya a operar. Sin filas no hay acceso.
3. Conceder `capturar` a quien registra su empaque de tienda y `clasificar` a quien revisa. No van juntos.
4. Reservar `ver_todas` para quien deba ver todas las áreas. Super Admin y Administrador ya entran a todas por su rol.
5. No hay corte automático por quincena. El intervalo se captura con fechas.

## Límites que siguen

La confirmación compara las unidades de la vista previa con su fuente en ese instante. No congela los pedidos. Si la fuente cambia después, el corte original permanece y la diferencia se atiende con incidencia o ajuste.

Dos registros de la misma área, la misma referencia y el mismo día se tratan como posible duplicado. En días distintos no se marcan.

El resguardo empacado en CEDIS y terminado en tienda sigue contando en las dos áreas, marcado como excepción temporal.

La sincronización sigue recorriendo desde la fecha de inicio en cada Actualizar. La marca de agua no está hecha.

La reversión de esta corrección es el `down` de `2026_10_18_221500_ajustar_comisiones_empaque_decisiones`. Quita las columnas nuevas y los dos permisos del catálogo. No borra las tablas del módulo ni los pedidos.
