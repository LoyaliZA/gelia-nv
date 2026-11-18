# Pruebas — Gestión de pedidos

## Ejecución dirigida
- [verificado] Usar Sail y aislar ambas cachés; diagnosticar Sail antes de recurrir al host: `AGENTS.md:26` y `:29`.
- Comando base: `./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php laravel.test php artisan test --filter='ConversionModalidadM02Test|ConversionMotorATest|MunicipalOrdinarioM01Test|SolicitudRegistrarTiendaTest|TransferenciaTiendaTest|ImportesPagoTiendaTest|RemisionAuditoriaTest|InstruccionEntregaVigenteTest|RecorridoTiendaIntegralTest|ConcurrenciaCapturaPedidoTest'`.
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
| Municipal | `MunicipalOrdinarioM01Test`, `ConversionMotorATest`, `ConversionModalidadM02Test`, `Municipal/registroMunicipal.test.jsx` |
| Conversión P0 | `ConversionModalidadM02Test` caso 10: GET pendiente 150, pago bloqueado con consulta abierta, cierre municipal, exhibición cubre solo transporte |
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
- [verificado] Filtro M-02 oleada 3 (2026-11-18): 228 aprobadas, 5 fallidas, 2718 aserciones. `ConversionModalidadM02Test` 29/29 y `ConversionMotorATest` 6/6. `TransferenciaTiendaTest` verde (T-03 Nacional sigue solo intención).
- [verificado] Los 5 fallos no salen del motor M-02: retiro documental deja `aceptacion_vigente` (M-01 cierre 200 y 2 casos Tienda); MD sábado pasa si `horarios_activos=false`; preparación Q3 exige `folio_remision`. No se relajaron esas suites.
- [verificado] CSV municipal sin caso de descarga; Reverb real y Nacional completo no acreditados: `gestion-pedidos-docs/M01_AUDITORIA_CODEX_DETALLADA.md:78`; `M01_CORTE_ETAPAS_1_2.md:126`.
- [verificado] Falta baseline para check:style; no regenerarlo para ocultarlo: `scripts/style-guard/lib.sh:12`.
- [inferido] SQLite/sync no acredita bloqueos MySQL ni entrega asíncrona: `phpunit.xml:29` y `:32`.
- Última verificación: 2026-11-18, working tree M-02 oleada 3.
