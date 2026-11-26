# MIG-03 · Modelo de inventario por ubicación

Fecha: 11 de septiembre de 2026. Proyecto: gelia-nv / módulo TiendaNube.
Destino: API REST 2025-03. Referencia de código previa: cliente y configuración leídos en `main` durante esta conversación; Cursor debe fijar el SHA actual antes de editar. No hay prueba contra la tienda ni implementación realizada en este documento.

## Alcance de esta tarea

Ejecutar solo MIG-03, en una rama/revisión acotada. Leer instrucciones del repositorio. No incorporar automáticamente pasos posteriores ni reconstruir todo el módulo. Mantener las mejoras TN-01 a TN-06. Toda migración de datos es aditiva y preserva identidad/historial.

## Objetivo y dependencia

Requiere MIG-01 y MIG-02. Incorporar ubicaciones remotas y niveles por variante, separando stock total de stock editable por ubicación. No enlazar automáticamente ubicaciones remotas con almacenes internos ni crear reservas/pedidos.

## Artefactos

### Entradas

| Archivo | Uso en MIG-03 |
|---------|----------------|
| [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md) | GET `/locations`; probe `read_locations`; permisos |
| [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md) | Sección Location; multi-inventory; stock plano vs niveles |
| [MIG-02-mapeo-lectura-catalogo.md](./MIG-02-mapeo-lectura-catalogo.md) | Campo `stock` legado en variante; columnas locales de producto/variante |

### Entregables de esta fase

| Archivo | Contenido |
|---------|-----------|
| [MIG-03-modelo-ubicaciones.md](./MIG-03-modelo-ubicaciones.md) | Esquema tablas, unicidad, `location_id` opaco, propiedades `stock_total` / niveles |
| [MIG-03-payload-stock.md](./MIG-03-payload-stock.md) | Contrato interno para MIG-04: tienda/producto/variante/ubicación, stock objetivo, `stock_management` |

MIG-04 **debe** usar [MIG-03-payload-stock.md](./MIG-03-payload-stock.md); no redefinir semántica de stock en código sin actualizar ese archivo.

## Contrato externo relevante

