# MIG-06 · Validación integral, corte y habilitación de TN-07

Fecha: 11 de septiembre de 2026. Proyecto: gelia-nv / módulo TiendaNube.
Destino: API REST 2025-03. Referencia de código previa: cliente y configuración leídos en `main` durante esta conversación; Cursor debe fijar el SHA actual antes de editar. No hay prueba contra la tienda ni implementación realizada en este documento.

## Alcance de esta tarea

Ejecutar solo MIG-06, en una rama/revisión acotada. Leer instrucciones del repositorio. No incorporar automáticamente pasos posteriores ni reconstruir todo el módulo. Mantener las mejoras TN-01 a TN-06. Toda migración de datos es aditiva y preserva identidad/historial.

## Objetivo y dependencia

Requiere todas las entregas MIG-01 a MIG-05. Certificar el catálogo actual sobre 2025-03, efectuar el corte cuando se solicite el despliegue y fijar una base estable para TN-07. Este documento no certifica pruebas ni despliegue ya realizados.

## Artefactos

### Entradas (cadena completa)

| Fase | Archivos |
|------|----------|
| MIG-01 | [inventario-endpoints](./MIG-01-inventario-endpoints.md), [matriz-compatibilidad](./MIG-01-matriz-compatibilidad.md) |
| MIG-02 | [mapeo-lectura-catalogo](./MIG-02-mapeo-lectura-catalogo.md), [fixtures-catalogo](./MIG-02-fixtures-catalogo.md), [comparacion-sync](./MIG-02-comparacion-sync.md) |
| MIG-03 | [modelo-ubicaciones](./MIG-03-modelo-ubicaciones.md), [payload-stock](./MIG-03-payload-stock.md) |
| MIG-04 | [payloads-escritura](./MIG-04-payloads-escritura.md), [reconciliacion-operaciones](./MIG-04-reconciliacion-operaciones.md) |
| MIG-05 | [inventario-jobs-webhooks](./MIG-05-inventario-jobs-webhooks.md), [procedimiento-pausa](./MIG-05-procedimiento-pausa.md) |

### Entregables de esta fase

| Archivo | Contenido |
|---------|-----------|
| [MIG-06-evidencia-integracion.md](./MIG-06-evidencia-integracion.md) | Matriz consolidada con evidencia por operación; pendientes críticos |
| [MIG-06-registro-cierre.md](./MIG-06-registro-cierre.md) | SHA, versión efectiva, migraciones, pruebas, restricciones, base TN-07 |

## Pasos

1. Consolidar [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md) con evidencia por operación en [MIG-06-evidencia-integracion.md](./MIG-06-evidencia-integracion.md): lectura tienda/categorías/productos/variantes/imágenes/ubicaciones; creación/edición; precios/promociones/costo; inventario localizado; cargas individuales/ZIP; webhooks; reportes; recuperadores y limpieza. Enlazar filas a entregables MIG-02…05 donde aplique.
2. Ejecutar suites unitarias/feature del módulo con API simulada y pruebas de concurrencia sobre backend representativo; registrar resultados en [MIG-06-evidencia-integracion.md](./MIG-06-evidencia-integracion.md). Ejecutar build frontend según scripts del checkout. No sustituir prueba de contrato por búsqueda/reemplazo de URLs.
3. Ensayar con tienda/productos de prueba usando fixtures de [MIG-02-fixtures-catalogo.md](./MIG-02-fixtures-catalogo.md); volcar comparación en [MIG-02-comparacion-sync.md](./MIG-02-comparacion-sync.md) y resumen en [MIG-06-evidencia-integracion.md](./MIG-06-evidencia-integracion.md). Si solo hay una ubicación real, registrar limitación en [MIG-03-modelo-ubicaciones.md](./MIG-03-modelo-ubicaciones.md).
4. Comparar snapshot anterior/nuevo en [MIG-06-evidencia-integracion.md](./MIG-06-evidencia-integracion.md): IDs, conteos, vínculos, precios, nulos, imágenes, categorías y niveles. Verificar que no se activó depuración por cambio de formato. Ensayar timeout, 429, credenciales inválidas y trabajos diferidos.
5. Preparar corte siguiendo [MIG-05-procedimiento-pausa.md](./MIG-05-procedimiento-pausa.md): respaldo verificable, migraciones aditivas, drenaje/pausa. Deshabilitar temporalmente escritura/depuración mientras se cambia contrato activo. No vaciar catálogo como mecanismo de migración.
6. Desplegar versión compatible, aplicar `TIENDANUBE_API_VERSION=2025-03`, reconstruir caché de configuración y reiniciar workers. Lectura de control (probe MIG-01) y reconciliación completa; registrar en [MIG-06-registro-cierre.md](./MIG-06-registro-cierre.md). Activar primero lecturas, luego escrituras verificadas ([MIG-04-payloads-escritura.md](./MIG-04-payloads-escritura.md)), después depuración protegida.
7. Monitorear fallos; criterio de detener escrituras en [MIG-06-registro-cierre.md](./MIG-06-registro-cierre.md). Rollback técnico: preferir modo lectura si el código viejo escribiría stock plano ([MIG-03-payload-stock.md](./MIG-03-payload-stock.md)).
8. Cerrar migración en [MIG-06-registro-cierre.md](./MIG-06-registro-cierre.md): SHA, versión efectiva, migraciones, pruebas, restricciones y pendientes. No confundir “código fusionado” con “tienda validada”.

