# Multienvío nacional — Traspaso para continuar en otro chat (27-nov-2026)

**Estado:** el usuario hizo el checkpoint `e2848a51` («CHECKPOINT ENVIOS MULTINACIONALES Y NGINX») con todo lo de abajo **más** los cambios de la co-desarrolladora (Delegado/guías PDF, enlaces de Direcciones, nginx/Docker, etc.). No se hizo ningún commit desde la sesión de Claude. Al cerrar, el árbol de trabajo estaba limpio y la revisión del bloque A fue **detenida** a petición del usuario (no terminó).

Documentos base (todos en el repo): diseño `docs/superpowers/specs/2026-11-27-multienvio-nacional-design.md`, hallazgos R1 `…-multienvio-r1-hallazgos.md`, plan Fase 1 `docs/superpowers/plans/2026-11-27-multienvio-nacional-fase1.md`, plan de las fases A/B `docs/superpowers/plans/2026-11-27-multienvio-nacional-fases-2-4.md`, bitácoras/informes copiados en `docs/superpowers/handoff-2026-11-27/` (ledgers con **todos los Ruling y menores diferidos**, informes A1/A2-A3/A4, MySQL, fix-wave). Especificación funcional original: `GELIA-NV_plan_multienvio_nacional_Claude_Code.md` (ME-01..ME-12, A01..A22).

## Hecho

- **Fase 1 (backend, revisada tarea por tarea + revisión final + fix wave):** migraciones `2026_11_27_100000_create_multienvios_nacionales_table` y `…100100_agregar_multienvio_a_pedidos_bma`; servicios en `app/Services/ControlPedidos/Nacional/Multienvio/` (`EvaluarMultienvioNacional`, `RegistrarDecisionMultienvio`, `GuardarBorradorMultienvio`, `ConfirmarMultienvio`, `MaterializarEnvioMultienvio`, `DetectarHechosPreviosMultienvio`, `FolioWizerpMultienvio`, `ProyeccionMultienvio`, `MultienvioNacionalException`); `MultienvioNacionalController` + rutas `GET /control-pedidos/{id}/multienvio`, `POST …/decision`, `PUT …/borrador`, `POST …/confirmar` (con espejo móvil). Concurrencia real en MySQL 8.4 probada (`MultienvioNacionalConcurrenciaMysqlTest`, `MULTIENVIO_MYSQL=1`).
- **Bloque A sin revisión de bloque:**
  - **A1 (backend):** `ResumenGrupoMultienvio`, `VisibilidadMiembrosMultienvio`, `cajas_origen`/`cajas_resumen`/`pendiente`/`saf_aplicado` en `ProyeccionMultienvio`, `multienvio` en `LecturaConsultaNacional` y en `ListarPedidosBmaService`. Informe: `handoff-2026-11-27/a1-report.md`.
  - **A2/A3 (React):** `EtiquetaMultienvio`, `SeccionMultienvioDetalle`, `PanelMultienvioNacional`, `useMultienvioNacional`, integración en `FormularioRegistrarNacional.jsx`, `EncabezadoFolioPedido.jsx`, `CuerpoDetalleNacional.jsx`, `ControlPedidos/Index.jsx`. Informe: `a23-report.md`. Vitest Nacional+Partials: 497 pasadas / 2 fallos preexistentes (`modalElegibilidadCedisPdv.test.jsx`). `npm run build` compiló bien.
  - **A4 (prueba HTTP):** `MultienvioNacionalRecorridoVentasTest` — un miembro cotiza y llega a Auditar; números reales = spec (seguros 65.00/63.40/61.60; totales 765.00/683.40/591.60). Informe: `a4-report.md`.

## Pendiente (en este orden)

