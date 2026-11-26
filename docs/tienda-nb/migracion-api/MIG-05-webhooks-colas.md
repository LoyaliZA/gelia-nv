# MIG-05 · Webhooks, colas y tareas operativas

Fecha: 11 de septiembre de 2026. Proyecto: gelia-nv / módulo TiendaNube.
Destino: API REST 2025-03. Referencia de código previa: cliente y configuración leídos en `main` durante esta conversación; Cursor debe fijar el SHA actual antes de editar. No hay prueba contra la tienda ni implementación realizada en este documento.

## Alcance de esta tarea

Ejecutar solo MIG-05, en una rama/revisión acotada. Leer instrucciones del repositorio. No incorporar automáticamente pasos posteriores ni reconstruir todo el módulo. Mantener las mejoras TN-01 a TN-06. Toda migración de datos es aditiva y preserva identidad/historial.

## Objetivo y dependencia

Requiere MIG-01 a MIG-04. Asegurar que cambios externos y trabajos pendientes utilizan la misma versión y modelo que las operaciones manuales. Preservar recuperación, deduplicación/idempotencia y coordinación existentes.

## Artefactos

### Entradas

| Archivo | Uso en MIG-05 |
|---------|----------------|
| [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md) | Webhooks CRUD; GET producto/categoría; jobs listados |
| [MIG-02-mapeo-lectura-catalogo.md](./MIG-02-mapeo-lectura-catalogo.md) | Normalización en jobs de sync/webhook |
| [MIG-03-payload-stock.md](./MIG-03-payload-stock.md) | Jobs no deben escribir stock plano |
| [MIG-04-payloads-escritura.md](./MIG-04-payloads-escritura.md) | Misma lista permitida en cola |
| [MIG-04-reconciliacion-operaciones.md](./MIG-04-reconciliacion-operaciones.md) | Import/imagen jobs |

### Entregables de esta fase

| Archivo | Contenido |
|---------|-----------|
| [MIG-05-inventario-jobs-webhooks.md](./MIG-05-inventario-jobs-webhooks.md) | Jobs, comandos, eventos webhook; versión API; lector/escritor |
| [MIG-05-procedimiento-pausa.md](./MIG-05-procedimiento-pausa.md) | Pausa/drenaje/reinicio; compatibilidad jobs encolados |

MIG-06 ejecuta [MIG-05-procedimiento-pausa.md](./MIG-05-procedimiento-pausa.md) en el corte.

## Pasos

1. Inventariar jobs de catálogo, imágenes, webhooks, recuperadores y comandos de auditoría/limpieza en [MIG-05-inventario-jobs-webhooks.md](./MIG-05-inventario-jobs-webhooks.md). Cruzar con consumidores de [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md). Registrar versión de payload interno, generación de tienda y lector/escritor usado. No asumir que reiniciar web corrige workers residentes.
2. Revisar documentación oficial de Webhook; anotar eventos soportados en [MIG-05-inventario-jobs-webhooks.md](./MIG-05-inventario-jobs-webhooks.md). Distinguir versión de ruta REST de formato/versionado de webhooks; no cambiar HMAC por haber cambiado `/v1`. No duplicar registros remotos sin comparar evento/URL.
3. Mantener recepción durable durante mantenimiento. Los eventos recibidos se guardan y pueden diferirse; la firma se valida sobre cuerpo original. El parser transforma evento a operación de catálogo usando normalización MIG-02; no usar payload incompleto para sustituir todo el producto.
4. Hacer que los jobs consulten MIG-01/02/03 según [MIG-05-inventario-jobs-webhooks.md](./MIG-05-inventario-jobs-webhooks.md). Stock vía [MIG-03-payload-stock.md](./MIG-03-payload-stock.md). Si existe evento específico de niveles/ubicaciones útil y documentado, incorporarlo con prueba; si no, definir reconciliación de lectura programada sin inventar eventos.
5. Definir compatibilidad de jobs ya encolados en [MIG-05-procedimiento-pausa.md](./MIG-05-procedimiento-pausa.md): drenar antes de corte o adaptarlos con versión explícita. No cambiar la generación de tienda solo para perder silenciosamente trabajos.
6. Coordinar sync, escrituras, importación, limpieza y cambio de configuración con el servicio de operaciones existente; referencias en [MIG-05-inventario-jobs-webhooks.md](./MIG-05-inventario-jobs-webhooks.md). Probar liberación tras excepción/timeout y recuperación de entregas diferidas.
7. Revisar desinstalación y privacidad; documentar en [MIG-05-inventario-jobs-webhooks.md](./MIG-05-inventario-jobs-webhooks.md). La migración no debe ampliar acceso ni modificar reglas de privacidad sin necesidad documentada.
8. Añadir diagnóstico (versión API, duración, cola, último sync) en [MIG-05-inventario-jobs-webhooks.md](./MIG-05-inventario-jobs-webhooks.md), sin secretos. Mantener comandos programados funcionales y documentar cron/worker necesarios.

