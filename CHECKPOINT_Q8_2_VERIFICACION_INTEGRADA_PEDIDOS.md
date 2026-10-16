# Q8.2 — Verificación integrada en navegador y cierre de fixes de pedidos

Fecha: 16 de octubre de 2026, America/Mexico_City.

## Entorno efectivo

- Rama `main`. HEAD `83a5ac1aad034c13be82c7d424d7b0cb73c764f3` (“Q6.1 terminado”). El árbol local de Q5.1, Q5.2, Q6, Q6.1, Q8 y Q8.1 se conservó. No hubo commit ni push.
- `APP_ENV=local`. La URL de negocio `http://100.75.11.59:9080` coincide con `APP_URL`. `/login` responde 200. El navegador integrado recorrió esa URL con la sesión ya autenticada.
- Contenedor Sail `gelianv-laravel.test-1` (imagen `sail-8.4/app`). MySQL 8.4, base `laravel`. El código nuevo se mantiene compatible con PHP 8.2.
- Sesión usada: “Jesus Gabriel”, rol Administrador (id 1). No se modificó autenticación ni se ampliaron permisos. No hay cuentas separadas de Ventas, Auxiliar, preparador de Tienda o CEDIS en esta comprobación: los recorridos se hicieron con ese administrador.

## Esquema

`./vendor/bin/sail artisan migrate:status` no mostró migraciones pendientes. No se ejecutó `migrate` porque no había nada que aplicar. No se usó `migrate:fresh`, `refresh`, `reset`, rollback ni `--force`.

Presencia efectiva en la base (ya aplicada, no pendiente como decía el checkpoint Q8):

- Q6 cantidades: batch 171.
- Q6.1 abastecimiento: `solicitudes_traspasos.tarea_receptora_id`, `proposito` y tabla `traspaso_operaciones` (batch 172).
- Q8: `pedido_bma_revisiones_producto.retirada_at` y `pedido_bma_pagos.idempotency_key` (batch 172).

## Recursos y optimización

- `./vendor/bin/sail npm run build` publicó el manifiesto. No existe `public/hot`.
- El navegador cargó `http://100.75.11.59:9080/build/assets/app-I1Kv76j9.js` después del último build (badge de CEDIS y plural del listado). Un build intermedio de esta sesión había publicado `app-BzTBFIrq.js`.
- `./vendor/bin/sail artisan optimize:clear` y `optimize` se ejecutaron antes de las pruebas de navegador y terminaron bien. El cambio posterior del controlador de auditoría es PHP y se comprobó recargando la página, sin otro build.

## Perfiles y recorridos comprobados en el navegador

Emulación de viewport (no acredita un dispositivo físico): 1366×900, 390×844 y 360×800, con `mobile: true`. El tema de la sesión es el oscuro.

### Ventas

Modal de pedido nuevo en escritorio y a 390/360. Cabecera compacta “Pedido”, fase “Datos del cliente y pedido”, tipo Tienda/Envío, aviso, resumen (mercancía, envío, seguro, SAF, a cobrar) y “Opciones de captura” con Limpiar. “Guardar borrador” es la acción principal. Sin desbordamiento horizontal. A 360×800 el pie queda dentro del viewport. El cuerpo tiene scroll propio. No se completó una captura nueva ni se comprobó el autoguardado de un pedido recién creado.

### Auxiliar

Bandeja “Revisión de pedidos” en ` /control-pedidos/auditar`. Encabezado, métricas (Pendientes 1, Corregidos 1, Aprobados 13) y folios internos etiquetados. Tras el arreglo de la primera pintura, la pestaña Pendientes muestra “1 pedido · Pendientes” desde la carga, sin el listado completo de 14. No se aprobó ni se rechazó un pedido.

### Preparación Tienda

Se abrió la preparación real del folio de remisión 325235 (tarea 16, pedido 39, `PBMA-2026-00036`). Escritorio: dos paneles, referencia del PDF y “Volver a productos”. Móvil 390: un solo desplazamiento del modal, sin pie fijo alto, navegación Productos/Evidencias/Cierre, acciones al final con altura usable, sin anaqueles. Búsqueda por código `85715166708` en almacén CARMEN CADENA, guardado parcial y reapertura con “Piezas solicitadas: 1”. El SKU `8054320903093` respondió, de forma correcta, que no está en ese almacén.

### CEDIS

Cabecera de tipo TIENDA frente a ENVÍO, traslado interno aparte, folio interno etiquetado, almacén de salida y destino, paquetería y guía. Detalle de 5625859 y de 21430 (`PBMA-2026-00017`): documentos, evidencias y revisión física. Búsqueda `21430` deja un resultado. El plural quedó “1 resultado para “21430””. En el detalle de 21430, la pieza Polo Red muestra el badge “Única pieza”, que coincide con `unica_pieza=1` en `pedido_bma_revisiones_producto` id 38.

