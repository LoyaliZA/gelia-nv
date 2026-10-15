# CHECKPOINT Q7 — Continuidad de número de remisión y búsqueda de guías

Fecha de ejecución: 14 de octubre de 2026, zona de operación America/Mexico_City. Base: `861cb19d0e7ffbdb3695f9443723145e286fb8d1`, conservando los cambios de Q1 presentes al inicio.
Estado: **implementado y verificado en entorno aislado; pendiente de revisión**. Alcance exclusivo Q7/H09 de P1.2. No se iniciaron Q2 ni Q3.

## 1. Defecto reproducido

Se agregaron primero las pruebas, antes de cambiar código de aplicación. La fixture contiene identidades distintas:

| Pedido sintético | folio interno | folio_remision del pedido | numero_remision |
| --- | --- | --- | --- |
| A | PBMA-2026-00077 | PED-772 | REM-00935 |
| B | PBMA-2026-00078 | REM-00935 | REM-00888 |
| C | Folio Q7 sintético propio | REM-00935 | REM-00935 |

A recibe su numero_remision mediante el endpoint real de Auxiliar, en PENDIENTE_AUXILIAR; posteriormente la fixture lo sitúa en PENDIENTE_DE_GUIA con pago validado, remisión documental vigente y paquetería comercial. B también es elegible. C permanece en BORRADOR, fuera de las fases de la bandeja, incluso con tab TODOS. No se usan los identificadores internos de producción: los ids numéricos se generan en la base de prueba.

La solicitud HTTP `q_campo=numero_remision&q=REM-00935` devolvió B (id sintético 2), mientras el oráculo exige A (id sintético 1). La reproducción individual alcanzó el controlador y el listado reales: **1 prueba, 8 aserciones, 1 fallo funcional**, sin errores de tablas/bootstrap/permisos.

La reproducción de interfaz mostró que el selector no tenía la opción numero_remision y que no podía conservarla/enviarla. La búsqueda general también omitía A, aunque el número ya estaba persistido.

## 2. Causa raíz e inspección

`BusquedaDelegadoPedidoBma::valoresPermitidos` no incluía numero_remision. El controlador normaliza q_campo usando ese helper: el criterio nuevo era tratado como desconocido y caía a general. A su vez, `aplicarGeneral` no consultaba numero_remision; solo folio, folio_remision, numero_rastreo, cliente e id cuando el término es numérico. Así, el señuelo B coincidía por folio_remision y A no aparecía por su remisión.

En frontend, `FiltrosDelegado.jsx` consume `OPCIONES_BUSQUEDA_DELEGADO` de `pedidosBmaStyles.js`. La opción antigua etiquetada «Remisión» en realidad enviaba folio_remision. `Delegado/Index.jsx` y `useListadoDiscreto` ya transportan genéricamente q_campo a la petición y a la URL; no necesitan otra implementación.

Se inspeccionaron además `ListarPedidosDelegadoService`, `PedidoBmaDelegadoController`, `GestionarRemisionPedidoBmaService`, `ActualizarNumeroRemisionPedidoBmaRequest`, los servicios `AsignarGuiaPedidoBmaService`/`ActualizarGuiaPedidoBmaService`, `ExportarPlantillaGuiasPedidoService`/`ImportarGuiasPedidoService`, sus requests y las pruebas relacionadas.

La captura ya guarda numero_remision como string, registra referencia REMISION e historial y prohíbe modificar folio_remision/tipo_referencia en esa operación. La serialización del listado ya devuelve numero_remision. No hubo que modificar captura, columnas ni documentos.

## 3. Archivos de Q7 modificados o creados

| Archivo | Cambio |
| --- | --- |
| `app/Support/ControlPedidos/BusquedaDelegadoPedidoBma.php` | Constante/campo permitido numero_remision, rama específica y OR de búsqueda general |
| `resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js` | Nueva opción «Número de remisión»; criterio antiguo rotulado «Folio del pedido»; textos de ayuda diferenciados |
| `tests/Feature/ControlPedidos/BusquedaRemisionDelegadoTest.php` | 9 pruebas de integración HTTP/persistencia/CSV |
| `resources/js/Pages/ControlPedidos/Delegado/remisionBusqueda.test.jsx` | 3 pruebas de interfaz montada e interacción hasta la petición HTTP simulada |
| `CHECKPOINT_Q7_REMISION_BUSQUEDA_GUIAS.md` | Este informe |