La guía oficial presenta `inventory_levels` con `location_id` y stock. Advierte que el antiguo stock puede ser total y que enviar solo `variant.stock` actualiza la primera ubicación. No basar escrituras nuevas en esa compatibilidad. Fuente: [guía de inventario](https://tiendanube.github.io/api-documentation/guides/multi-inventory/products).

## Pasos

1. Verificar disponibilidad y permisos de Location y multiinventario en tienda de ensayo; registrar resultado en [MIG-03-modelo-ubicaciones.md](./MIG-03-modelo-ubicaciones.md). Consultar pendientes Location en [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md). No asumir que cambiar URL activa capacidades de la cuenta. Si falta capacidad, registrar limitación y mantener escritura de stock bloqueada hasta definir comportamiento válido; no ocultarla como compatibilidad completa.
2. Crear tablas de ubicaciones por tienda y niveles por variante/ubicación; documentar DDL y unicidad en [MIG-03-modelo-ubicaciones.md](./MIG-03-modelo-ubicaciones.md). `location_id` debe ser texto opaco, no entero; conservar exactamente el ID remoto. Unicidad por tienda/variante/ubicación. Usar migraciones nuevas, no cambiar IDs de productos.
3. Definir propiedades locales en [MIG-03-modelo-ubicaciones.md](./MIG-03-modelo-ubicaciones.md): `stock_total` derivado de niveles completos, `stock_management` y niveles. Cruzar lecturas `.stock` legadas con [MIG-02-mapeo-lectura-catalogo.md](./MIG-02-mapeo-lectura-catalogo.md). Ausencia de niveles no es stock cero. No distribuir el stock histórico entre ubicaciones inventadas: poblar con lecturas nuevas y conservar dato legado solo como referencia de transición.
4. Sincronizar ubicaciones y niveles desde respuestas completas; anotar reglas de retiro/inactivación en [MIG-03-modelo-ubicaciones.md](./MIG-03-modelo-ubicaciones.md). Tratar ubicaciones retiradas/inactivas sin perder historial. Revisar el contrato real antes de decidir cuándo eliminar una relación de nivel. Evitar sumas entre snapshots de distintas fechas.
5. En UI, mostrar total como resumen y niveles con nombre/ID de ubicación. Selección explícita para editar. Si solo hay una ubicación verificada, permitir preseleccionarla mostrando cuál es. Mantener ilimitado como estado explícito conforme al contrato, nunca como campo vacío accidental.
6. Documentar en [MIG-03-payload-stock.md](./MIG-03-payload-stock.md) el payload interno que MIG-04 recibirá: IDs tienda/producto/variante/ubicación, stock objetivo y modo de control. Rechazar ubicación ajena y requerir lectura reciente cuando cambien capacidades.
7. Ajustar reportes y consumidores de stock legado; registrar cada lectura/escritura de `.stock` en [MIG-03-modelo-ubicaciones.md](./MIG-03-modelo-ubicaciones.md). Distinguir campo resumen de campo remoto enviado. Mantener compatibilidad local solo con semántica documentada.

## Archivos iniciales

Modelos `TiendanubeProductoVariante`, nuevas ubicaciones/niveles, `TiendanubeCatalogoSyncService`, cliente para Location y formularios/detalle de producto. Revisar migraciones existentes y no modificar las ya aplicadas.

## Aceptación

- [ ] Variante con ubicación A=3 y B=7 muestra total 10 y niveles separados.
- [ ] ID alfanumérico se conserva exactamente.
- [ ] Niveles ausentes no se convierten en cero ni se reparten arbitrariamente.
- [ ] Ubicación ajena se rechaza antes de llamada remota.
- [ ] Cambiar control de stock requiere intención explícita.
- [ ] Ninguna ubicación remota crea o altera almacén interno automáticamente.

## Reversión

No volver a escribir total agregado en primera ubicación. Si hay regresión, mantener lectura y deshabilitar edición de stock; conservar tablas nuevas. Entregar [MIG-03-modelo-ubicaciones.md](./MIG-03-modelo-ubicaciones.md) y [MIG-03-payload-stock.md](./MIG-03-payload-stock.md) a MIG-04. MIG-04 habilitará escrituras luego de pruebas. No incluir abastecimiento, reservas ni sincronización entre canales.

## Fuentes y verificación

- [Recursos oficiales 2025-03](https://tiendanube.github.io/api-documentation/resources).
- [Introducción y transporte](https://tiendanube.github.io/api-documentation/intro).
- [Ubicaciones](https://tiendanube.github.io/api-documentation/resources/location).
- [Variantes](https://tiendanube.github.io/api-documentation/resources/product-variant).
- [Inventario por ubicación](https://tiendanube.github.io/api-documentation/guides/multi-inventory/products).

Consultar la referencia específica de cada endpoint y la capacidad real de la tienda al implementar. No asumir que disponibilidad documental equivale a permiso/capacidad de la cuenta. No está acreditada aquí una fecha de apagado de v1.

## Instrucción para Cursor

> Implementa exclusivamente MIG-03, comprobando sus dependencias en el checkout actual y usando documentación oficial 2025-03. **Lee primero** los artefactos listados en **Entradas**. Crea o actualiza [MIG-03-modelo-ubicaciones.md](./MIG-03-modelo-ubicaciones.md) y [MIG-03-payload-stock.md](./MIG-03-payload-stock.md). Antes de modificar código, concreta contratos y archivos afectados. Conserva las mejoras existentes; no añadas funcionalidades comerciales de TN-07 ni otros recursos API ajenos al catálogo. Reporta evidencia por criterio de aceptación, pruebas realmente ejecutadas, migraciones, compatibilidad de jobs y pendientes. No cierres como completo un punto crítico no validado. Una tarea de implementación no autoriza por sí sola despliegue o escrituras de prueba sobre productos reales.
