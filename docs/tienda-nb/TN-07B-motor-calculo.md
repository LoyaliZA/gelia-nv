# TN-07B · Motor de cálculo, condiciones y redondeo

Fecha: 11 de septiembre de 2026. Proyecto: `gelia-nv`, módulo TiendaNube.
Base conocida: commit `5eeb392f0d90b62cdb292c5326b98398a2d787a7`. No se revalidó el checkout hoy. Este documento especifica trabajo por implementar; no afirma que sus componentes propuestos existan ni que se hayan ejecutado pruebas.

## Regla de alcance

Ejecutar únicamente TN-07B. Leer instrucciones del repositorio y contrastar las rutas citadas con el código actual. Conservar las correcciones previas del módulo. Resolver dependencias usando sus contratos; si falta una, reportarla sin implementar silenciosamente otro plan. Un archivo por tarea y revisión propia; no reemplazar archivos compartidos con copias antiguas.

El sistema usa costo general capturado/importado y listas opcionales de nombres configurables. No impone fuente comercial. Unidad de operación: tienda/producto/variante. Las referencias monetarias se mantienen separadas de los valores remotos publicados. Sin automatización comercial por fecha o disparador en esta primera versión.

## Objetivo y dependencia

Crear un motor puro, determinista, sin acceso a API, base de datos, archivos o cola. Usa el contrato de fuentes de A; puede desarrollarse con fixtures antes de que exista su interfaz. No implementar persistencia de reglas ni selección de productos.

## Contrato de entrada/salida

Entrada: snapshot de valores por variante, conjunto de reglas tipadas y política de redondeo/protección. Regla = `{condiciones[], base, operacion, parametro?, destino, redondeo}`. Salida: intención por campo (`conservar`, `establecer(valor)`, `eliminar`), valor bruto/final, regla, explicación, alertas y errores. Destinos: normal, promocional y costo remoto. Solo promocional admite eliminación en esta versión.

## Pasos de implementación

1. **Tipos y validación.** Definir esquema de condiciones: campo, operador `< <= = >= > entre tiene_valor no_tiene_valor`, valores y extremos inclusivos/exclusivos. Condiciones de una regla combinadas con AND. Validar operaciones y destinos; nada de eval, SQL o fórmulas arbitrarias.
2. **Decimales.** Usar aritmética decimal disponible o dependencia específica justificada. Serializar importes como cadenas. No usar float binario como autoridad. Validar límites de precisión/escala compatibles con persistencia.
3. **Cálculos.** Implementar copiar B, fijar importe, B±importe, B×(1±p/100) y margen C/(1−m/100). Margen objetivo solo desde costo hacia precio de venta. Exigir 0≤m<100; reducción entre 0 y 100 y bloquear venta final cero por defecto. Costo cero no permite reportar aumento porcentual sobre costo como finito.
4. **Evaluar condiciones.** Usar siempre snapshot inicial, incluida la condición. Un filtro puede mirar precio normal y calcular desde costo. No recalcular sobre resultados de otra regla ni permitir encadenamientos implícitos.
5. **Conflictos.** Dos reglas que escriben el mismo campo/variante bloquean esa fila, aunque produzcan el mismo importe. Reglas a campos distintos conviven. Orden visual no resuelve conflictos.
6. **Redondear.** Dos decimales half-up por defecto. Entero con dirección arriba/abajo/cercano. Terminación .90/.99 con escalón de una unidad monetaria y dirección explícita; definir desempate. Conservar valor bruto y final.
7. **Validación final conjunta.** Promocional debe ser positivo y menor que normal. Cambiar normal conserva promoción salvo regla explícita; si deja relación inválida, bloquear. Aplicar protecciones de margen después del redondeo. No ajustar precio silenciosamente para alcanzar protección.
8. **Métricas.** Margen estimado = (venta efectiva−costo)/venta efectiva. Utilizar promoción válida si existe. Mostrar costo usado y diferencia absoluta. Si no hay costo, margen no calculable; si origen es cero, variación porcentual no calculable. No presentar ganancia neta sin gastos.

## Tabla de pruebas obligatorias

| Entrada | Resultado |
| --- | --- |
| Costo 330, aumento 30 % | 429.00 |
| Costo 330, margen 30 % | 471.43 |
| Costo 330, aumento 75 % | 577.50 |
| Normal 90, descuento 6 % a promoción | 84.60, normal conserva 90 |
| Normal 1600, aumento 12 % | 1792.00 |
| Base 577.50, reducción 10 % | 519.75 |
| Regla <100 / >1500, valores 100 / 1500 | No coinciden |
| 577.50, redondeo arriba a .99 | 577.99 |
| Fuente ausente o margen 100 % | Error, sin resultado publicable |

## Archivos y aceptación

Crear servicios/DTO de cálculo bajo `app/Services/Tiendanube/Precios/` y tests unitarios propios. No modificar `TiendanubeApiClient`. Entregar fixtures reutilizables por C/D/E/F.

- [ ] Misma entrada produce exactamente la misma salida y recalcular no acumula porcentajes.
- [ ] Condición, base y destino funcionan independientemente.
- [ ] Conflictos, nulos, límites, redondeo y protección tienen pruebas.
- [ ] El motor no ejecuta llamadas externas ni modifica sus entradas.

## Despliegue y reversión

No requiere migraciones por sí mismo. Versionar contrato del motor para reproducir snapshots futuros. No reemplazar cálculos existentes de otros módulos. Revertir implementación sin afectar fuentes; no avanzar a aplicación remota en este plan.

## Instrucción específica para Cursor

> Trabaja exclusivamente en TN-07B. Lee este archivo, las instrucciones locales y los contratos de los requisitos previos. Confirma el estado real del checkout, reutiliza lo existente y concreta nombres/archivos nuevos según convenciones del proyecto. Implementa sus pasos en cambios acotados, con pruebas de aceptación y sin incluir otros planes. Consulta documentación oficial vigente si una decisión depende del framework o del proveedor. No presentes componentes propuestos como existentes. Si un requisito falta, delimita lo bloqueado. Reporta archivos, migraciones, pruebas realmente ejecutadas, criterios cumplidos, riesgos pendientes y reversión. No publiques ni despliegues por el solo hecho de implementar.

## Condición de cierre común

- [ ] Todas las pruebas específicas de este archivo están ejecutadas o su limitación está documentada; no declarar cierre completo si queda un criterio obligatorio bloqueado.
- [ ] Diff acotado al plan y contratos entregados al siguiente.
- [ ] Migraciones nuevas; no editar las ya aplicadas ni eliminar historial para resolver incompatibilidades.
- [ ] Pruebas de API simuladas; validaciones reales únicamente con entorno/productos de ensayo apropiados.
- [ ] Comandos de test/build elegidos según scripts del checkout; no inventar resultados.