El archivo compartido pedidosBmaStyles se modificó únicamente dentro de las opciones de búsqueda de Delegado; no se tocaron validaciones, modal, estilos ni reglas de otros procesos. `FiltrosDelegado`, `Index`, el controlador y los servicios de captura/listado/guías/CSV conservan su implementación.

El diff de aplicación de Q7 añade cinco líneas al helper y cambia únicamente las opciones/textos del selector. El diff de carátulas que sigue visible en el árbol corresponde a Q1; no forma parte de Q7 y se conserva idéntico.

## 4. Contrato de búsqueda aplicado

- `folio`: identidad interna GELIA; conserva su criterio y el comportamiento existente sobre el principal en búsqueda específica.
- `folio_remision`: folio documental del pedido; conserva columna y valor del parámetro. La etiqueta ahora distingue ese concepto del número registrado por Auxiliar.
- `numero_remision`: nuevo criterio explícito; busca únicamente esa columna, sin OR a los demás identificadores.
- `guia`: conserva la consulta a numero_rastreo. Cliente, general e id numérico mantienen sus reglas.
- General amplía el OR existente con numero_remision **dentro del mismo grupo de condiciones**, manteniendo los filtros externos de elegibilidad y bandeja.

La política de coincidencia existente está definida: SQL LIKE con `%término%`, trim del término y normalización de nombre de campo con trim/minúsculas. Se aplica la misma coincidencia parcial a numero_remision; no se introduce igualdad exacta, conversión numérica, nueva normalización de ceros, escape distinto de comodines ni cambio de sensibilidad de mayúsculas de la base. Campos desconocidos continúan cayendo a general.

Buscar numero_remision=REM-00935 encuentra A; folio_remision=REM-00935 encuentra B; general puede mostrar ambos. Buscar parcialmente 00935 conserva los ceros almacenados y encuentra A bajo el criterio nuevo. Un pedido con numero_remision null sigue localizable por sus folios y otros criterios, sin inventar un número de remisión obligatorio en Ventas.

## 5. Implementación y continuidad de guías

El criterio se agregó en el helper único, por lo que normalización del request, validación por lista permitida, aplicación de consulta y respuesta normalizada del controlador utilizan la misma definición. No se duplicó lógica en el controlador.

El selector actual renderiza automáticamente la opción nueva desde la lista compartida. El estado existente de Index entrega q_campo=numero_remision al cambiar criterio, actualizar o paginar. No se rediseñó el formulario.

El resultado conserva el id real del modelo. Las pruebas seleccionan A desde la búsqueda y usan ese id en los endpoints actuales de asignación y actualización; solo A cambia de guía, B conserva todos sus atributos y las tres identidades documentales de A permanecen intactas.

La importación sigue leyendo ID_Pedido y Guia_Rastreo; no resuelve por folio ni remisión. La prueba sube el CSV real al endpoint: una fila con id de A y folio textual deliberadamente ambiguo actualiza A; otra con REM-00935 como ID_Pedido se omite por id inválido, sin modificar B. La exportación conserva encabezados y Folio=folio_remision. No se cambió el CSV ni se detectó una necesidad indispensable de ampliarlo.

## 6. Cobertura agregada H09

