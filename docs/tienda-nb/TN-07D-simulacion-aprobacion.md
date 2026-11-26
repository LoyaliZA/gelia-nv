# TN-07D · Simulación, revisión y aprobación de precios

Fecha: 11 de septiembre de 2026. Proyecto: `gelia-nv`, módulo TiendaNube.
Base conocida: commit `5eeb392f0d90b62cdb292c5326b98398a2d787a7`. No se revalidó el checkout hoy. Este documento especifica trabajo por implementar; no afirma que sus componentes propuestos existan ni que se hayan ejecutado pruebas.

## Regla de alcance

Ejecutar únicamente TN-07D. Leer instrucciones del repositorio y contrastar las rutas citadas con el código actual. Conservar las correcciones previas del módulo. Resolver dependencias usando sus contratos; si falta una, reportarla sin implementar silenciosamente otro plan. Un archivo por tarea y revisión propia; no reemplazar archivos compartidos con copias antiguas.

El sistema usa costo general capturado/importado y listas opcionales de nombres configurables. No impone fuente comercial. Unidad de operación: tienda/producto/variante. Las referencias monetarias se mantienen separadas de los valores remotos publicados. Sin automatización comercial por fecha o disparador en esta primera versión.

## Objetivo y dependencias

Convertir selección de variantes y reglas A/B/C en una propuesta persistida y aprobable. No publicar por API ni exportar CSV en este plan. Entrega el contrato central que E y F consumen sin recalcular.

## Contrato de lote

Lote = UUID, tienda/generación, actor, selección congelada, versión del motor, fuentes/versiones, reglas/versiones, revisión y estado. Ítem = producto/variante, valores anteriores, fuentes, intenciones por campo, resultados y validaciones. Revisión aprobada inmutable con checksum y actor/fecha.

Persistir desde ahora eventos de simulación, edición, exclusión y aprobación para G; no esperar al plan de historial. Estados: borrador, simulado, aprobado, cancelado. E/F añadirán sus ejecuciones sin convertir descarga en aplicación.

## Pasos de implementación

1. **Esquema.** Crear lotes, revisiones, ítems y eventos. Identidad histórica sobrevive a eliminación del espejo. Índices por tienda/lote/revisión/variante. Unicidad que evite variantes duplicadas dentro de la revisión.
2. **Seleccionar universo.** UI distingue página actual, selección manual y todos los resultados filtrados. Resolver en servidor y congelar IDs al simular. Guardar filtros solo como explicación; no reevaluarlos al aplicar.
3. **Capturar snapshot.** Resolver fuentes A y valores actuales locales con fecha de sincronización. Datos son referencia, no garantía de actualidad remota. Registrar costo local y costo remoto por separado. Moneda incompatible bloquea.
4. **Simular.** Usar B con versiones exactas de C. Mostrar faltantes/conflictos por fila. Para muchos productos procesar en job con progreso; mantener snapshot de entradas estable durante toda la ejecución.
5. **Tabla de revisión.** Mostrar SKU/atributos, fuentes, normal/promoción/costo actuales y propuestos, diferencias, margen y reglas. Filtrar errores, excluir filas y paginar sin perder selección. No implementar una hoja de cálculo genérica.
6. **Cambios manuales.** Permitir editar resultado por campo con motivo, conservando resultado original calculado. Crear revisión nueva, validar con B y anular aprobación anterior. Excluir no borra evidencia.
7. **Aprobar.** Backend comprueba permiso, revisión/checksum esperado, al menos una fila válida y ausencia de errores en seleccionadas. Aprobación cubre IDs e importes exactos. Reintento de aprobación no duplica eventos.
8. **Contrato de consumo.** Servicio `obtenerRevisionAprobada` devuelve valores absolutos, intenciones, identidad y anteriores, con autorización. E/F nunca leen resultados mutables del formulario.

## Componentes

Crear pantalla `resources/js/Pages/Tiendanube/Precios/Index.jsx`, tabla de resultados, selector de fuentes/reglas y controlador de lotes separado. Añadir rutas de simulación/revisión/aprobación. Reutilizar autorización del módulo y crear permiso de aprobación masiva. Definir el evento común de auditoría que E/F/G ampliarán de forma compatible.

## Pruebas y aceptación

- [ ] Recalcular igual revisión no acumula aumentos.
- [ ] Todos los filtrados incluyen más de una página; selección final queda congelada.
- [ ] Cambio de regla, lista, selección o resultado exige nueva revisión.
- [ ] Aprobar una revisión desactualizada devuelve conflicto.
- [ ] Exclusión evita envío posterior y conserva evidencia.
- [ ] Fuente faltante y reglas superpuestas no producen precio publicable.
- [ ] Snapshot aprobado puede leerse sin conexión a API.
- [ ] Ninguna acción escribe remotamente.

## Despliegue y reversión

Aplicar esquema y habilitar solo simulación inicialmente; luego aprobación. Reversión preserva revisiones y deshabilita nuevas aprobaciones. Entregar a E/F fixtures de snapshot aprobado y contrato estable. No añadir programación, aplicación automática ni restauración remota.

## Instrucción específica para Cursor

> Trabaja exclusivamente en TN-07D. Lee este archivo, las instrucciones locales y los contratos de los requisitos previos. Confirma el estado real del checkout, reutiliza lo existente y concreta nombres/archivos nuevos según convenciones del proyecto. Implementa sus pasos en cambios acotados, con pruebas de aceptación y sin incluir otros planes. Consulta documentación oficial vigente si una decisión depende del framework o del proveedor. No presentes componentes propuestos como existentes. Si un requisito falta, delimita lo bloqueado. Reporta archivos, migraciones, pruebas realmente ejecutadas, criterios cumplidos, riesgos pendientes y reversión. No publiques ni despliegues por el solo hecho de implementar.

## Condición de cierre común

- [ ] Todas las pruebas específicas de este archivo están ejecutadas o su limitación está documentada; no declarar cierre completo si queda un criterio obligatorio bloqueado.
- [ ] Diff acotado al plan y contratos entregados al siguiente.
- [ ] Migraciones nuevas; no editar las ya aplicadas ni eliminar historial para resolver incompatibilidades.
- [ ] Pruebas de API simuladas; validaciones reales únicamente con entorno/productos de ensayo apropiados.
- [ ] Comandos de test/build elegidos según scripts del checkout; no inventar resultados.
