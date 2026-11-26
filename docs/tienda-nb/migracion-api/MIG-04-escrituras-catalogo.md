# MIG-04 · Escrituras de productos, variantes e imágenes

Fecha: 11 de septiembre de 2026. Proyecto: gelia-nv / módulo TiendaNube.
Destino: API REST 2025-03. Referencia de código previa: cliente y configuración leídos en `main` durante esta conversación; Cursor debe fijar el SHA actual antes de editar. No hay prueba contra la tienda ni implementación realizada en este documento.

## Alcance de esta tarea

Ejecutar solo MIG-04, en una rama/revisión acotada. Leer instrucciones del repositorio. No incorporar automáticamente pasos posteriores ni reconstruir todo el módulo. Mantener las mejoras TN-01 a TN-06. Toda migración de datos es aditiva y preserva identidad/historial.

## Objetivo y dependencia

Requiere MIG-01/02/03. Adaptar todas las funciones de escritura que el catálogo actual ofrece: creación simple, edición de producto/variante, precio/promoción/costo, categorías asignadas e imágenes individuales/masivas. Mantener alcance funcional existente; no añadir editor masivo TN-07 aquí.

## Artefactos

### Entradas

| Archivo | Uso en MIG-04 |
|---------|----------------|
| [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md) | POST/PUT/DELETE productos, variantes, imágenes; idempotencia POST |
| [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md) | `visibility`/`published`; Variant cost; Image POST |
| [MIG-02-mapeo-lectura-catalogo.md](./MIG-02-mapeo-lectura-catalogo.md) | Campos de producto/variante/imagen en lectura post-escritura |
| [MIG-03-payload-stock.md](./MIG-03-payload-stock.md) | **Única** fuente de semántica de stock en escritura |

### Entregables de esta fase

| Archivo | Contenido |
|---------|-----------|
| [MIG-04-payloads-escritura.md](./MIG-04-payloads-escritura.md) | Lista permitida por operación; separación producto / precios / inventario / imágenes |
| [MIG-04-reconciliacion-operaciones.md](./MIG-04-reconciliacion-operaciones.md) | Timeout POST, operaciones recuperables, cargar-antes-de-borrar imágenes |

MIG-05 y MIG-06 consultan estos entregables para jobs e idempotencia.

## Pasos

1. Revisar cada payload saliente y producir lista permitida por operación en [MIG-04-payloads-escritura.md](./MIG-04-payloads-escritura.md). Separar datos de producto, precios de variante, inventario ([MIG-03-payload-stock.md](./MIG-03-payload-stock.md)) e imágenes. Validar pertenencia de variante antes de cualquier escritura.
2. Actualizar creación simple para contrato nuevo en [MIG-04-payloads-escritura.md](./MIG-04-payloads-escritura.md), incluyendo ubicación explícita cuando se asignen existencias. Investigar respuestas ambiguas de creación y registrar operación recuperable en [MIG-04-reconciliacion-operaciones.md](./MIG-04-reconciliacion-operaciones.md); no crear otro producto después de timeout sin reconciliar.
3. Migrar escritura de stock a niveles con ubicación validada por [MIG-03-payload-stock.md](./MIG-03-payload-stock.md). Nunca enviar stock total como stock de la primera ubicación. Verificar experimentalmente si omitir otras ubicaciones conserva sus niveles; anotar en [MIG-04-payloads-escritura.md](./MIG-04-payloads-escritura.md). No asumir semántica de merge o reemplazo.
4. Revisar precios, costo y promoción en [MIG-04-payloads-escritura.md](./MIG-04-payloads-escritura.md): conservar/establecer/eliminar son intenciones distintas. Confirmar restricciones remotas actuales (matriz MIG-01 Variant). No copiar el guion de eliminación CSV al JSON.
5. Conservar el vaciado explícito de categorías y la edición por variante. Actualizar errores de validación sin convertir una operación parcial en éxito total. Releer para conocer estado real después de fallos parciales; documentar en [MIG-04-reconciliacion-operaciones.md](./MIG-04-reconciliacion-operaciones.md).
6. Adaptar el servicio de operaciones de imágenes al cliente MIG-01 preservando cargar-antes-de-borrar, clave de solicitud y reconciliación; detallar en [MIG-04-reconciliacion-operaciones.md](./MIG-04-reconciliacion-operaciones.md). Verificar carga URL/archivo, respuesta de imagen, límites y URLs finales.
7. Recorrer importaciones ZIP/archivos y selección SKU: anotar controles en [MIG-04-reconciliacion-operaciones.md](./MIG-04-reconciliacion-operaciones.md). Nueva API no elimina prevalidación ni autoriza elegir la primera coincidencia.
8. Actualizar pruebas HTTP según [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md) y añadir ensayos documentados en [MIG-04-payloads-escritura.md](./MIG-04-payloads-escritura.md). Validar que modificar precio no toca existencias y que editar una ubicación conserva las demás.

## Archivos iniciales

`TiendanubeProductoWriteService`, `TiendanubeProductoImagenOperacionService`, `TiendanubeImageImportService`, Requests de productos/imágenes, controlador y formularios de creación/edición. Actualizar pruebas de escritura/importaciones, no solo URLs en mocks.

## Aceptación

- [ ] Crear/editar precio, promoción y costo funciona en ensayo.
- [ ] Quitar promoción y categorías tiene semántica verificada.
- [ ] Precio no cambia stock ni atributos no solicitados.
- [ ] Ubicación A cambia sin alterar B.
- [ ] Fallo de carga conserva fotos anteriores; reintento no duplica imagen.
- [ ] No queda ninguna escritura activa con stock plano legado.

## Entrega y reversión

Entregar [MIG-04-payloads-escritura.md](./MIG-04-payloads-escritura.md) y [MIG-04-reconciliacion-operaciones.md](./MIG-04-reconciliacion-operaciones.md) a MIG-05 y contratos de precios para TN-07F. Ante regresión, bloquear la operación afectada y reconciliar pendientes. No confiar en revertir código para recuperar cambios remotos ni borrar operaciones parciales.

## Fuentes y verificación

- [Recursos oficiales 2025-03](https://tiendanube.github.io/api-documentation/resources).
- [Introducción y transporte](https://tiendanube.github.io/api-documentation/intro).
- [Ubicaciones](https://tiendanube.github.io/api-documentation/resources/location).
- [Variantes](https://tiendanube.github.io/api-documentation/resources/product-variant).
- [Inventario por ubicación](https://tiendanube.github.io/api-documentation/guides/multi-inventory/products).

Consultar la referencia específica de cada endpoint y la capacidad real de la tienda al implementar. No asumir que disponibilidad documental equivale a permiso/capacidad de la cuenta. No está acreditada aquí una fecha de apagado de v1.

## Instrucción para Cursor

> Implementa exclusivamente MIG-04, comprobando sus dependencias en el checkout actual y usando documentación oficial 2025-03. **Lee primero** los artefactos en **Entradas**; el stock en escritura sale solo de [MIG-03-payload-stock.md](./MIG-03-payload-stock.md). Crea o actualiza los entregables de esta fase. Antes de modificar código, concreta contratos y archivos afectados. Conserva las mejoras existentes; no añadas funcionalidades comerciales de TN-07 ni otros recursos API ajenos al catálogo. Reporta evidencia por criterio de aceptación, pruebas realmente ejecutadas, migraciones, compatibilidad de jobs y pendientes. No cierres como completo un punto crítico no validado. Una tarea de implementación no autoriza por sí sola despliegue o escrituras de prueba sobre productos reales.
