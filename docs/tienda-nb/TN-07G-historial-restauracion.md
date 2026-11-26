# TN-07G · Historial, conciliación y restauración revisable

Fecha: 11 de septiembre de 2026. Proyecto: `gelia-nv`, módulo TiendaNube.
Base conocida: commit `5eeb392f0d90b62cdb292c5326b98398a2d787a7`. No se revalidó el checkout hoy. Este documento especifica trabajo por implementar; no afirma que sus componentes propuestos existan ni que se hayan ejecutado pruebas.

## Regla de alcance

Ejecutar únicamente TN-07G. Leer instrucciones del repositorio y contrastar las rutas citadas con el código actual. Conservar las correcciones previas del módulo. Resolver dependencias usando sus contratos; si falta una, reportarla sin implementar silenciosamente otro plan. Un archivo por tarea y revisión propia; no reemplazar archivos compartidos con copias antiguas.

El sistema usa costo general capturado/importado y listas opcionales de nombres configurables. No impone fuente comercial. Unidad de operación: tienda/producto/variante. Las referencias monetarias se mantienen separadas de los valores remotos publicados. Sin automatización comercial por fecha o disparador en esta primera versión.

## Objetivo y dependencias

Ofrecer consulta de lo sucedido y preparar restauraciones controladas. Requiere D y sus eventos. Para historial de API requiere F; para historial CSV requiere E. No simular canales todavía ausentes. No trasladar a este plan la persistencia básica que D/E/F deben haber implementado desde el principio.

## Contrato de restauración

Restaurar no significa hacer rollback SQL ni importar a ciegas un archivo viejo. Crea una propuesta compensatoria nueva, por variante y campo, enlazada a la operación original. Esa propuesta pasa por revisión/aprobación D y se ejecuta con F o se exporta con E. Preservar tanto operación original como compensación.

## Pasos de implementación

1. **Consultar eventos.** Construir vista de lotes/revisiones/ejecuciones/artefactos con actor, fecha, fuente/regla, valores anterior/propuesto/confirmado y canal. Paginación y filtros por fecha, SKU/variante, estado y usuario. Mostrar conflictos y exclusiones.
2. **Semántica de evidencia.** Separar aprobado, archivo generado, declarado importado y verificado remotamente. Mostrar diferencia entre enviado, confirmado por lectura y coincidencia previa sin escritura propia. No atribuir al sistema un cambio de origen desconocido.
3. **Conciliar.** Leer remoto mediante servicio separado para filas elegidas o aceptar exportación nativa reciente vinculada a identidades. Comparar valores y registrar fecha/evidencia. Un informe manual sin evidencia queda declarado, no verificado. No cambiar precios durante conciliación.
4. **Elegir restauración.** Permitir variantes y campos concretos, mostrar valor anterior de la operación original, valor aplicado y valor remoto actual. Admitir solo registros con evidencia suficiente. Operaciones inciertas deben conciliarse primero.
5. **Detectar cambios posteriores.** Si actual coincide con resultado que se quiere deshacer, puede proponerse restauración. Si difiere, conflicto que exige revisión. Incluso igualdad no demuestra ausencia de cambios intermedios; consultar historial disponible y mostrar limitación. No sobrescribir automáticamente.
6. **Crear compensación.** Crear lote nuevo con origen “restauración”, valores objetivos e intención explícita. Cuando anteriormente no había promoción, generar eliminar; no cero. Validar con B relación normal/promoción y protecciones. Si normal anterior invalida promoción vigente, bloquear y requerir revisión de ambos campos.
7. **Revisar y aplicar/exportar.** Usar aprobación D, no bypass por ser restauración. F repite control remoto al ejecutar. CSV E no garantiza estado fresco al importar: indicar necesidad de revisión previa y conciliación posterior.
8. **Auditoría.** Registrar vínculo original-compensación, solicitante, aprobador, resultado y motivo. Restringir acceso a costos según permisos. Retención no elimina registros necesarios para reconstruir operaciones activas o disputadas.

## Componentes

Pantallas `Tiendanube/Precios/Historial.jsx` y detalle/restauración, consultas de auditoría, servicio de conciliación y generador de propuesta compensatoria. Reutilizar modelos de D/E/F; solo añadir columnas o eventos si no existen. No duplicar tablas de ejecución ni motor de precios.

## Pruebas y aceptación

- [ ] CSV descargado no aparece como aplicado.
- [ ] Conciliación detecta discrepancias sin escribir remotamente.
- [ ] Restauración parcial solo incluye campos elegidos.
- [ ] Cambio posterior provoca conflicto antes de restaurar.
- [ ] Promoción originalmente ausente se restaura como eliminación explícita.
- [ ] Confirmación exige permiso y revisión vigente.
- [ ] Compensación no borra ni modifica evidencia original.
- [ ] Restauración cumple mismo control de precios/protecciones que una aplicación normal.
- [ ] Usuario sin acceso a costos no los obtiene por historial o descargas.

## Despliegue, límites y reversión

Habilitar primero historial/conciliación de lectura, luego propuestas compensatorias. Ante regresión desactivar restauración manteniendo auditoría. No revertir automáticamente compensaciones ya aplicadas: constituyen otra operación con su propia evidencia. No implementar calendario de promociones, actualizaciones automáticas por costo ni una app offline en este plan.

## Instrucción específica para Cursor

> Trabaja exclusivamente en TN-07G. Lee este archivo, las instrucciones locales y los contratos de los requisitos previos. Confirma el estado real del checkout, reutiliza lo existente y concreta nombres/archivos nuevos según convenciones del proyecto. Implementa sus pasos en cambios acotados, con pruebas de aceptación y sin incluir otros planes. Consulta documentación oficial vigente si una decisión depende del framework o del proveedor. No presentes componentes propuestos como existentes. Si un requisito falta, delimita lo bloqueado. Reporta archivos, migraciones, pruebas realmente ejecutadas, criterios cumplidos, riesgos pendientes y reversión. No publiques ni despliegues por el solo hecho de implementar.

## Condición de cierre común

- [ ] Todas las pruebas específicas de este archivo están ejecutadas o su limitación está documentada; no declarar cierre completo si queda un criterio obligatorio bloqueado.
- [ ] Diff acotado al plan y contratos entregados al siguiente.
- [ ] Migraciones nuevas; no editar las ya aplicadas ni eliminar historial para resolver incompatibilidades.
- [ ] Pruebas de API simuladas; validaciones reales únicamente con entorno/productos de ensayo apropiados.
- [ ] Comandos de test/build elegidos según scripts del checkout; no inventar resultados.
