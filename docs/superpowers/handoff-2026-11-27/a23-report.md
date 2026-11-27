# Tasks A2 + A3 — Etiqueta de grupo, refresco en vivo y panel «Multienvío» (frontend)

Status: DONE_WITH_CONCERNS (sin commit; sin build; sin PHP tocado; sin validación visual en la app real)

## Archivos
Nuevos
- `resources/js/Pages/ControlPedidos/Partials/EtiquetaMultienvio.jsx` — `textoMultienvio`, `firmaMultienvio`, píldora `<EtiquetaMultienvio>` (lucide `Layers`, tono neutral `theme-border theme-element`, icono + texto).
- `resources/js/Pages/ControlPedidos/Partials/EtiquetaMultienvio.test.jsx` (6 pruebas).
- `resources/js/Pages/ControlPedidos/Partials/detalle-pedido/SeccionMultienvioDetalle.jsx` — sección «Multienvío» del detalle con hermanos (Envío n, folio Wizerp, folio GELIA, estado con icono + texto, hechos pago/remisión/guía, «Este pedido» con `aria-current`).
- `resources/js/Pages/ControlPedidos/Nacional/useMultienvioNacional.js` — GET/POST decision/PUT borrador/POST confirmar con `revision` vigente; `erroresMultienvio` (422 por campo `folio_remision|total_mercancia|revision|envios|multienvio`, 403 → texto de rol); guarda de doble confirmación por `ref`; descarta lecturas viejas (turno); revisión obsoleta → recarga + aviso; opción `onConfirmado`.
- `resources/js/Pages/ControlPedidos/Nacional/PanelMultienvioNacional.jsx` — panel (aviso, configuración, activo, regularización, pedido estándar sin seguro) + `centavos()` + `multienvioBloqueaCotizacion()` + `HECHOS_REGULARIZACION`.
- `resources/js/Pages/ControlPedidos/Nacional/PanelMultienvioNacional.test.jsx` (16 pruebas).
- `resources/js/Pages/ControlPedidos/Nacional/FormularioRegistrarNacional.multienvio.test.jsx` (5 pruebas de integración).

Modificados
- `Partials/EncabezadoFolioPedido.jsx` — `<EtiquetaMultienvio>` junto a `EtiquetaAnexo` (solo si hay `pedido.multienvio`; sin grupo el markup previo queda idéntico). Al vivir en el encabezado, llega a tablas, tarjetas CEDIS/Delegado/Auditar, detalle y bitácora y no entra en `MAX_BADGES_LISTADO`.
- `Partials/detalle-pedido/CuerpoDetalleNacional.jsx` — `<SeccionMultienvioDetalle>` tras «Datos del pedido».
- `Index.jsx` — comparación `mismo` del refresco en vivo: `multienvio_nacional_id`, `multienvio_orden` y `firmaMultienvio` (id, orden, n_total, estado, parcial y avance de hermanos).
- `Nacional/FormularioRegistrarNacional.jsx` — hook + panel justo antes del bloque `ContinuacionEnvioNacional`; la cotización se oculta mientras `multienvioBloqueaCotizacion`; suciedad del borrador sumada a `onDirtyChange`; tras confirmar relee `GET /control-pedidos/{id}/consulta-nacional` y `adoptar`.

## Comportamiento
- Se consulta el multienvío solo con consulta cerrada, sin cambio a Tienda y con ≥2 cajas activas (no `retirada|cancelada`) o con `actual.multienvio` ya presente. Con 1 caja o consulta abierta no hay GET ni aviso. `clave` de relectura: `consulta_cerrada_at|multienvio.estado|n_total|uuids de cajas activas`.
- Cotización retenida: decisión pendiente (`elegible` y grupo nulo/`pendiente_decision`) o `configurando`. Mientras carga la primera lectura se retiene solo si hay ≥2 cajas y el resumen es nulo/pendiente/configurando (estado «Revisando las cajas del envío…»). Si la lectura falla se muestra la cotización (fail-open, el backend no bloquea la cotización) con «Revisar de nuevo».
- Aviso: `AvisoOperativoPedido` tono info, `Layers`, label «Multienvío detectado», copy exacta pedida, tipos/peso de `cajas_resumen` en apoyo; botones «Activar multienvío con seguro» (primario, `ShieldCheck`) y «Continuar sin seguro»; texto «Podrás activar el multienvío posteriormente si el cliente cambia de opinión.».
- Sin seguro + `elegible`: línea «El pedido continúa como un solo envío sin seguro por caja.» y «Activar multienvío con seguro» (ME-06). La cotización se muestra.
- Configuración: una tarjeta por `cajas_origen` (Caja n.º, tipo · peso · L × A × H cm, miniaturas de evidencia de CEDIS con `MiniaturaDocumento`, abren `onVer(evidencias, i)`); campos *Folio de pedido Wizerp*, *Valor de mercancía (MXN)* (`type="text" inputMode="decimal"`, `,`→`.`, regex `^\d+(?:\.\d{1,2})?$`), *Contenido (opcional)*. Totales: total del pedido, suma, diferencia en centavos enteros («Cuadra con el total» / «Faltan $x» / «Sobran $x», icono + texto, `role="status"`). «Confirmar multienvío» deshabilitado con motivo (`aria-describedby`) hasta: folios llenos y distintos (sin distinguir mayúsculas, igual que el backend), montos válidos y diferencia 0. «Continuar sin seguro» disponible.
- Borrador: autoguardado `PUT borrador` con espera de 600 ms y al salir de cada campo; una petición a la vez; versión local vs guardada → suciedad (`onDirtyChange`) y estado «Guardando borrador… / Cambios sin guardar. / Borrador guardado.». Una lectura nueva solo completa cajas no editadas localmente; nunca pisa lo tecleado. Con folios repetidos locales no se envía el borrador (el servidor lo rechazaría entero) y se indica. Valor vacío/ inválido se envía como 0 (el backend exige numérico); al reabrir, 0 se muestra vacío.
- Confirmar: primero vacía el borrador pendiente y luego `POST confirmar {revision}`; doble clic → una sola petición (guarda por ref + botón deshabilitado). Tras `activo` o `regularizacion_pendiente` se relee la consulta nacional y se adopta (el origen queda como envío 1 con su importe/caja); la clave cambia y se relee la proyección.
- Errores: 422 `folio_remision` marca la caja cuyo folio aparece en el mensaje (si no se reconoce, la primera) con `aria-invalid` + mensaje `role="alert"` enlazado; `total_mercancia` bajo los totales; `envios`/`multienvio`/general como alerta del panel; `revision` → recarga la proyección, aviso «Las cajas de la consulta cambiaron… lo que capturaste se conserva» y, en configuración, botón «Actualizar con las cajas vigentes» (`decision: activar` con la nueva revisión, que es la salida que el backend prevé en `configurando`).
- Activo: «Este pedido se repartió en N envíos…» (o, con `parcial`, «Solo ves los envíos a los que tienes acceso» sin total), chips de progreso «Pagos validados x de n», «Remisiones», «Guías», «Enviados», lista Envío n · folio Wizerp · folio GELIA · estatus con icono · pago · «Este pedido».
- Regularización: aviso warning «Multienvío pendiente de regularización», texto de que Auxiliar y Ventas deben regularizar y que el pedido sigue como un solo envío, lista de hechos con etiquetas: Pago capturado, Pago validado, Cierre de pago o saldo a favor aplicado, Remisión registrada, Guía registrada, Empaque registrado (`empacada`), Salida parcial de cajas, Cancelación en curso, Complemento o anexo relacionado. Sin botones.
- Etiqueta: «Envío n de N»; «Envío n» si `parcial`; origen sin orden: «Multienvío en configuración» / «Multienvío por regularizar». Nada sin grupo.

