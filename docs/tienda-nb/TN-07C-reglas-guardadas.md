# TN-07C · Configuración y versiones de reglas

Fecha: 11 de septiembre de 2026. Proyecto: `gelia-nv`, módulo TiendaNube.
Base conocida: commit `5eeb392f0d90b62cdb292c5326b98398a2d787a7`. No se revalidó el checkout hoy. Este documento especifica trabajo por implementar; no afirma que sus componentes propuestos existan ni que se hayan ejecutado pruebas.

## Regla de alcance

Ejecutar únicamente TN-07C. Leer instrucciones del repositorio y contrastar las rutas citadas con el código actual. Conservar las correcciones previas del módulo. Resolver dependencias usando sus contratos; si falta una, reportarla sin implementar silenciosamente otro plan. Un archivo por tarea y revisión propia; no reemplazar archivos compartidos con copias antiguas.

El sistema usa costo general capturado/importado y listas opcionales de nombres configurables. No impone fuente comercial. Unidad de operación: tienda/producto/variante. Las referencias monetarias se mantienen separadas de los valores remotos publicados. Sin automatización comercial por fecha o disparador en esta primera versión.

## Objetivo y dependencias

Crear el editor y almacenamiento de reglas reutilizables. Requiere contrato de fuentes A y esquema de cálculo B. Guardar, habilitar o editar una regla no ejecuta cambios de precios. No añadir cron, fechas ni disparadores por costo.

## Contrato que entrega

Regla con ID estable, nombre, descripción opcional, versión inmutable, condiciones, fuente por ID, operación, parámetro, destino, redondeo, protecciones y actor. Habilitada significa disponible para elegir, no ejecución automática. Archivar impide nuevas selecciones, sin borrar referencias históricas.

## Pasos de implementación

1. **Validar dependencia.** Verificar enums y validadores de B; usarlos en frontend/backend. No crear una segunda lista de fórmulas incompatible.
2. **Migraciones.** Crear cabecera de regla y tabla de versiones. Edición genera versión nueva; la cabecera señala versión vigente. Unicidad de número de versión por regla y control de concurrencia optimista al guardar.
3. **Endpoints autorizados.** Listar, consultar, crear, editar, duplicar y archivar. Separar permisos de lectura/administración. Validar pertenencia de fuentes/listas a la tienda y no admitir IDs de otra tienda.
4. **Editor de filas.** Campos visibles: nombre; condiciones con “todas”; lista/base; operación; cantidad; destino; redondeo. Agregar/quitar condiciones. Etiquetas “Aumentar sobre base” y “Margen sobre venta” distintas; explicar base con un ejemplo.
5. **Ejemplos de configuración.** Permitir regla normal<100 → normal×0.94 a promocional, y normal>1500 → normal×1.12 a normal. Mostrar inclusividad al configurar rangos. No preconfigurar nombres comerciales de listas.
6. **Vista previa individual.** Ejecutar motor B con valores de prueba introducidos por el usuario; indicar que es ejemplo, no lote ni resultado guardado sobre productos.
7. **Validación de referencias.** Fuente archivada no desaparece del historial. Marcar regla no utilizable en nuevas simulaciones si la fuente ya no está disponible. Cambiar nombre de lista no rompe referencias por ID.
8. **Control de concurrencia.** Dos editores sobre una versión: el segundo recibe conflicto y conserva su formulario; no sobrescribir cambios ajenos. Duplicar crea regla nueva sin alterar la original.

## Archivos de partida y componentes

Crear controlador/Requests `Tiendanube/Precios` y pantallas de reglas; añadir rutas con middleware de permisos al archivo de integración existente. Usar estilos del módulo. Reutilizar motor B y resolver A. Crear migraciones nuevas y pruebas de contrato y endpoints.

## Pruebas y aceptación

- [ ] Guardar regla no escribe precios en TiendaNube ni en el espejo.
- [ ] Editar crea versión nueva, sin modificar la versión referenciada por un lote.
- [ ] Fórmula y destino inválidos son rechazados también por backend.
- [ ] Regla duplicada es independiente; archivar conserva historial.
- [ ] Dos guardados concurrentes no pierden datos.
- [ ] Fuentes ajenas o inexistentes no se aceptan.
- [ ] Regla habilitada no genera jobs automáticamente.

## Despliegue, salida y límites

Aplicar esquema aditivo y habilitar editor tras B. Entregar a D consulta de versiones y validación. No implementar selector masivo, aprobación, CSV, jobs de aplicación o programación de promociones. Reversión deshabilita edición nueva conservando versiones; no elimina reglas usadas por snapshots.

## Instrucción específica para Cursor

> Trabaja exclusivamente en TN-07C. Lee este archivo, las instrucciones locales y los contratos de los requisitos previos. Confirma el estado real del checkout, reutiliza lo existente y concreta nombres/archivos nuevos según convenciones del proyecto. Implementa sus pasos en cambios acotados, con pruebas de aceptación y sin incluir otros planes. Consulta documentación oficial vigente si una decisión depende del framework o del proveedor. No presentes componentes propuestos como existentes. Si un requisito falta, delimita lo bloqueado. Reporta archivos, migraciones, pruebas realmente ejecutadas, criterios cumplidos, riesgos pendientes y reversión. No publiques ni despliegues por el solo hecho de implementar.

## Condición de cierre común

- [ ] Todas las pruebas específicas de este archivo están ejecutadas o su limitación está documentada; no declarar cierre completo si queda un criterio obligatorio bloqueado.
- [ ] Diff acotado al plan y contratos entregados al siguiente.
- [ ] Migraciones nuevas; no editar las ya aplicadas ni eliminar historial para resolver incompatibilidades.
- [ ] Pruebas de API simuladas; validaciones reales únicamente con entorno/productos de ensayo apropiados.
- [ ] Comandos de test/build elegidos según scripts del checkout; no inventar resultados.
