# Incidente de importación Wizerp — 12 de noviembre de 2026

Este documento no contiene datos de clientes ni el contenido de ningún respaldo. No se leyó `storage/app/backups/backup_gelia_nv_2026-11-12_110021.sql.gz`.

GELIA decide con sus reglas. El archivo de aquel día es una entrada histórica, no un control remoto. No hay API ni base compartida con el origen del reporte. El nombre de ese origen se conserva en este incidente y no se renombra el código histórico mientras la consolidación sigue en borrador.

## Hechos verificados en el código

La rama de producción `main` recibió el commit `bd086c41` («FIX EMERGENCIA IMPORTACION MASIVA») sobre el importador anterior a Fase 1. Ese arreglo deja de tratar un `codigo_lista` vacío como baja y deja de cambiar `lista_actual_id`. También escribía un registro de depuración en `/var/www/html/.cursor/debug-017bf5.log`. Esa escritura no forma parte de la consolidación.

`develop` ya tenía Fase 1 (PR #26), la conciliación documental (PR #28) y el diseño de Fase 2B (PR #30). El PR #31 se fusionó en `feature/fase2a-conciliacion-documental`, no en `develop`. La implementación verificada de Fase 2B es el PR #32 (`fcbca7b3`), ya rebasado sobre `develop`. Esta rama la incorpora tal cual y no rederiva el beneficio desde la rama vieja.

El importador que queda es el de Fase 1, no el archivo antiguo de `main`. Sobre ese importador, un `codigo_lista` vacío conserva `es_inactivo` y `lista_actual_id`. Un monto cero no inactiva. Un código válido puede activar y, si la lista no está bloqueada y el módulo no gobierna al cliente, puede actualizar la lista. La lista bloqueada no impide el cambio de estado. El monto solo se escribe si la autoridad de Escalonamiento está apagada y el valor pasa la validación monetaria. El CSV no infiere cobertura documental ni mueve `monto_venta_actual` por un pago confirmado. `conciliar()` sigue sin llamador de producción.

Un cliente nuevo sin nombre, sin código válido o con un monto ilegible no se crea. No se inventa activo ni inactivo. Una fila rechazada no detiene las demás.

## Cifras reportadas, no recontadas aquí

Estas cantidades llegaron como reporte del incidente. No se volvieron a contar contra la base operativa ni contra el respaldo, y la plantilla reconstruida no se toma como prueba independiente del ERP:

- 6827 filas
- 2137 códigos de lista vacíos
- 2062 clientes etiquetados inactivos
- 790 listas restauradas
- 95 montos del CSV más altos que el valor guardado
- 3 filas sin cliente existente
- 25 cuentas de demostración dejadas fuera

La prueba sintética de 6827 filas solo comprueba el contrato del código con números ficticios. No reproduce esas filas ni acredita las cifras.

## Qué ocurrió

El importador de Fase 1, antes de este ajuste, interpretaba código vacío más monto cero como inactivación y pasaba la lista a Público General cuando la lista era editable. Un código vacío con monto positivo se rechazaba. La exportación usada el 12 de noviembre dejó el código en blanco en clientes que seguían activos. Esa celda vacía no era una baja del ERP.

El hotfix de `main` corrigió ese efecto en el importador previo. La consolidación conserva el efecto y lo aplica al importador de Fase 1. No restaura montos de octubre que una importación de noviembre ya reemplazó. No corrige en automático las 95 diferencias de monto: quedan como conciliación de negocio pendiente contra Wizerp.

## Señal de baja que todavía no existe

Una baja legítima solo puede aceptarse con una señal explícita del ERP, distinta de un código de lista vacío y distinta de un monto cero. El archivo actual no trae esa columna. La condición que la probaría sería un campo propio, por ejemplo `estatus`, con un valor afirmativo acordado (`INACTIVO`) y presente en la fila. Hasta ese contrato, el importador no inactiva.

## Relación con las fases

- Fase 1 sigue siendo la autoridad del CSV sobre monto y estado cuando la autoridad de Escalonamiento está apagada. Este ajuste corrige la fila «código vacío + cero» de ese contrato.
- Fase 2A distingue pago, documento e inclusión. Un documento verificable no prueba que el monto esté en el CSV. La inclusión indeterminada no promueve la lista.
- Fase 2B revierte solo el beneficio que la solicitud todavía sostiene. Esta importación no borra una concesión, una ratificación posterior ni una marca histórica.

## Pruebas y límites

Hay regresión sintética en `tests/Feature/Clientes/IncidenteWizerp20261112Test.php`. No usa nombres, teléfonos, RFC ni filas financieras reales. Cubre código vacío, cero y monto positivo, Bronce, Plata, Oro y Diamante, lista bloqueada, inactivo con código válido, solicitud pendiente, historial de octubre que no se restaura, repetición, importación concurrente en SQLite, columnas ausentes, código desconocido, monto ilegible, alta incompleta y fila sin cliente. El conteo de 6827 filas, si la corrida lo incluye, usa solo identificadores ficticios.

`scripts/qa/wizerp-mysql.sh` exige Docker y un MySQL 8.4 efímero. Si ese entorno no existe, esa corrida no se aprueba. SQLite no acredita la concurrencia de MySQL.

## Seguimiento que no entra en este PR

La revisión previa de la carga masiva (apartado 14) entra en este mismo PR. El uso está en `docs/clientes/IMPORTACION_MASIVA_REVISION.md`. Siguen fuera el renombre de clases, una integración externa y una reescritura del módulo de clientes.

## Riesgos que siguen impidiendo un despliegue

- No hay señal de ERP para una baja real.
- Las 95 diferencias de monto no están conciliadas con Wizerp.
- Sin `fecha_corte` o `fecha_exportacion` legible, un archivo anterior todavía puede proponer montos distintos. Con esa fecha anterior al último historial, la confirmación se bloquea. Las 95 diferencias no se inventan.
- Con la autoridad apagada, un documento no prueba inclusión en el acumulado.
- Quien lea `lista_actual_id` sin el estado puede seguir beneficiando a un cliente inactivo.
- MySQL 8.4 del script de QA no sustituye a una corrida que no se haya ejecutado.