## Archivos iniciales

`TiendanubeWebhookController`, `ProcessTiendanubeWebhook`, `TiendanubeWebhookInboxService`, `TiendanubeWebhookService`, `TiendanubeOperacionTiendaService`, jobs Tiendanube, `routes/console.php` y comandos asociados.

## Aceptación

- [ ] Firma válida/inválida conserva tratamiento esperado.
- [ ] Dos eventos legítimos iguales se procesan sin pérdida permanente por hash.
- [ ] Evento durante sync se conserva y aplica después.
- [ ] Caída de broker/worker se recupera con modelo nuevo.
- [ ] Jobs antiguos no escriben con semántica de inventario incorrecta.
- [ ] Registro de webhooks no crea duplicados innecesarios.
- [ ] Cambio de tienda impide aplicar trabajos a otra tienda y deja evidencia.

## Reversión

Pausar consumo incompatible preservando recepción y bandeja según [MIG-05-procedimiento-pausa.md](./MIG-05-procedimiento-pausa.md). No descartar jobs para despejar cola. Reconciliar efectos remotos antes de reejecución. Entregar inventario y procedimiento a MIG-06.

## Fuentes y verificación

- [Recursos oficiales 2025-03](https://tiendanube.github.io/api-documentation/resources).
- [Introducción y transporte](https://tiendanube.github.io/api-documentation/intro).
- [Ubicaciones](https://tiendanube.github.io/api-documentation/resources/location).
- [Variantes](https://tiendanube.github.io/api-documentation/resources/product-variant).
- [Inventario por ubicación](https://tiendanube.github.io/api-documentation/guides/multi-inventory/products).

Consultar la referencia específica de cada endpoint y la capacidad real de la tienda al implementar. No asumir que disponibilidad documental equivale a permiso/capacidad de la cuenta. No está acreditada aquí una fecha de apagado de v1.

## Instrucción para Cursor

> Implementa exclusivamente MIG-05, comprobando sus dependencias en el checkout actual y usando documentación oficial 2025-03. **Lee primero** los artefactos en **Entradas**. Crea o actualiza [MIG-05-inventario-jobs-webhooks.md](./MIG-05-inventario-jobs-webhooks.md) y [MIG-05-procedimiento-pausa.md](./MIG-05-procedimiento-pausa.md). Antes de modificar código, concreta contratos y archivos afectados. Conserva las mejoras existentes; no añadas funcionalidades comerciales de TN-07 ni otros recursos API ajenos al catálogo. Reporta evidencia por criterio de aceptación, pruebas realmente ejecutadas, migraciones, compatibilidad de jobs y pendientes. No cierres como completo un punto crítico no validado. Una tarea de implementación no autoriza por sí sola despliegue o escrituras de prueba sobre productos reales.
