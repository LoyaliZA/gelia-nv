# TN-07F · Aplicación masiva por API y recuperación

Fecha: 11 de septiembre de 2026. Proyecto: `gelia-nv`, módulo TiendaNube.
Base conocida: commit `5eeb392f0d90b62cdb292c5326b98398a2d787a7`. No se revalidó el checkout hoy. Este documento especifica trabajo por implementar; no afirma que sus componentes propuestos existan ni que se hayan ejecutado pruebas.

## Regla de alcance

Ejecutar únicamente TN-07F. Leer instrucciones del repositorio y contrastar las rutas citadas con el código actual. Conservar las correcciones previas del módulo. Resolver dependencias usando sus contratos; si falta una, reportarla sin implementar silenciosamente otro plan. Un archivo por tarea y revisión propia; no reemplazar archivos compartidos con copias antiguas.

El sistema usa costo general capturado/importado y listas opcionales de nombres configurables. No impone fuente comercial. Unidad de operación: tienda/producto/variante. Las referencias monetarias se mantienen separadas de los valores remotos publicados. Sin automatización comercial por fecha o disparador en esta primera versión.

## Objetivo y dependencia

Aplicar importes absolutos aprobados en D a variantes de TiendaNube con recuperación por ítem y detección de conflictos. No requiere E. Respetar coordinación y validación del módulo existente. No reimplementar motor B ni aplicar porcentajes durante un reintento.

## Contrato de ejecución

Ejecución enlazada a lote/revisión/tienda/generación. Ítems con clave estable por revisión/variante, antes/propuesto/confirmado, campos objetivo, intentos, token de adquisición, vencimiento, siguiente intento y error. Estados: pendiente, procesando, confirmado, conflicto, reintento_pendiente, resultado_incierto, fallido, cancelado. Lote puede terminar parcial.

## Pasos de implementación

1. **Integración.** Inspeccionar `TiendanubeApiClient`, `TiendanubeProductoWriteService` y `TiendanubeOperacionTiendaService`. Reutilizar validación de pertenencia y coordinación. Si hace falta, crear método exclusivo de precios para no reenviar stock, SKU ni campos del producto.
2. **Admisión atómica.** Verificar aprobación y permisos en servidor, bloquear ejecución duplicada de la misma revisión y persistir trabajo antes de encolar. Comprobar generación de tienda. Un fallo de broker deja pendiente recuperable.
3. **Control previo remoto.** Leer variante/producto y comparar con snapshot: campos objetivo más campos relevantes para validez, como precio normal cuando se cambia promoción. Si ya coincide con objetivo, registrar coincidencia sin nueva escritura y sin atribuir autoría no demostrada. Si cambió desde origen, conflicto; si variante no existe, fallo explicativo.
4. **Bloqueos.** Adquirir operación de tienda/recurso e ítem con propietario y lease conforme al coordinador existente. No sostener transacción SQL durante HTTP. Renovar o dimensionar lease respecto a timeout. Trabajador vencido no finaliza una adquisición nueva.
5. **Escritura absoluta.** Enviar solo costo/normal/promocional que tengan intención de cambio. Verificar contrato API de eliminar promoción: no copiar el guion CSV. Leer resultado final, actualizar espejo y guardar confirmación/evento.
6. **Recuperación.** Timeout después del PUT implica resultado incierto. Releer: coincide con objetivo → confirmar; con origen → reintentar según política; otro → conflicto. Si no se puede leer, mantener incierto. Respetar rate limit, backoff y límite de intentos; detener nuevos envíos si credenciales fallan.
7. **Control de ejecución.** Cancelación detiene pendientes, no revierte confirmados. Reintento solo recuperables. Recuperador programado es infraestructura de jobs, no programación comercial de reglas. Registrar avance durable que no dependa del navegador.
8. **Interacción con CSV.** Si el mismo objetivo llegó por importación manual, no incrementar otra vez. Guardar canal y evidencia. Un CSV descargado puede aplicarse después; no garantizar exclusión frente a archivos externos.

La lectura previa no garantiza atomicidad remota. Verificar si API admite precondiciones/versiones; si no, documentar la ventana de carrera con ediciones externas y usar verificación posterior. No afirmar “cero conflictos” por tener un lock local.

## Archivos y persistencia

Servicios nuevos bajo `app/Services/Tiendanube/Precios/`, jobs en `app/Jobs/Tiendanube/Precios/`, migraciones de ejecuciones/ítems y endpoints aplicar/estado/cancelar/reintentar. Consumir solo `obtenerRevisionAprobada` de D. Persistir todos los eventos requeridos por G desde esta entrega.

## Pruebas y aceptación

- [ ] Aplicar dos veces misma revisión no duplica trabajos ni porcentajes.
- [ ] Variante ajena o generación cambiada produce cero escrituras.
- [ ] Cambio remoto posterior a simulación genera conflicto.
- [ ] Timeout con resultado ya aplicado se reconoce por relectura.
- [ ] Fallo parcial no marca todo el lote como exitoso.
- [ ] Caída de broker/worker se recupera sin navegador.
- [ ] Cancelación conserva confirmados y detiene pendientes.
- [ ] Payload no contiene stock, nombre ni categorías.
- [ ] API y snapshot aprobado tienen mismos importes.

## Despliegue y reversión

Migraciones aditivas, workers compatibles y permiso específico de aplicación masiva. Habilitar en lote de ensayo, luego ampliar. Ante regresión detener nuevas ejecuciones, conservar pendientes y conciliar inciertos. No restaurar automáticamente valores anteriores ni borrar historial. G implementará propuestas compensatorias.

## Instrucción específica para Cursor

> Trabaja exclusivamente en TN-07F. Lee este archivo, las instrucciones locales y los contratos de los requisitos previos. Confirma el estado real del checkout, reutiliza lo existente y concreta nombres/archivos nuevos según convenciones del proyecto. Implementa sus pasos en cambios acotados, con pruebas de aceptación y sin incluir otros planes. Consulta documentación oficial vigente si una decisión depende del framework o del proveedor. No presentes componentes propuestos como existentes. Si un requisito falta, delimita lo bloqueado. Reporta archivos, migraciones, pruebas realmente ejecutadas, criterios cumplidos, riesgos pendientes y reversión. No publiques ni despliegues por el solo hecho de implementar.

## Condición de cierre común

- [ ] Todas las pruebas específicas de este archivo están ejecutadas o su limitación está documentada; no declarar cierre completo si queda un criterio obligatorio bloqueado.
- [ ] Diff acotado al plan y contratos entregados al siguiente.
- [ ] Migraciones nuevas; no editar las ya aplicadas ni eliminar historial para resolver incompatibilidades.
- [ ] Pruebas de API simuladas; validaciones reales únicamente con entorno/productos de ensayo apropiados.
- [ ] Comandos de test/build elegidos según scripts del checkout; no inventar resultados.
