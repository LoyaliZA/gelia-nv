# Auditoría de Envíos Nacionales 2.0 — diseño

Fecha: 23 de noviembre de 2026 · Rama `develop` · Estado: aprobado por el usuario el 23-nov-2026 (incluye crear datos sintéticos en el entorno de pruebas).

## Intención y por qué

El flujo de Envíos Nacionales sigue roto después de las olas 0-3 implementadas por Cursor (commits `b5be2bb5`, `f410db92`, `1ac45c5f`, `63d6c6d5`, `0d04de9c`; unas 8,800 líneas netas). El usuario reporta:

- registro de pedido nacional, pesaje y respuesta del pesaje defectuosos;
- el modal de envíos nacionales no cumple los estándares visuales del módulo de gestión;
- el pesaje de CEDIS no está adaptado a móvil (`ModalResponderPesaje.jsx`, 1,982 líneas);
- las paqueterías con logística (FedEx, Estafeta, DHL) no se aplican como deben;
- varios endpoints no concluyen correctamente;
- sospecha de que la implementación no sigue la guía operativa V3.1 y de que dejó regresiones.

La auditoría debe dar un diagnóstico confiable y priorizado para reparar por lotes. Contrato de negocio: `gestion-pedidos-docs/GELIA_GESTION_PEDIDOS_GUIA_OPERATIVA_V3.1_2026-11-21.md` (§6 N-F01…N-F04, §7, §10 QA-N01…N06, §11). La versión 1.0 se usa solo como inventario de capacidades que no debieron perderse (`ModalFormPedidoLegado.jsx` y el flujo histórico).

## Metas

- Informe de hallazgos con severidad, evidencia (archivo:línea, prueba o salida de comando) y regla V3.1 incumplida.
- Una matriz relevo por relevo: pantalla → ruta → controlador → servicio → bandeja siguiente → estado (OK / roto / incompleto / no verificable).
- Lista de regresiones en Tienda y Municipal por componentes compartidos.
- Propuesta de lotes de reparación ordenados por impacto y dependencia.

## No metas

- No corregir código, no migrar, no compilar assets (salvo lo necesario para pruebas Vitest) ni hacer commit/push.
- No rediseñar la interfaz ni reabrir decisiones vigentes de Tienda/Municipal.
- No ejecutar pagos, guías ni mensajería reales; no tocar datos productivos.
- No reimplementar la v1.0: se compara capacidad, no pantalla.

## Anti-metas

- Declarar "OK" por una prueba verde sin comprobar el relevo al rol siguiente (V3.1 §11.2).
- Confiar en los reportes de Cursor como evidencia: sus cifras se tratan como afirmaciones a reverificar.
- Un informe tan largo que no sirva para decidir qué reparar primero.

## Restricciones

- Todo comando por `./vendor/bin/sail`; pruebas con `APP_CONFIG_CACHE` y `APP_ROUTES_CACHE` aislados en `/tmp` (AGENTS.md).
- Comprobaciones dirigidas y proporcionales; sin repetir suites por rutina.
- Interfaz juzgada con `GUIA_DISENO_GELIA.md` y `GUIA_EVIDENCIA_VISUAL.md`; 375 px y 1280 px.
- Navegador: hay una sesión de superadmin de prueba en `http://100.110.75.115:9080/dashboard` (Claude in Chrome). Un superadmin ve todas las vistas pero puede saltarse las guardas por rol, así que **no acredita** la separación de responsabilidades de V3.1 (§2): eso se verifica por código y pruebas de permisos. Lo que no se pueda comprobar con esa cuenta queda `BLOCKED` o `NOT_RUN`, nunca `PASS`.
- El usuario confirmó que el entorno del navegador es de pruebas. Sin pagos, guías ni mensajería reales; solo pedidos y comprobantes sintéticos. Sin acciones destructivas.

## Enfoques considerados

