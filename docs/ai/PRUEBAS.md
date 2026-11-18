# Pruebas — Gestión de pedidos

## Ejecución dirigida
- [verificado] Usar Sail y aislar ambas cachés; diagnosticar Sail antes de recurrir al host: `AGENTS.md:26` y `:29`.
- Comando base: `./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php laravel.test php artisan test --filter='MunicipalOrdinarioM01Test|SolicitudRegistrarTiendaTest'`.
- Elegir solo las clases de los módulos afectados; sustituir el filtro anterior por sus nombres. No implica ejecutar toda el área.
- Frontend dirigido: `./vendor/bin/sail exec --user sail -e NODE_OPTIONS=--no-experimental-webstorage laravel.test npm test -- resources/js/Pages/ControlPedidos/Partials/useActualizacionesPedidos.test.jsx resources/js/Pages/ControlPedidos/Partials/PanelRespuestaDisponibilidad.test.jsx`.
- [verificado] La opción de Node evita la interferencia de Web Storage nativo en este entorno: `gestion-pedidos-docs/M01_REFINAMIENTO_CAPTURA_MUNICIPAL_CODEX.md:94`.
- [verificado] PHPUnit fuerza SQLite en memoria, broadcast null y cola sync: `phpunit.xml:27` y `:29` y `:32`.
- [verificado] Concurrencia MySQL requiere habilitación explícita y base exclusiva; normalmente se omite: `tests/Feature/ControlPedidos/InstruccionRegistrarConcurrenciaMysqlTest.php:13`.
- Para concurrencia, revisar primero el preparador y [contrato de aislamiento](../../gestion-pedidos-docs/TIENDA_DECISIONES_VIGENTES.md#215--criterios-de-verificación); no apuntar a la base operativa ni ejecutar preparadores por rutina.
- Build solo cuando se cambie interfaz y se requiera publicarla: `./vendor/bin/sail npm run build`; no aplica a esta entrega documental. Reglas del entorno en `AGENTS.md:27`.

## Qué seleccionar
| Área | Clases/archivos de referencia |
| --- | --- |
| Captura/consulta | `ConcurrenciaCapturaPedidoTest`, `SolicitudRegistrarTiendaTest`, `useActualizacionesPedidos.test.jsx` |
| Municipal | `MunicipalOrdinarioM01Test`, `Municipal/registroMunicipal.test.jsx`, `Municipal/continuidadRegistro.test.jsx` |
| Pago/remisión | `ImportesPagoTiendaTest`, `RemisionAuditoriaTest`, `BlindarPagoTotalPedidoTest` |
| Tienda/custodia | `InstruccionEntregaVigenteTest`, `TransferenciaTiendaTest`, `RecorridoTiendaIntegralTest` |
| CEDIS/guías | `EmpaqueBultosCedisTest`, `ControlPedidosFase22Test`, `ControlPedidosActualizarGuiasTest` |
| Dirección/cancelación | `SnapshotDireccionPedidoTest`, `ControlPedidosFase7Test`, `EliminarRegistroPedidoBmaTest` |
- Ubicaciones exactas y dependencias: [MAPA](MAPA.md) → ficha correspondiente.

## Comprobación manual necesaria
- Dos sesiones autorizadas y datos sintéticos: respuesta inicial, ajuste, nueva respuesta, cierre/reapertura; mantener modales abiertos y captura local pendiente.
- Permisos denegados, departamentos, sucursales y propietario; no usar una cuenta omnipotente como evidencia de todos los actores.
- Transferencia: bolsas, salida, recepción, ubicación y entrega; repetir condiciones de horarios activos/pausados sin alterar pedidos operativos.
- Nacional: pesaje y recorrido financiero/logístico propios. Municipal: expediente privado y transporte conocido/diferido hasta el alcance realmente implementado.
- Reverb/worker, reconexión y avisos recibidos; distinguir evento real del respaldo HTTP. Cámara/escáner requieren dispositivo real.
- Si cambia interfaz: [GUIA_EVIDENCIA_VISUAL](../../GUIA_EVIDENCIA_VISUAL.md), escritorio/móvil, claro/oscuro y personalización; comprobar build servido.

## Cobertura y límites conocidos
- [verificado] Dos fallos previos de Tienda siguen registrados; no asumir la suite verde: `gestion-pedidos-docs/M01_CONTINUIDAD_REGISTRO_Y_CONSULTA_CODEX.md:94` y `:166`.
- [verificado] CSV municipal no tiene caso específico de descarga/exportación en la auditoría documentada: `gestion-pedidos-docs/M01_AUDITORIA_CODEX_DETALLADA.md:78`.
- [verificado] El corte más reciente acredita dos sesiones con respaldo HTTP, pero no transporte Reverb real ni Nacional completo: `gestion-pedidos-docs/M01_CORTE_ETAPAS_1_2.md:126`.
- [verificado] Falta baseline para check:style; conservar la limitación sin regenerarlo para ocultarla: `gestion-pedidos-docs/M01_CONTINUIDAD_REGISTRO_Y_CONSULTA_CODEX.md:166`; `scripts/style-guard/lib.sh:12`.
- [inferido] SQLite/sync no acredita bloqueos MySQL ni entrega asíncrona; por confirmar mediante aceptación aislada: `phpunit.xml:29` y `:32`.
- Esta auditoría no ejecutó pruebas, build, scheduler ni navegador; solo comprobó fuentes, referencias y alcance documental.