| Validación | Prueba backend/frontend |
| --- | --- |
| Criterio nuevo, respuesta y serialización | `test_numero_remision_encuentra_a_y_conserva_el_criterio_en_respuesta` |
| Folio documental B, general A+B y PED-772 | `test_folio_documental_conserva_b_y_general_encuentra_a_y_b_sin_c` |
| Parcial, trim, nombre del campo y ceros | `test_coincidencia_parcial_trim_y_normalizacion_del_campo_conservan_ceros` |
| Pedido sin numero_remision | `test_sin_numero_remision_siguen_funcionando_los_criterios_anteriores` |
| Sin permiso y fuera de elegibilidad | `test_coincidir_no_elude_permiso_fase_pago_documento_o_guia_cliente`: 403 al usuario sin permiso; excluye C y coincidencias sin pago/documento o con guía de cliente |
| Asignar/corregir por id | `test_asignar_y_actualizar_por_id_no_modifica_b_ni_identidades` |
| Paginación y combinación de filtros | `test_paginacion_y_filtros_combinados_con_remision`: 16 coincidencias, páginas de 15+1 sin solapamiento; paquetería, situación, orden, URL y filtros incompatibles |
| Importar/exportar | `test_importacion_y_exportacion_conservan_contrato_csv_por_id`: servicio/lector reales, error parcial, mismas columnas y folio exportado |
| Captura Auxiliar e identidad | `test_captura_auxiliar_conserva_folios_y_referencia_remision`; también se realiza el alta real del número en la preparación de cada caso |
| Selector y parámetro UI | Tres casos de `remisionBusqueda.test.jsx`: opciones separadas, selección→HTTP con q_campo correcto y filtros/página preservados, recuperación desde filtros del servidor y actualización |

La prueba frontend monta Index y FiltrosDelegado reales y usa `useListadoDiscreto` real; sustituye Axios para capturar la petición, evitando red externa. Comprueba también q_campo en la URL. Layout, eventos en vivo y paneles ajenos se sustituyen; la tabla se reduce al disparador de paginación. No se presenta esto como prueba manual de navegador ni de comunicación real con servidor productivo.

## 7. Entorno y comparación antes/después

Backend: PHP 8.4.25 / PHPUnit 12.5.25 del contenedor existente `gelianv-laravel.test-1`. Variables por proceso DB_CONNECTION=sqlite, DB_DATABASE=:memory:, APP_ENV=testing. El guard de la prueba nueva comprueba configuración efectiva antes de que RefreshDatabase prepare esquema. Se usan almacenamiento falso local/public, permisos reales y notificador sustituido; no se accede a bases operativas ni se alteran entornos/configuración productivos.

Frontend: Vitest 4.1.11, happy-dom para interacción y dependencias ya instaladas. Sin instalaciones, servicios externos ni build/despliegue.

Comando backend combinado, ejecutado antes y después:

```sh
docker exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e APP_ENV=testing \
  -w /var/www/html gelianv-laravel.test-1 php vendor/bin/phpunit \
  tests/Feature/ControlPedidos/BusquedaRemisionDelegadoTest.php \
  tests/Feature/ControlPedidos/RemisionAuditoriaTest.php \
  tests/Unit/ControlPedidos/ControlPedidosActualizarGuiasTest.php
```

Para reproducción individual se ejecutó el archivo nuevo con `--filter test_numero_remision_encuentra_a_y_conserva_el_criterio_en_respuesta`.

Comando frontend combinado antes/después:

```sh
npm test -- resources/js/Pages/ControlPedidos
```

También se ejecutó el archivo frontend nuevo de forma aislada y revisión de sintaxis PHP de los dos archivos PHP de Q7, además de `git diff --check`.

| Ejecución | Antes | Después |
| --- | --- | --- |
| Reproducción HTTP individual | 1 fallo funcional; devuelve B en lugar de A | El mismo caso pasa dentro del conjunto final |
| Backend: 9 nuevas + 27 existentes | 36 pruebas, 189 aserciones; 7 fallos nuevos de contrato y 29 aprobadas | **36 pruebas, 221 aserciones, todas aprobadas** |
| Frontend: 3 nuevas + 85 existentes | 20 archivos; 3 fallos nuevos y 85 aprobadas | **20 archivos, 88 pruebas aprobadas** |
| Frontend nuevo aislado final | La versión inicial reprodujo ausencia de opción y parámetro | **3 pruebas aprobadas**, usando hook y petición Axios simulada |
| Sintaxis y espacios | — | Sin errores |

Ajustes de pruebas nuevos durante preparación, distinguidos de defectos funcionales:

1. La primera lectura de exportación falló porque el CSV existente incluye BOM UTF-8 delante de ID_Pedido. Se corrigió **el lector de la nueva prueba** para retirar el BOM antes de comparar encabezados, sin cambiar exportación ni expectativas existentes. Se volvió a obtener el baseline antes del parche: los siete fallos restantes corresponden a búsqueda, no a CSV.
2. La primera versión frontend sustituía el hook de listado; luego se fortaleció para usar el hook real y observar Axios/URL. En esa preparación faltaban métricas estables de servidor en la fixture, lo que provocó renderizados repetidos. Se interrumpió esa ejecución y se completó la fixture con métricas estables; no se atribuyó a Q7 ni se cambió código de aplicación por ello. El archivo aislado y el conjunto final de 88 pasan.