- **A. Solo por relevos:** fiel al trabajo real, pero no detecta daños colaterales.
- **B. Solo por capas técnicas:** rápido para endpoints y regresiones, pero no mide cumplimiento de la guía.
- **C. Híbrida en tres pasadas (elegida por el usuario, por tiempo):** cubre los dos riesgos con un solo informe.

## Diseño de la auditoría

**Pasada 1 — Línea base ejecutable.** Enumerar rutas nacionales (`routes/web/modules/control-pedidos/{core,cedis,delegado}.php`) con su controlador y permiso. Correr las suites nacionales (`NacionalAltaConsultaTest`, `NacionalLogisticaCobroTest`, `NacionalOla3*`, `MarcaNacionalCapturaTest`, `OrigenEnvioNacionalTest`, seguro, paquetería pendiente, guías) y Vitest de `Nacional/` y `Cedis/Partials/`. Registrar pasa/falla con salida. Clasificar cada falla (pre-existente en HEAD limpio vs introducida).

**Pasada 2 — Trazado por relevos contra V3.1.** Un recorrido por cada uno: N-F01 ordinario; pesaje y respuesta de CEDIS (incluida la vista móvil); N-F02 `PAQ. PENDIENTE`; paqueterías con logística FedEx/Estafeta/DHL (cotización, flete por caja, reexpedición, seguro, fórmula AC-09); N-F03 cajas y consolidación (N-11/N-12/N-13, AC-12); N-F04 complementos; bifurcaciones (cambio de paquetería, dirección con guía, Nacional→Tienda, cancelación, crédito). Por relevo: ¿existe acción visible para el rol correcto, ruta, validación de servidor, efecto en dinero y físico por separado, y llegada a la bandeja siguiente? Se cierra con las barreras de §1.1 (sin despacho con crédito bloqueado, sin guía válida, sin cobertura).

**Pasada 3 — Transversal.** Diff de los cinco commits sobre archivos compartidos (`FormularioRegistrarTienda`, `PedidoBma`, `adaptadoresConsulta`, `FusionarSolicitudPreparacionPedido`, rutas) buscando regresiones en Tienda/Municipal y las dos fallas no clasificadas conocidas (`SnapshotDireccionPedidoTest`, `MunicipalOrdinarioM01Test::test_resolver_faltante…`). Revisión de interfaz por código y estilos, y **en el navegador** (superadmin de prueba): modal nacional, bandeja y modal de pesaje de CEDIS a 375/1280 px, consola y red en busca de endpoints que fallan o no concluyen, estados, jerarquía y tokens frente a la guía de diseño. Capturas como evidencia.

**Entregable.** `docs/superpowers/specs/2026-11-23-nacional-auditoria-informe.md` con: resumen ejecutivo; tabla de hallazgos (ID, severidad, área, descripción, evidencia, regla V3.1, lote sugerido); matriz de relevos; regresiones; lo no verificable y por qué; lotes de reparación propuestos.

## Decididas por mí (corregibles)

- Solo lectura y pruebas aisladas; nada se corrige en esta fase.
- Navegador con la cuenta superadmin de prueba: primero solo lectura (abrir vistas, 375 px y 1280 px, consola y red); el usuario confirmó que el entorno es de pruebas: se pueden crear pedidos sintéticos (prefijo `AUD-NAC-` en cliente/folio para identificarlos), sin pagos, guías ni mensajería reales.
- Pasadas 1 a 3 en ese orden; se ejecutan en esta sesión sin subagentes salvo que lo pidas.
- Severidades: **Crítica** (flujo no concluye o rompe Tienda/Municipal), **Alta** (incumple regla de V3.1), **Media** (UX/diseño), **Baja** (deuda).

## Quedan al auditor

Elegir el orden fino de los relevos dentro de la pasada 2, qué pruebas adicionales escribir solo para reproducir un hallazgo (en directorio temporal, sin dejarlas en el repo) y cómo agrupar los lotes finales.