## Registros de prueba

No se eliminó evidencia ni registros financieros.

- Tarea de preparación 16, pedido 39, remisión 325235. Pasó a `EN_ATENCION`, asignada al usuario 1 el 2026-10-16 09:41:37. Modalidad Recoge en tienda, almacén 2. No se confirmó el apartado.
- Línea `pedido_bma_tarea_productos` id 13: SKU `85715166708`, descripción “C ABERCROMBIE & F. 100% PASSION 100ML. EDT”, cantidad 1, estado físico bueno, creada 2026-10-16 09:44:58.
- El cliente `Q82-BUSQ` existe solo en la prueba automática (SQLite). No se insertó en MySQL.

## Defectos, causas y fixes

1. **Búsqueda de Tienda devolvía la lista anterior.** `ListarTareasTiendaService` filtraba `clientes.nombre_comercial`, columna inexistente. `useListadoDiscreto` tragaba el error. Se consulta `nombre_razon_social`, el orden usa un parámetro en lugar de `NOW()` (MySQL-only, rompía PHPUnit) y el listado de Tienda muestra un aviso si la actualización falla. Comprobado en MySQL y en el navegador: `325235` deja una preparación.
2. **Plural del listado CEDIS.** “1 resultados” en `Cedis/Index.jsx`. Recompilado y visto en el navegador como “1 resultado”.
3. **Detalle CEDIS no mostraba “Única pieza” ni “Mejor ejemplar”.** El flag sí llegaba y clasificaba la fila; el texto no se pintaba. `ModalDetalleCedis.jsx` ahora usa los mismos badges que la revisión física. Recompilado y visto en el detalle de 21430. Prueba en `trasladosQ6Presentacion.test.jsx`.
4. **Auxiliar pintaba primero toda la bandeja en Pendientes.** El índice no enviaba pestaña y el servicio usa `TODAS` si falta; la interfaz abre en Pendientes. El controlador de auditoría usa `PENDIENTES` cuando la petición no trae pestaña. Recarga comprobada: “1 pedido · Pendientes” desde el primer muestreo.

## Pruebas automáticas

Subconjunto, no la suite completa:

- `PreparacionTiendaWorkspaceTest`: 6 pruebas, 63 aserciones, pasan (incluye búsqueda por remisión y razón social).
- `trasladosQ6Presentacion.test.jsx`: 3 pruebas, pasan (incluye los badges).

## Pendientes y limitaciones

- No se ejecutaron de punta a punta los recorridos A (pagos exactos, SAF real, parcial, excedente, reintento), B (consulta, retirada, sustitución, cantidades, bloqueo de empaque), C (traslados CEDIS/PDV, abastecimiento, recepción parcial, custodia, envío externo) ni D (captura completa de folio, remisión, municipio, paquetería y direcciones).
- No se comprobó autoguardado de un pedido nuevo ni la referencia de Tienda contraída al abrir en frío a 390.
- La emulación no sustituye un teléfono físico. No se dispararon paquetería real ni notificaciones a clientes.
- La etiqueta “Vendedora” en preparación sigue en el código; no se cambió en este encargo.
- El inventario sigue sin sincronización.

**Q8 no se propone como cerrado.** Quedan recorridos acordados sin comprobación real.

## Archivos tocados en Q8.2

- `app/Services/ControlPedidos/ListarTareasTiendaService.php`
- `resources/js/Pages/ControlPedidos/Partials/useListadoDiscreto.js`
- `resources/js/Pages/ControlPedidos/Tienda/Index.jsx`
- `resources/js/Pages/ControlPedidos/Cedis/Index.jsx`
- `resources/js/Pages/ControlPedidos/Cedis/Partials/ModalDetalleCedis.jsx`
- `resources/js/Pages/ControlPedidos/Cedis/Partials/trasladosQ6Presentacion.test.jsx`
- `app/Http/Controllers/ControlPedidos/PedidoBmaAuditoriaController.php`
- `tests/Feature/ControlPedidos/PreparacionTiendaWorkspaceTest.php`

El resto del árbol sucio pertenece a Q8/Q8.1 y trabajo previo. No se revirtió.

## Reversión

Para deshacer solo el código de Q8.2, restaurar los ocho archivos de la lista anterior desde el HEAD o desde la copia previa a este encargo. No ejecutar `down()` de las migraciones de cantidades, abastecimiento, `retirada_at` ni `idempotency_key`: esas columnas ya tienen datos y la reversión ordinaria no debe borrarlas. Conservar la tarea 16 y la línea de producto 13.
