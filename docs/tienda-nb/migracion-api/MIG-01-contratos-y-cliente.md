# MIG-01 · Contratos y cliente HTTP

Fecha: 11 de septiembre de 2026. Proyecto: gelia-nv / módulo TiendaNube.
Destino: API REST 2025-03. Referencia de código previa: cliente y configuración leídos en `main` durante esta conversación; Cursor debe fijar el SHA actual antes de editar. No hay prueba contra la tienda ni implementación realizada en este documento.

## Alcance de esta tarea

Ejecutar solo MIG-01, en una rama/revisión acotada. Leer instrucciones del repositorio. No incorporar automáticamente pasos posteriores ni reconstruir todo el módulo. Mantener las mejoras TN-01 a TN-06. Toda migración de datos es aditiva y preserva identidad/historial.

## Objetivo y dependencia

Primera entrega. Preparar cliente para 2025-03 y documentar contrato por endpoint, sin activar aún escrituras nuevas en producción. El código revisado utiliza `config('tiendanube.api_base')` y tiene fallback a `/v1` en `TiendanubeApiClient`. Verificar configuración real del entorno: el valor por defecto no prueba qué URL usa producción.

## Artefactos

### Entregables de esta fase

| Archivo | Contenido |
|---------|-----------|
| [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md) | Inventario HTTP: consumidor, verbo, ruta, permisos, paginación, idempotencia |
| [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md) | Matriz v1 vs 2025-03 por recurso; deltas comprobados y pendientes de ensayo |

Las fases MIG-02 en adelante **consultan** estos archivos como entrada. No duplicar el inventario HTTP ni la matriz de transporte en otros documentos; actualizar aquí si cambia el cliente.

## Pasos

1. Inventariar todas las llamadas HTTP del módulo con búsqueda por dominio, `/v1`, `api_base` y uso del cliente. Incluir comandos, jobs, pruebas y scripts; no inspeccionar ni imprimir valores secretos. Registrar consumidor, verbo, ruta, entrada, respuesta, permisos, paginación y efecto en [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md).
2. Crear matriz de compatibilidad contra documentación 2025-03 para Store, Category, Product, Product Variant, Product Image, Webhook y Location en [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md). Distinguir diferencias comprobadas de puntos pendientes de ensayo. Campos adicionales ajenos al catálogo no son funciones obligatorias.
3. Centralizar host y versión. Usar configuración explícita de versión con destino 2025-03 y validación que impida rutas duplicadas/versiones desconocidas. Migrar compatiblemente `TIENDANUBE_API_BASE` si ya está configurada; documentar precedencia y retirar fallbacks silenciosos a v1 al completar el corte.
4. Revisar autenticación y User-Agent contra guía oficial. Probar el encabezado admitido y un identificador de aplicación con contacto válido. No rotar credenciales ni cambiar scopes por suposición; si faltan permisos de ubicaciones, documentar autorización requerida.
5. Implementar errores tipados conservando status, recurso y motivo resumido. Un 404 de ruta/configuración equivocada no debe probar ausencia de un producto para depuración. Probar conectividad y capacidades antes de habilitar barridos.
6. Corregir reintentos según idempotencia. GET recuperable admite espera acotada; POST de imágenes/productos con timeout requiere reconciliación antes de repetir. Revisar el callback actual que comprueba `method_exists($exception, 'response')` aunque luego accede a propiedad: contrastar con excepción de la versión Laravel instalada. Respetar límites/encabezados documentados sin reintentar indefinidamente.
7. Paginar según contrato/Link cuando corresponda, validando que enlaces pertenezcan a host, tienda y versión permitidos antes de reenviar autenticación. Probar fin de páginas, página vacía y respuesta inválida.
8. Añadir prueba de conexión de solo lectura en entorno de ensayo. Registrar versión configurada y resultado observable sin secretos. No fijar respuestas reales privadas en fixtures.

## Archivos iniciales

`config/tiendanube.php`, `app/Services/Tiendanube/TiendanubeApiClient.php`, controlador de configuración, excepciones y pruebas del módulo. Crear pruebas de contrato HTTP específicas sin reescribir servicios de negocio.

## Aceptación

- [ ] Cada endpoint usado aparece en [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md) con contrato o pendiente explícito.
- [ ] [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md) cubre todos los consumidores HTTP del módulo.
- [ ] Todas las llamadas de ensayo usan host/versión/tienda correctos.
- [ ] Autenticación, paginación y errores probados; ningún log expone token.
- [ ] POST incierto no se repite automáticamente.
- [ ] No hay escrituras de producción por configurar pruebas.

## Entrega y reversión

Entregar cliente configurable y los artefactos [MIG-01-inventario-endpoints.md](./MIG-01-inventario-endpoints.md) + [MIG-01-matriz-compatibilidad.md](./MIG-01-matriz-compatibilidad.md) a MIG-02/MIG-03. Mantener producción en configuración anterior hasta MIG-06, sin asumir que esa versión seguirá disponible. Revertir código de transporte solo si es compatible con estado remoto; no usar rollback de versión para ocultar fallos de datos.

## Fuentes y verificación

- [Recursos oficiales 2025-03](https://tiendanube.github.io/api-documentation/resources).
- [Introducción y transporte](https://tiendanube.github.io/api-documentation/intro).
- [Ubicaciones](https://tiendanube.github.io/api-documentation/resources/location).
- [Variantes](https://tiendanube.github.io/api-documentation/resources/product-variant).
- [Inventario por ubicación](https://tiendanube.github.io/api-documentation/guides/multi-inventory/products).

Consultar la referencia específica de cada endpoint y la capacidad real de la tienda al implementar. No asumir que disponibilidad documental equivale a permiso/capacidad de la cuenta. No está acreditada aquí una fecha de apagado de v1.

## Instrucción para Cursor

> Implementa exclusivamente MIG-01, comprobando sus dependencias en el checkout actual y usando documentación oficial 2025-03. Antes de modificar, concreta contratos y archivos afectados. Conserva las mejoras existentes; no añadas funcionalidades comerciales de TN-07 ni otros recursos API ajenos al catálogo. Crea o actualiza los entregables listados en **Artefactos**; las fases siguientes los reutilizan como fuente única de inventario HTTP y matriz de compatibilidad. Reporta evidencia por criterio de aceptación, pruebas realmente ejecutadas, migraciones, compatibilidad de jobs y pendientes. No cierres como completo un punto crítico no validado. Una tarea de implementación no autoriza por sí sola despliegue o escrituras de prueba sobre productos reales.
