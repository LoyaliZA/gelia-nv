# TN-07E · Exportación CSV compatible y plan de contingencia

Fecha: 11 de septiembre de 2026. Proyecto: `gelia-nv`, módulo TiendaNube.
Base conocida: commit `5eeb392f0d90b62cdb292c5326b98398a2d787a7`. No se revalidó el checkout hoy. Este documento especifica trabajo por implementar; no afirma que sus componentes propuestos existan ni que se hayan ejecutado pruebas.

## Regla de alcance

Ejecutar únicamente TN-07E. Leer instrucciones del repositorio y contrastar las rutas citadas con el código actual. Conservar las correcciones previas del módulo. Resolver dependencias usando sus contratos; si falta una, reportarla sin implementar silenciosamente otro plan. Un archivo por tarea y revisión propia; no reemplazar archivos compartidos con copias antiguas.

El sistema usa costo general capturado/importado y listas opcionales de nombres configurables. No impone fuente comercial. Unidad de operación: tienda/producto/variante. Las referencias monetarias se mantienen separadas de los valores remotos publicados. Sin automatización comercial por fecha o disparador en esta primera versión.

## Objetivo y dependencia

Generar un CSV nativo de importación TiendaNube desde una revisión aprobada de D, sin llamadas en vivo al proveedor al exportar. No requiere F. No recalcular porcentajes ni crear reglas propias. Es un plan B frente a fallo de API, no una garantía de acceso a la tienda sin internet.

## Contrato de exportación

Entrada: snapshot aprobado, plantilla nativa validada, identidad de filas y perfil de formato. Salida: archivo CSV, hash, fecha, revisión, plantilla/version, cantidad de filas y estado `generado`. Eventos descarga/declarado_importado/verificado son distintos. No marcar aplicado al descargar.

## Pasos de implementación

1. **Obtener plantilla real.** Usar exportación reciente de la tienda con un producto simple y otro multivariante. Verificar permisos de importación de la cuenta. Confirmar encabezados, codificación, delimitador, decimal y columnas de propiedades. Sin esta evidencia no declarar “listo para importar”.
2. **Mapear identidad.** Preservar identificador de URL y propiedades de variantes; vincular inequívocamente con IDs locales. No identificar solo por SKU cuando esté duplicado. Bloquear identidad faltante y no reconstruir slugs a partir de nombres.
3. **Perfil versionado.** Guardar contrato del CSV, plantilla y fecha. Revalidar documentación y plantilla al cambiar formato. Tomar los siguientes detalles como referencia verificada el 10/09/2026: precios/costos numéricos sin símbolo, decimal con punto; promoción `-` elimina y vacío conserva. Verificar de nuevo antes de publicar el exportador.
4. **Generar.** Leer valores absolutos de D. Serializar `conservar/establecer/eliminar` según perfil. No añadir columnas de reglas, margen o auditoría al CSV nativo. Producir reporte de revisión separado si se solicita desde la interfaz.
5. **Evitar efectos colaterales.** Incluir columnas mínimas permitidas y filas de contexto requeridas por el importador. Si requiere plantilla completa, conservar columnas ajenas y explicar que deben ignorarse al importar. No habilitar actualización de stock. No dividir variantes del mismo producto entre archivos.
6. **Formato.** Escritor CSV real con comillas y saltos escapados, UTF-8 conforme al perfil, importes decimales sin miles. Preservar SKU textual. Detectar texto que pudiera ejecutarse como fórmula al abrir en Excel; bloquear casos inseguros de identidad en vez de anteponer caracteres que alteren el matching. No exportar fórmulas.
7. **Partición y descarga.** Respetar límite verificado del proveedor; referencia previa hasta 20,000 líneas. Contar cabecera conservadoramente, evitar grupos incompletos y registrar manifiesto local del conjunto. Autorizar descargas y definir retención.
8. **Verificar importación.** Probar archivo acotado en productos de ensayo, comparar campos objetivo y confirmar que stock, categorías y variantes permanecen correctos. Incorporar luego exportación nueva o lectura remota para conciliar; la UI debe distinguir declaración del usuario de verificación.

## Contingencia y límites

| Incidente | Resultado posible |
| --- | --- |
| API caída; Gelia y panel TiendaNube accesibles | Exportar snapshot y subir manualmente |
| Gelia caído | Usar archivo ya descargado |
| TiendaNube caída | Preparar si Gelia funciona; importar cuando vuelva |
| Sin internet, Gelia remoto | Solo archivos previamente descargados; no abrir Gelia ni importar |
| Gelia local accesible sin salida a internet | Exportar con snapshot/plantilla guardados; importar después |

Un archivo descargado no puede invalidarse remotamente: indicar fecha y revisión en nombre/instrucciones. No prometer restauración automática con un CSV antiguo. “Offline completo” es otro proyecto.

## Fuentes de formato

- [Campos oficiales del CSV](https://ayuda.tiendanube.com/es_MX/122710-importar-y-exportar-productos/como-completar-el-excel-de-carga-masiva-de-productos).
- [Importar modificaciones masivas](https://ayuda.tiendanube.com/es_MX/122710-importar-y-exportar-productos/como-modificar-los-productos-de-forma-masiva).

El perfil final debe probarse con la tienda concreta. Si falta plantilla, puede ofrecerse reporte NO IMPORTABLE, dejando explícitamente pendiente este plan.

## Componentes, pruebas y salida

Crear `ExportarPreciosCsvService`, perfil de plantilla, registro de artefactos y endpoint desde lotes D. No generar el CSV desde React ni desde datos del navegador no aprobados.

- [ ] CSV y snapshot coinciden numéricamente.
- [ ] Quitar promoción usa intención de eliminación, no cero.
- [ ] SKU con ceros, acentos, comillas y variantes se conserva o bloquea explícitamente.
- [ ] Relectura del CSV reproduce filas y valores esperados.
- [ ] Exportar funciona sin acceso a API cuando las entradas están guardadas.
- [ ] Descarga no cambia estado remoto del lote.
- [ ] Importación de ensayo no duplica productos ni cambia stock.

Desplegar con exportación limitada hasta validar importador; ante regresión detener generación nueva y conservar artefactos/hashes. No reenviar por API ni implementar restauración en este alcance.

## Instrucción específica para Cursor

> Trabaja exclusivamente en TN-07E. Lee este archivo, las instrucciones locales y los contratos de los requisitos previos. Confirma el estado real del checkout, reutiliza lo existente y concreta nombres/archivos nuevos según convenciones del proyecto. Implementa sus pasos en cambios acotados, con pruebas de aceptación y sin incluir otros planes. Consulta documentación oficial vigente si una decisión depende del framework o del proveedor. No presentes componentes propuestos como existentes. Si un requisito falta, delimita lo bloqueado. Reporta archivos, migraciones, pruebas realmente ejecutadas, criterios cumplidos, riesgos pendientes y reversión. No publiques ni despliegues por el solo hecho de implementar.

## Condición de cierre común

- [ ] Todas las pruebas específicas de este archivo están ejecutadas o su limitación está documentada; no declarar cierre completo si queda un criterio obligatorio bloqueado.
- [ ] Diff acotado al plan y contratos entregados al siguiente.
- [ ] Migraciones nuevas; no editar las ya aplicadas ni eliminar historial para resolver incompatibilidades.
- [ ] Pruebas de API simuladas; validaciones reales únicamente con entorno/productos de ensayo apropiados.
- [ ] Comandos de test/build elegidos según scripts del checkout; no inventar resultados.