No se modificaron expectativas de pruebas preexistentes. No hubo errores de bootstrap ni fallos preexistentes en el conjunto final de Q7. Los problemas de pruebas municipales descritos en Q1 no pertenecen a este conjunto; no se afirma haberlos corregido ni se reescribió su informe.

## 8. Compatibilidad, permisos y alcance departamental

Se conservan autorización control_pedidos.delegado, normalización central, fases y reglas de queryBase, tab, pago validado, documento de remisión, guía proporcionada por cliente, paqueterías, situación, orden y paginación de 15 registros. El nuevo OR está dentro del grupo de búsqueda; no evade condiciones externas. Sin permiso la solicitud devuelve 403 aun cuando la remisión coincida.

**Discrepancia explícita con la suposición de alcance departamental:** Delegado actualmente no limita por departamento del usuario. Lo documenta `app/Support/ControlPedidos/VisibilidadPedidoBma.php:18` y lo implementan el listado y el controlador sin filtro departamental. No se agregó ni retiró ninguno. No es correcto usar un vendedor de otro departamento como C y asegurar que actualmente debería ocultarse al Delegado: cambiar esa política afectaría visibilidad y otros consumidores fuera de esta ampliación.

Por ello C prueba exclusión por fase no elegible y se prueba aparte usuario sin permiso. Esto **no acredita aislamiento departamental por usuario**. Si se pretende imponerlo, queda como decisión funcional separada, con revisión de listado, métricas, consultas y mutaciones; se documenta sin implementarla. Q7 preserva el alcance actual y no otorga permisos ni agrega acceso a fases nuevas.

Los servicios de guías y CSV permanecen intactos; la búsqueda no cambia sus autorizaciones ni amplía su contrato. Este checkpoint no constituye una auditoría general de autorización de sus endpoints.

## 9. Regresiones detectadas

No se detectaron regresiones atribuibles a Q7: las 27 pruebas backend existentes y las 85 frontend existentes mantienen su resultado aprobado, además de los nuevos casos. La captura de remisión, las identidades, la actualización seleccionada y el CSV se verificaron con pruebas de integración.

No se modificaron código ni pruebas de Q1. Se compararon hashes de inicio/final para su servicio, prueba e informe, y para P0/P1/P1.1/P1.2: todos permanecen idénticos. Se mantiene el diff de Q1 en el árbol de trabajo.

## 10. Riesgos pendientes y límites

- Restricción departamental de Delegado: pendiente si se solicita como política nueva; no existe hoy y no se puede declarar cubierta (§8).
- LIKE conserva semántica de la base, incluidos comodines y collation. No se impuso unicidad de numero_remision ni coincidencia exacta. Una búsqueda parcial puede devolver varios pedidos; la selección y mutación usan id.
- Consultas `%término%` mantienen el patrón existente. No se midió rendimiento con volumen productivo ni se crearon índices/migraciones.
- SQLite y HTTP simulado cubren contrato y persistencia; no son prueba de concurrencia MySQL, interfaz desplegada ni comportamiento de collation productiva.
- Frontend verifica interacción DOM, hook real y envío de parámetros con transporte sustituido. No se hicieron pruebas sobre datos operativos ni pruebas manuales en producción.
- No se modificaron fallos, reglas o paquetes de captura, Preparación Tienda/CEDIS, carátulas, estados, finanzas ni resguardos.

## 11. Recomendación del siguiente checkpoint

Revisar el diff Q7 y sus evidencias. Después de esa revisión, el paquete Q2 de integridad de captura es el siguiente candidato del plan P1.2, acotando primero reproducción y persistencia de los campos actuales; Q3 de concurrencia conserva su alcance separado. Ninguno se inicia automáticamente.

Q7 termina aquí: dos archivos de aplicación modificados, dos archivos de pruebas nuevos y este informe. Sin commits, push, instalaciones, cambios de CSV, migraciones operativas ni modificaciones de checkpoints previos.
