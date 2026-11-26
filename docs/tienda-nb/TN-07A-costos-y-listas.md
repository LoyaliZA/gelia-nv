# TN-07A · Captura de costos y listas de referencia

Fecha: 11 de septiembre de 2026. Proyecto: `gelia-nv`, módulo TiendaNube.
Base conocida: commit `5eeb392f0d90b62cdb292c5326b98398a2d787a7`. No se revalidó el checkout hoy. Este documento especifica trabajo por implementar; no afirma que sus componentes propuestos existan ni que se hayan ejecutado pruebas.

## Regla de alcance

Ejecutar únicamente TN-07A. Leer instrucciones del repositorio y contrastar las rutas citadas con el código actual. Conservar las correcciones previas del módulo. Resolver dependencias usando sus contratos; si falta una, reportarla sin implementar silenciosamente otro plan. Un archivo por tarea y revisión propia; no reemplazar archivos compartidos con copias antiguas.

El sistema usa costo general capturado/importado y listas opcionales de nombres configurables. No impone fuente comercial. Unidad de operación: tienda/producto/variante. Las referencias monetarias se mantienen separadas de los valores remotos publicados. Sin automatización comercial por fecha o disparador en esta primera versión.

## Objetivo y dependencia

Permitir capturar, importar y consultar un costo general por variante, además de listas de referencia opcionales con nombres definidos por el usuario. No depende de otros planes nuevos. Un costo puede introducirse manualmente o mediante archivo; no hay proveedor, marca comercial ni integración externa obligatoria.

## Contrato que entrega a los siguientes planes

Fuente = `{tipo, lista_id?, version, moneda, fecha, valor_decimal, producto_id, variante_id, tienda_id}`. Tipos: costo local, lista de referencia, costo remoto actual, precio normal actual y precio promocional actual. Valores como cadenas decimales; ausencia distinta de cero. Costo local es el predeterminado si está disponible, sin sustituirlo silenciosamente por otra fuente.

El costo capturado se guarda localmente. Publicarlo en el campo costo de TiendaNube es una operación explícita posterior, con aprobación. Un webhook o sync no debe sobrescribir el costo local: mantener separado el espejo remoto.

## Pasos de implementación

1. **Inspeccionar modelos y enlaces.** Revisar variantes, permisos y cualquier infraestructura general de listas ya existente. Reutilizar solo si conserva identidad por variante, moneda y versiones. No copiar dependencias de otros canales de venta sin necesidad.
2. **Crear esquema aditivo.** Proponer tablas de fuentes/listas, versiones y valores por variante. Unicidad por tienda/fuente/versión/variante. Guardar actor, fecha y origen manual/archivo. Referenciar el catálogo sin eliminar historial en cascada al limpiar el espejo.
3. **Captura manual.** Crear formulario de costo con cantidad, moneda y motivo opcional; validar negativo, escala, rango soportado por la base y variante. Confirmar crea nueva versión. Cero explícito es válido como dato y debe distinguirse de costo no informado.
4. **Listas configurables.** Crear, renombrar y archivar listas sin cambiar sus IDs. Nombres libres con validación; no listas comerciales predefinidas obligatorias. Archivar no invalida snapshots históricos.
5. **Importación CSV local.** Permitir mapear SKU o ID de variante y columnas de importes a costo/listas. Configurar delimitador y decimal en vista previa. No aceptar formatos numéricos ambiguos silenciosamente. Conservar ceros de SKU, detectar duplicados entre filas y entre productos, y exigir destino explícito cuando haya ambigüedad.
6. **Confirmar lote de fuentes.** Mostrar coincidencias, errores y valores anterior/nuevo antes de guardar. Ninguna llamada remota. Confirmar únicamente filas válidas seleccionadas y reportar las excluidas. Limitar bytes/filas de entrada según infraestructura y usar lectura incremental.
7. **Exponer resolver de fuentes.** Recibir conjunto de variantes y devolver datos versionados, faltantes y moneda. No buscar por nombre ni inventar un costo mínimo. La elección automática entre proveedores queda fuera.
8. **Proteger acceso.** Permiso para consultar costo, editar costo/listas e importar. Aplicar autorización al servidor y ocultar valores en respuestas cuando no se tenga acceso.

## Archivos de partida y nuevos componentes

Consultar `app/Models/Tiendanube/TiendanubeProductoVariante.php`, `app/Models/Tiendanube/TiendanubeProducto.php` y rutas de integración. Crear componentes bajo `app/Services/Tiendanube/Precios/` para resolver/importar fuentes, Requests/controlador propio, migraciones nuevas y pantalla `resources/js/Pages/Tiendanube/Precios/Fuentes.jsx`. Nombres definitivos según convenciones del checkout.

## Pruebas y aceptación

- [ ] Captura de 330.00 persiste sin modificar el costo remoto.
- [ ] Cero y ausencia siguen siendo diferentes; negativos rechazados.
- [ ] Lista renombrada conserva ID y versiones anteriores.
- [ ] SKU 00123 conserva ceros; coincidencia ambigua no elige el primer registro.
- [ ] Archivo con filas duplicadas o número ambiguo informa error antes de confirmar.
- [ ] Usuario sin permiso no recibe costo por endpoint ni exportación.
- [ ] Monedas incompatibles no se mezclan ni convierten automáticamente.

## Despliegue y reversión

Aplicar migraciones aditivas, habilitar captura local primero y probar importación acotada. Revertir interfaz conservando fuentes/versiones. No borrar datos históricos ni sobrescribir el espejo remoto. Entregar a B y D el contrato de fuentes y sus fixtures; no implementar cálculos en este plan.

## Instrucción específica para Cursor

> Trabaja exclusivamente en TN-07A. Lee este archivo, las instrucciones locales y los contratos de los requisitos previos. Confirma el estado real del checkout, reutiliza lo existente y concreta nombres/archivos nuevos según convenciones del proyecto. Implementa sus pasos en cambios acotados, con pruebas de aceptación y sin incluir otros planes. Consulta documentación oficial vigente si una decisión depende del framework o del proveedor. No presentes componentes propuestos como existentes. Si un requisito falta, delimita lo bloqueado. Reporta archivos, migraciones, pruebas realmente ejecutadas, criterios cumplidos, riesgos pendientes y reversión. No publiques ni despliegues por el solo hecho de implementar.

## Condición de cierre común

- [ ] Todas las pruebas específicas de este archivo están ejecutadas o su limitación está documentada; no declarar cierre completo si queda un criterio obligatorio bloqueado.
- [ ] Diff acotado al plan y contratos entregados al siguiente.
- [ ] Migraciones nuevas; no editar las ya aplicadas ni eliminar historial para resolver incompatibilidades.
- [ ] Pruebas de API simuladas; validaciones reales únicamente con entorno/productos de ensayo apropiados.
- [ ] Comandos de test/build elegidos según scripts del checkout; no inventar resultados.
