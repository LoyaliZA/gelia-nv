# Pedido nacional: paquetería, direcciones y seguro visible — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** La vendedora ve el seguro y el desglose antes de guardar, el cliente sin direcciones puede recibir un enlace-formulario, y las etiquetas dejan de confundir.

**Architecture:** El servidor sigue siendo la única fuente del cálculo (`CalcularSeguroPedidoService` + `DistribuirSeguroCajaNacional`). Un endpoint de vista previa sin persistencia devuelve el desglose; la pantalla lo consulta. El resto son cambios de UI y validación sobre componentes existentes.

**Tech Stack:** Laravel (Sail), Inertia + React, Vitest (`*.test.jsx`), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-24-nacional-paqueteria-seguro-design.md`

## Global Constraints

- Ejecutar todo con `./vendor/bin/sail`. Pruebas PHP: `./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php laravel.test php artisan test <ruta>`.
- No hacer commit ni push salvo que el usuario lo pida. No dejar Vite ni `public/hot` activos; no compilar tras cada edición (un solo `sail npm run build` al final, solo si el usuario lo pide; los cambios se publican con `./update-prod-sim.sh`).
- Antes de tocar UI leer `GUIA_DISENO_GELIA.md`, `.ux-profile.md` y `GUIA_EVIDENCIA_VISUAL.md`; reutilizar `THEME_*`/`GELIA_BTN_*` de `utils/geliaTheme`. Layouts válidos a 375 px y 1280 px.
- Nunca mostrar códigos de ficha de proceso al usuario; usar nombres de operación.
- Fórmula fija (no cambiar): Estafeta y FedEx = (venta + guía) × 2.5 %; DHL = (venta + guía) × 2 % + 51. Seguro opcional, apagado por defecto. Sin columnas nuevas en el catálogo.
- Textos exactos: «Seleccionar paquetería», «Enviado a sucursal».
- Al corregir el flujo, actualizar `gestion-pedidos-docs/GELIA_GESTION_PEDIDOS_GUIA_OPERATIVA_V3.1_2026-11-21.md` (recorrido y tabla §15: fecha, regla sustituida, decisión, casos).

## Review Focus

- Cliente sin direcciones abre el modal sin permiso de direcciones: aviso visible y sin botón de enlace/alta que falle (Task 3).
- Seguro activado y luego se cambia la paquetería a una sin cobertura (o a «pendiente de confirmación»): el seguro se apaga, no se cobra (Task 2, 3).
- Flete vacío, cero o mal escrito mientras se previsualiza: no hay error ni «NaN»; muestra el desglose en blanco (Task 3).
- Respuestas de vista previa que llegan desordenadas al teclear: solo vale la última (Task 3).
- Teléfono pegado con espacios, guiones o `+52`: se normaliza a 10 dígitos; CP con letras se rechaza en cliente y servidor (Task 4).

---

### Task 1: Etiquetas «Seleccionar paquetería» y «Enviado a sucursal»

**Files:**
- Modify: `resources/js/Pages/ControlPedidos/Nacional/FormularioRegistrarNacional.jsx:443-468` (label y `aria-label` «Cambiar paquetería» → «Seleccionar paquetería»; botón «Actualizar paquetería» → «Seleccionar paquetería»)
- Modify: `resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis.jsx:289` y `ModalDetalleCedis.jsx:205`
- Test: `FormularioRegistrarNacional.test.jsx`, `ContinuacionEnvioNacional.test.jsx` (ajustar textos), nuevo `resources/js/Pages/ControlPedidos/Cedis/Partials/etiquetaEnvioCedis.test.js`

**Interfaces:**
- Produces: `etiquetaMarcarEnviado(pedido: object): string` en `resources/js/Pages/ControlPedidos/Cedis/Partials/etiquetaEnvioCedis.js`; devuelve `'Enviado a sucursal'` si el pedido es resguardo en tienda, si no `'Marcar enviado'`.

- [ ] **Step 1: Test `etiquetaEnvioCedis.test.js`:** `expect(etiquetaMarcarEnviado({ es_resguardo: true })).toBe('Enviado a sucursal')` y `expect(etiquetaMarcarEnviado({ es_resguardo: false })).toBe('Marcar enviado')`.
- [ ] **Step 2:** Correrlo (`./vendor/bin/sail npx vitest run <ruta>`); esperado: falla por módulo inexistente.
- [ ] **Step 3:** Implementar `etiquetaMarcarEnviado`. Antes de fijar la condición, confirmar en `ListarPedidosBmaService`/CEDIS qué campo marca «resguardo en tienda» en estatus `PENDIENTE_DE_ENVIO` (candidatos: `es_resguardo`, `cambioATiendaNacional`); si difiere de `es_resguardo`, usar el correcto y ajustar el test.
- [ ] **Step 4:** Usarlo en `TarjetasCedis.jsx:289` y `ModalDetalleCedis.jsx:205` (la rama «Marcar recolectadas» no cambia). Aplicar a municipal y nacional.
- [ ] **Step 5:** Renombrar los textos del formulario nacional y ajustar tests existentes que busquen los textos viejos (`grep -rn "Cambiar paquetería\|Actualizar paquetería" resources/js`, incluido el manual `Soporte/Manuales/content/control-pedidos`).
- [ ] **Step 6:** Correr los tests de las tres carpetas tocadas; esperado: pasan.

### Task 2: Vista previa de cotización (backend)

**Files:**
- Create: `app/Services/ControlPedidos/Nacional/VistaPreviaCotizacionNacional.php`
- Modify: `app/Http/Controllers/ControlPedidos/PedidoBmaController.php` (método `vistaPreviaCotizacionNacional`, junto a `paqueteriaNacional` ~línea 1092)
- Modify: `routes/web/modules/control-pedidos/core.php:89` (nueva ruta `POST /{pedidoBma}/cotizacion-nacional/vista-previa`, nombre `vista_previa_cotizacion_nacional`)
- Modify: `app/Services/ControlPedidos/ObtenerCatalogosPedidoBmaService.php` (cada paquetería expone `cobertura_seguro: bool` vía `CalcularSeguroPedidoService::tieneCobertura`)
- Test: `tests/Feature/ControlPedidos/NacionalVistaPreviaCotizacionTest.php`

**Interfaces:**
- Produces: `VistaPreviaCotizacionNacional::calcular(PedidoBma $pedido, CatalogoPaqueteriaPedido $paqueteria, array $lineas, bool $aplicaSeguro, ?int $zonaId): array` → `['venta' => float, 'flete' => float, 'reexpedicion' => float, 'guia' => float, 'seguro' => float, 'total' => float, 'cobertura_seguro' => bool]`.
- Request: `catalogo_paqueteria_id`, `catalogo_zona_id` (nullable), `aplica_seguro` (boolean), `lineas.*.{uuid_operativo, costo_envio}`. Mismas reglas de acceso que `paqueteriaNacional` (`Gate control_pedidos.crear`, `asegurarAcceso`). No escribe en base.

- [ ] **Step 1: Tests.** (a) Estafeta, venta 1000, un flete 200, seguro activo → `seguro` 30.00 y `total` 1230.00 + reexpedición; (b) DHL mismos datos → 75.00; (c) `aplica_seguro=false` → 0; (d) paquetería sin cobertura → `seguro` 0 y `cobertura_seguro` false; (e) la llamada no modifica `pedido_bma` ni cajas; (f) usuario sin acceso → 403.
- [ ] **Step 2:** Correr; esperado: fallan (ruta inexistente).
- [ ] **Step 3:** Implementar el servicio reutilizando `DistribuirSeguroCajaNacional::porLinea` para el seguro. La reexpedición sale del mismo cálculo de zona que usa `ActualizarCostosCajasPedidoBmaService` al guardar (localizarlo, no duplicarlo). **Verificar** si la base de seguro de `calcularCosto` incluye la reexpedición: hoy recibe solo el flete. Si no la incluye, mantener el comportamiento guardado, devolver `guia` = flete + reexpedición, y **reportar la discrepancia al usuario** antes de cambiar la fórmula.
- [ ] **Step 4:** Controlador, ruta y bandera `cobertura_seguro` en catálogos.
- [ ] **Step 5:** Correr tests; esperado: pasan. Correr también `NacionalLogisticaCobroTest.php` para confirmar que el guardado no cambió.

### Task 3: Contenedores Dirección / Costo de envío y desglose en vivo

**Files:**
- Modify: `resources/js/Pages/ControlPedidos/Nacional/ContinuacionEnvioNacional.jsx` (extraer dos bloques; ~líneas 132-206)
- Create: `resources/js/Pages/ControlPedidos/Nacional/DesgloseCotizacionNacional.jsx` (presentacional) y `resources/js/Pages/ControlPedidos/Nacional/useVistaPreviaCotizacion.js`
- Test: `ContinuacionEnvioNacional.test.jsx`, nuevo `DesgloseCotizacionNacional.test.jsx`

**Interfaces:**
- Consumes: ruta `control_pedidos.vista_previa_cotizacion_nacional` y `catalogos.paqueterias[].cobertura_seguro` (Task 2).
- Produces: `useVistaPreviaCotizacion({ pedidoId, paqueteriaId, zonaId, fletes, aplicaSeguro, activo }): { datos: object|null, cargando: boolean }` (espera ~400 ms tras el último cambio; ignora respuestas obsoletas). `DesgloseCotizacionNacional({ datos, cargando })` muestra Mercancía, Flete, Reexpedición, Guía, Seguro y Total con `dinero()`.

- [ ] **Step 1: Tests.** (a) la sección tiene dos regiones con `aria-label` «Dirección de entrega» y «Costo de envío»; (b) la casilla «Incluir seguro» inicia desmarcada en un pedido sin seguro; (c) al marcarla y escribir flete 200 se llama a la vista previa y se ve «Seguro $30.00» (mock de axios); (d) con paquetería sin `cobertura_seguro` no se muestra la casilla; (e) flete vacío no llama al servidor ni muestra «NaN».
- [ ] **Step 2:** Correr; esperado: fallan.
- [ ] **Step 3:** Separar JSX en dos contenedores con el estilo de panel existente (`registrar-tienda-panel`/`theme-border`). Dirección: selector, ficha de la dirección, avisos. Costo de envío: tipo de guía, reexpedición, fletes por caja, seguro opcional y `DesgloseCotizacionNacional`. Al cambiar a una paquetería sin cobertura, forzar `seguro` a `false`.
- [ ] **Step 4:** Implementar el hook; mostrar el desglose guardado (el actual `desglose`) cuando no hay captura en curso.
- [ ] **Step 5:** Correr `ContinuacionEnvioNacional.test.jsx` y `FormularioRegistrarNacional.test.jsx`; esperado: pasan. Revisar a 375 px y 1280 px con el procedimiento de `GUIA_EVIDENCIA_VISUAL.md`.

### Task 4: Aviso sin direcciones y enlace-formulario desde el modal

**Files:**
- Modify: `ContinuacionEnvioNacional.jsx` (bloque Dirección) 
- Reutilizar: `resources/js/Pages/ControlPedidos/Partials/ModalGenerarLinkDireccion.jsx` (props `abierto`, `onClose`, `clientePreseleccionado`)
- Verificar/modificar si hace falta: `app/Services/Clientes/Direcciones/GenerarEnlaceDireccionService.php` y `routes/web/modules/control-pedidos/core.php:114` (`control_pedidos.enlace_direccion`)
- Test: `ContinuacionEnvioNacional.test.jsx`; backend `tests/Feature/Clientes/Direcciones/` (nuevo `EnlaceSinPrincipalTest.php`)

- [ ] **Step 1: Tests.** UI: con `direcciones = []` aparece `role="status"` «Este cliente no tiene dirección principal ni adicional. Agregue una o envíe el enlace para que el cliente la registre.» y los botones «Agregar dirección» y «Enviar enlace al cliente» (este último solo con `puedeAgregarDireccion`); sin permiso, solo el aviso con instrucción de pedirlo al auxiliar. Backend: un enlace para cliente sin ninguna dirección permite guardar como adicional y como principal sin exigir principal previa.
- [ ] **Step 2:** Correr; esperado: fallan.
- [ ] **Step 3:** Añadir el botón «Enviar enlace al cliente» que abre `ModalGenerarLinkDireccion` con `clientePreseleccionado={{ id: pedido.cliente_id, nombre: pedido.cliente_nombre }}` (confirmar el nombre real del campo del cliente en el pedido). Si el backend exige hoy una principal previa, quitar esa exigencia en el servicio y que `AplicarDireccionPublicaDesdeEnlaceService` marque principal solo si es la primera.
- [ ] **Step 4:** Tras guardar la dirección por enlace, el pedido debe poder recargarla (`Vuelva a abrir el pedido` hoy); añadir un botón «Actualizar direcciones» que repite la consulta de `/api/clientes/id/{id}/direccion-envio`.
- [ ] **Step 5:** Correr tests de UI y de `tests/Feature/Clientes/Direcciones`; esperado: pasan.

### Task 5: Formulario público de dirección más cómodo y validado

**Files:**
- Modify: `resources/js/Pages/Clientes/Direcciones/FormularioPublico.jsx` (hoy 257 líneas; teléfono ~línea 100, CP ~161)
- Create: `resources/js/utils/formatoContacto.js` (`formatearTelefono(valor: string): string` → `55 1234 5678`; `soloDigitos(valor: string, max: number): string`)
- Modify: `app/Http/Requests/Clientes/Direcciones/StoreDireccionPublicaDesdeEnlaceRequest.php:58-82`
- Test: `resources/js/utils/formatoContacto.test.js`, `FormularioPublico.test.jsx` (nuevo), `tests/Feature/Clientes/Direcciones/DireccionPublicaValidacionTest.php`

- [ ] **Step 1: Tests.** Utilidades: `formatearTelefono('+52 (55) 1234-5678')` → `'55 1234 5678'` (quita lada +52, máximo 10 dígitos). Servidor: teléfono con letras o de menos de 10 dígitos → 422 con mensaje en español; CP de 4 dígitos o con letras → 422 salvo domicilio irregular. UI: al teclear teléfono se ve formateado; error visible por campo al salir de él (blur) y se limpia al corregir.
- [ ] **Step 2:** Correr; esperado: fallan.
- [ ] **Step 3:** Implementar. Teléfono con `type="tel"`, `inputMode="tel"`, `autoComplete="tel-national"`; CP `inputMode="numeric"`, `autoComplete="postal-code"`. Validación en blur y envío (skill `forms`), etiquetas visibles, errores sin culpar. Agrupar en pasos legibles manteniendo las secciones; en móvil una columna, botón principal fijo al pie del formulario si no rompe el flujo. El servidor guarda el teléfono normalizado a 10 dígitos. Correo: el formulario no tiene campo de correo; **no añadir uno**; si el usuario lo pide, es un cambio aparte.
- [ ] **Step 4:** Correr los tests; esperado: pasan. Verificar a 375 px y 1280 px y ejecutar `ux-audit` sobre el formulario.

### Task 6: Documentación y comprobación final

**Files:**
- Modify: `gestion-pedidos-docs/GELIA_GESTION_PEDIDOS_GUIA_OPERATIVA_V3.1_2026-11-21.md` (recorrido nacional/CEDIS y tabla §15 con fecha 2026-11-24, reglas sustituidas, decisión del usuario, casos)
- Modify: `resources/js/Pages/Soporte/Manuales/content/control-pedidos/` donde se citen los textos renombrados

- [ ] **Step 1:** Registrar en §15: seguro opcional apagado por defecto con desglose previo; «Seleccionar paquetería»; «Enviado a sucursal»; enlace-formulario sin principal previa; validaciones del formulario. Incluir el flujo realista de pruebas: cliente sin direcciones → enlace → dirección → cotizar con seguro DHL → desglose $75.00.
- [ ] **Step 2:** Correr solo las pruebas dirigidas de las Tasks 1-5 (sin suites completas); esperado: todas pasan.
- [ ] **Step 3:** Informar al usuario qué comprobar con `./update-prod-sim.sh` y listar lo no verificado (recorrido con cuentas reales).

---

**Fuera de este plan:** el bug del pedido municipal 9797 (desaparece tras responder disponibilidad) se diagnostica por separado con `superpowers:systematic-debugging`.