1. **Revisión del bloque A** (se detuvo): diff = `git diff 0e5be6cb e2848a51 -- . ':!docs' ` filtrando lo no-Multienvío. Puntos a revisar: privacidad de `ResumenGrupoMultienvio`/`cajas_origen`/URLs de evidencia (¿mismos permisos que los documentos existentes?); montos en centavos enteros en el panel; borrador vacío se guarda como 0; flujo de revisión obsoleta («Actualizar con las cajas vigentes» debe re-activar con la revisión nueva); que un fallo del `GET /multienvio` no bloquee la cotización de pedidos ordinarios; colisión de nombre `multienvio` (relación del modelo vs. atributo resumen); tests del panel escritos después del código.
2. **Fase B** (plan `…-fases-2-4.md`): B1 Auditar (insignia, chips de hermanos, búsqueda por cualquier folio del grupo, aviso informativo de comprobante compartido, observación sugerida), B2 CEDIS (insignia, hermanos, búsqueda por `numero_remision`/rastreo/folio de hermano, **rechazar consolidar miembros** en `ConsolidarPedidosEmpaqueService`, grep de acoplamientos por `cliente_id`), B3 Delegado (insignia, hermanos, columnas informativas en la plantilla; `ID_Pedido` sigue siendo la clave), B4 prueba de recorrido completo con comprobante compartido de 2040.00 y reportes, B5 regresión + documentación + build + evidencia visual. **Delegado: la co-desarrolladora edita los mismos archivos (controlador, `ModalDetalleDelegado`, `TablaDelegado`, `Delegado/Index.jsx`); construir encima de lo ya commiteado, sin pisarlo.**
3. **Documentación de la UI** (REGISTRO_PEDIDOS, guía V3.1 §15 con la parte de pantallas, MAPA/PRUEBAS) y **evidencia visual** (escritorio/móvil, claro/oscuro) — nada de la UI se ha visto en navegador.
4. Menores diferidos: ver `ledger-fase1.md` y `ledger-fases-A-B.md` (p. ej. claves JSON `cancelacion_nacional`… agrupadas como `complemento`, `contenido` del borrador no pasa a los envíos, error de soporte faltante, huecos de pruebas).
5. Fuera de alcance acordado: reasignación automática de pagos ya capturados/validados a envíos creados después (Fase de regularización), seguros mixtos por caja, despacho parcial entre miembros, conteo de «operaciones agrupadas» en todas las métricas.

## Decisiones del usuario (27-nov-2026)

- Reutilizar el pedido original como **envío 1** (opción A) y aprobar el diseño técnico.
- Pago capturado sin validar → **regularización pendiente** en esta etapa (nada se mueve).
- El folio Wizerp de cada envío es referencia tipo **«Pedido»**; la auxiliar registra después las remisiones, una por envío con su número.
- Menos fases, urgente; prioridad: que Ventas, Auditar, CEDIS y Encargado de guías puedan operar; dejar rutas de datos para saldos a favor.
- Pruebas en los contenedores existentes (sin crear contenedores nuevos); MySQL sobre `gelianv-mysql-1`/BD `gelianv`, solo filas sintéticas marcadas y limpiadas; solo mis dos migraciones.

## Cómo seguir (entorno)

- Repo de trabajo: `/home/ti-admin/produccion-test` (rama `develop`). El contenedor de desarrollo con PHPUnit/npm es `gelianv-laravel.test-1`, que monta **otro checkout** (`/home/ti-admin/gelianv`). Sincronizar antes de cada prueba:
  `cd /home/ti-admin/produccion-test && (git diff --name-only HEAD; git ls-files -o --exclude-standard) | sort -u | rsync -a --files-from=- ./ /home/ti-admin/gelianv/`
  (si el árbol está limpio tras un commit, sincronizar con `git diff --name-only <commit-previo> HEAD`; el checkout de gelianv quedó **con cambios sin commit** por estos rsync y con `storage/framework/testing/disks` renombrado a `disks.root-bak`.)
- PHP: `docker exec -u sail -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php gelianv-laravel.test-1 php artisan test --filter='MultienvioNacional|MobileEspejoRutasTest|Nacional…'`.
- JS: `docker exec -u sail -e NODE_OPTIONS=--no-experimental-webstorage gelianv-laravel.test-1 npm test -- <archivos>`; build: `docker exec -u sail gelianv-laravel.test-1 npm run build` (publica en `/home/ti-admin/gelianv/public/build`; el `public/build` de `produccion-test` es de root y está fuera de Git: publicarlo es paso de despliegue del usuario).
- **Base de datos:** las dos migraciones de Multienvío quedaron **sin aplicar** (Pending) en `gelianv_db` (8.0, vía `gelianv_app`) y en `gelianv-mysql-1` (8.4). Probadas subir/revertir en ambas. Aplicar solo las mías: `php artisan migrate --path=database/migrations/2026_11_27_100000_create_multienvios_nacionales_table.php --path=database/migrations/2026_11_27_100100_agregar_multienvio_a_pedidos_bma.php --force`. Hay otras 5 migraciones pendientes que son de la co-desarrolladora: no correrlas.
- Baseline conocido: `NacionalAltaConsultaTest > alta mixta` falla desde antes de estos cambios.
- Modo de trabajo acordado: un implementador por bloque y una revisión por bloque (modelo fuerte solo en pagos, permisos y visibilidad); sin commit/push de Claude.