## TDD
- Baseline (antes de cambios), `npm test -- resources/js/Pages/ControlPedidos/Nacional/ resources/js/Pages/ControlPedidos/Partials/`: 62 archivos, **470 passed, 2 failed** (`Partials/modalElegibilidadCedisPdv.test.jsx` › «envío desde CEDIS muestra el almacén antes de la paquetería» y «cambiar Tienda a Envío retira la intención de recogida…»; preexistentes, no relacionados).
- EtiquetaMultienvio: RED «Failed to resolve import ./EtiquetaMultienvio» → GREEN 4/4; luego se añadieron detalle de hermanos y `firmaMultienvio` (6/6).
- Integración FormularioRegistrarNacional: RED 3 fallos / 2 passed (las dos negativas pasaban por ausencia de panel) → GREEN 5/5.
- Panel + hook: el componente se escribió antes que sus pruebas (desvío de TDD, declarado). Para comprobar que las pruebas detectan fallos se hizo una mutación temporal en la copia de `/home/ti-admin/gelianv` (quitar la guarda de doble confirmación, la preservación de filas editadas y el manejo de `revision`): 3 fallos esperados («una lectura nueva no pisa…», «una revisión obsoleta…», «doble clic…»); restaurado → 16/16.
- Final, mismos directorios: 65 archivos, **497 passed, 2 failed** (los mismos 2 del baseline). +27 pruebas nuevas.
- Extra: `Delegado`, `Cedis`, `Auditar` (usan `EncabezadoFolioPedido`): 16 archivos, 71 passed, 0 failed.
- Comando: rsync de `git diff --name-only HEAD` + no rastreados a `/home/ti-admin/gelianv`, luego `docker exec -u sail -e NODE_OPTIONS=--no-experimental-webstorage gelianv-laravel.test-1 npm test -- <rutas>`.

## Decisiones de UI
- Píldora neutral (`theme-border theme-element`, `Layers`) para no reutilizar el ámbar de Anexo ni un color de estado; DESIGN.md: un color significa una sola cosa.
- El panel usa `registrar-tienda-panel` + `registrar-tienda-seccion-titulo` como la sección «Envío nacional»; botones `THEME_BTN_PRIMARY`/`GELIA_BTN_OUTLINE` con icono lucide y `min-h-[44px]`; formularios en una columna a ancho de teléfono (`sm:grid-cols-2`).
- Validación al salir del campo (blur-first) o al intentar confirmar; el error se limpia al teclear.
- La lista de envíos del estado activo es de lectura: el modal no tiene navegación a otro pedido; cada envío se abre desde el listado (flujo normal).

## Pendiente / no verificado
- Sin validación visual en la app real (1440/1024/390, claro/oscuro, consola): no se ejecutó build (lo hace el controlador) ni navegador. Las pruebas son happy-dom.
- No se actualizaron `docs/ai/modulos/REGISTRO_PEDIDOS.md` ni la guía V3.1 §15 con la parte de interfaz (se deja al cierre de bloque B5 / controlador).
- Backend: `ProyeccionMultienvio` no expone `grupo.revision_consulta`; el front envía `evaluacion.revision`. Si las cajas cambian en `configurando`, confirmar responde 422 `revision` y la salida es «Actualizar con las cajas vigentes». No falta ningún campo para esta tarea.
- El valor vacío se guarda como 0 en el borrador (restricción del validador `numeric` del backend); un valor real de 0.00 se mostraría vacío al reabrir.
- Al recibir 422 `folio_remision` el borrador completo no se guarda (el servicio reemplaza todo); la captura local se conserva y queda «Cambios sin guardar» hasta corregir.