## Puerta de entrada a TN-07

Por decisión del proyecto, TN-07A a TN-07G empiezan después del cierre de esta migración. Antes, actualizar su base técnica a la revisión resultante documentada en [MIG-06-registro-cierre.md](./MIG-06-registro-cierre.md); no usar fixtures v1 ni cliente viejo. Los conceptos de costo general y listas configurables se conservan. Publicar costo debe cumplir restricciones remotas verificadas ([MIG-04-payloads-escritura.md](./MIG-04-payloads-escritura.md)) sin impedir guardar una referencia local válida.

Entregar a TN-07 contratos de identidad por tienda/producto/variante ([MIG-02-mapeo-lectura-catalogo.md](./MIG-02-mapeo-lectura-catalogo.md)), importes como decimales, intenciones conservar/establecer/eliminar, coordinación e idempotencia ([MIG-04-reconciliacion-operaciones.md](./MIG-04-reconciliacion-operaciones.md), [MIG-05-procedimiento-pausa.md](./MIG-05-procedimiento-pausa.md)). No obligar a TN-07 a escribir inventario al editar precios. El CSV sigue teniendo perfil nativo independiente de la versión REST.

## Aceptación final

- [ ] Toda operación vigente del catálogo tiene evidencia de compatibilidad o restricción explícita aceptada; no hay pendientes críticos ocultos.
- [ ] Versión efectiva verificada en web y workers.
- [ ] No hay fallback silencioso a v1 ni escrituras activas de stock plano.
- [ ] Datos/relaciones anteriores conservados y reconciliados.
- [ ] Colas, webhooks, importaciones y reportes funcionan después del corte.
- [ ] Rollback ensayado o procedimiento con límites documentados.
- [ ] Se registra base definitiva que habilita TN-07.

## Límites

No agregar pedidos, checkout, envíos, clientes, cupones, reglas comerciales nuevas ni todos los recursos disponibles en la API. Migración completa significa cobertura completa de las funciones actuales del catálogo y adaptación de inventario necesaria; las expansiones comerciales se planifican aparte.

## Fuentes y verificación

- [Recursos oficiales 2025-03](https://tiendanube.github.io/api-documentation/resources).
- [Introducción y transporte](https://tiendanube.github.io/api-documentation/intro).
- [Ubicaciones](https://tiendanube.github.io/api-documentation/resources/location).
- [Variantes](https://tiendanube.github.io/api-documentation/resources/product-variant).
- [Inventario por ubicación](https://tiendanube.github.io/api-documentation/guides/multi-inventory/products).

Consultar la referencia específica de cada endpoint y la capacidad real de la tienda al implementar. No asumir que disponibilidad documental equivale a permiso/capacidad de la cuenta. No está acreditada aquí una fecha de apagado de v1.

## Instrucción para Cursor

> Implementa exclusivamente MIG-06, comprobando sus dependencias en el checkout actual y usando documentación oficial 2025-03. **Consolida** todos los artefactos listados en **Entradas** en [MIG-06-evidencia-integracion.md](./MIG-06-evidencia-integracion.md) y [MIG-06-registro-cierre.md](./MIG-06-registro-cierre.md). Ejecuta el corte solo cuando se solicite despliegue, siguiendo [MIG-05-procedimiento-pausa.md](./MIG-05-procedimiento-pausa.md). No cierres pendientes críticos de MIG-01…05 como resueltos sin evidencia. Una tarea de implementación no autoriza por sí sola despliegue o escrituras de prueba sobre productos reales.
