# Municipal: recorrido físico propio y selector compartido de pedidos pagados

Fecha: 21 de noviembre de 2026 · Estado: **borrador para aprobación** · Tamaño: proyecto, en fases.
Contrato operativo: `gestion-pedidos-docs/GELIA_GESTION_PEDIDOS_GUIA_OPERATIVA_V3.1_2026-11-21.md` (M-F01 a M-F03, §7.4, §7.5).

## 1. Intención y por qué

GELIA centraliza lo que antes se reportaba por WhatsApp y Drive, para que la operación sea integral y rastreable. Cada modalidad (Tienda, Municipal, Nacional) tiene responsabilidades propias y solo se comunica con las otras en dos puntos: el **cambio de modalidad** (conserva pedido, pagos, remisión y evidencia) y el **traslado físico** entre ubicaciones.

La auditoría del 21 de noviembre encontró que el tramo físico de Municipal no es propio: usa la tarea de preparación de Tienda (`ENVIO_MUNICIPIO`: carátula, empaque, despacho) o cae en el empaque genérico de CEDIS, que comparte estatus de guía y rastreo con Nacional. El alta municipal nueva (M-01) nunca crea esa tarea. Resultado: un municipal remisionado no tiene camino claro para prepararse, trasladarse ni salir a terminal.

## 2. Roles (según el usuario)

- **Vendedora:** única que habla con el cliente. Crea el pedido, consulta, anexa/retira, cierra consulta, **decide si se prepara en CEDIS o en Tienda**, reporta el costo real de envío cuando se conoce después (la comunicación con la terminal ocurre fuera de GELIA) y comparte la ficha.
- **Auxiliar:** verifica información, remisiona, confirma pagos y guías, y da la luz verde para preparar. No prepara ni cobra.
- **CEDIS / Tienda:** responden y preparan solo tras la luz verde.
- **Encargado de guías:** solo Nacional. Municipal nunca entra a su bandeja.
- **Gerencia PDV:** recibe pedidos y los pasa a custodia. **Recepcionista:** genera turnos, recibe la custodia y entrega al cliente.
- **Repartidor:** fuera de GELIA por ahora. Gerencia PDV registra la salida a terminal.

## 3. Objetivos

1. Municipal tiene recorrido físico propio, sin depender de la tarea de Tienda ni de estatus de guía.
2. Recorrido: Vendedora decide origen → Auxiliar luz verde → CEDIS prepara (empaque) → traslado CEDIS→PDV → Gerencia PDV confirma recepción → **custodia compartida** → desde custodia, el cliente recoge o se registra **Salida a terminal** (Gerencia PDV, con receptor).
3. **Sin estado de custodia propio de Municipal.** La custodia es la misma de Tienda; el destino se decide desde ella.
4. Recepción y custodia reutilizan los servicios compartidos de traslado y recepción de Tienda, sin que Municipal dependa de las reglas de Tienda.
5. Cambios de modalidad: con el paquete en custodia solo cambia el destino; tras la salida a terminal no hay cambio ordinario (M-07).
6. Selector compartido de pedidos pagados del cliente por ubicación (sección 5).

## 4. No objetivos y anti-objetivos

- No crear repartidor, caja del repartidor ni paso extra de reporte de costo.
- No tocar el trabajo en curso de Nacional/CEDIS de Paola Castellanos (commits de `aux-sistemas/activos-update` del 21 de noviembre; cambios sin confirmar en `custodiaNacional` y el permiso de segundo traslado).
- No restaurar la tarea de Tienda como vía municipal ni crear un módulo municipal paralelo con custodia propia.
- No extender guía, rastreo ni reglas de Delegado a Municipal; no extender carátula/terminal a Nacional.
- P-01 (regularización del flete diferido) conserva su alcance: la vendedora reporta el costo; no se agrega paso para el repartidor.
- Anti-objetivo: que un cambio en Tienda o Nacional rompa Municipal, o al revés.

## 5. Selector compartido de pedidos pagados por ubicación

- La vendedora, al armar un pedido nuevo, trae pedidos ya pagados del mismo cliente desde **cualquier almacén**: custodia de Tienda, CEDIS y City Center cuando se habilite.
- Filtra por almacén y por cliente; selección múltiple; **la selección se conserva al cambiar de almacén**.
- El mecanismo (integrar en sucursal, trasladar a CEDIS, anexar al resguardo) **se deduce de la ubicación**; ya no lo elige la vendedora.
- Los almacenes que aparecen salen del catálogo existente (Catálogos → Almacenes: activo, visible en pedidos, tipo PDV, permite salida/surtido) y respetan la lista piloto de almacenes habilitados de Preparación Tienda (`PreparacionTiendaConfig`). City Center existe pero hoy está oculto; no aparece hasta que se configure. **No se agrega otro mecanismo de configuración.**
- Cada trabajador ve su parte en su vista (integración local en PDV, integración en CEDIS, recepción), como ya ocurre en las superficies existentes.
- Reemplaza el panel municipal de una remisión a la vez (`RemisionesPagadasMunicipal.jsx`, `VincularRemisionPagadaService`) y servirá de base a `CustodiaComplementoNacional` en una fase posterior.

## 6. Restricciones

- Reutilizar: custodia/resguardos PDV, traslados y recepción, `PoliticaResguardoEnvio`, política financiera municipal, expediente de consignatario.
- Mantener vigentes las reglas existentes: Ventas no confirma hechos físicos; vincular no es integrar; conjunto protegido tras cerrar acumulación o salir; idempotencia y bloqueos.
- Compatibilidad: pedidos municipales y tareas `ENVIO_MUNICIPIO` ya existentes deben seguir legibles. Sin `migrate:fresh`, sin tocar datos reales, sin commit/push sin pedirlo.
- Interfaz: partir de `GUIA_DISENO_GELIA.md` y `.ux-profile.md`; verificar a 375 y 1280 px.
- Permisos nuevos: migración + `permisos_actuales.php`.
- Pruebas por Sail con cachés aisladas; dirigidas y proporcionales.

## 7. Enfoques considerados

- **A (elegido):** recorrido físico propio de Municipal apoyado en custodia y traslado compartidos. Separa responsabilidades sin duplicar motores. Cuesta extraer carátula/empaque/despacho de la tarea de Tienda.
- **B (descartado):** crear la tarea de Tienda al dar luz verde. Rápido, pero mantiene el acoplamiento que se quiere eliminar.
- **C (descartado):** módulo municipal completo con custodia y traslado propios. Duplica motores y contradice la decisión de no tener estado de custodia propio.

## 8. Fases

1. **Desacoplar de Nacional/Tienda:** el empaque de CEDIS para municipal no usa estatus de guía, rastreo ni exige guía; se confirma qué fase de estatus usa en su lugar. Sin cambios en Nacional.
2. **Recorrido físico municipal:** origen decidido por Ventas, preparación en CEDIS (empaque; la carátula se genera del pedido y la usa Tienda/PDV), traslado y recepción compartidos, custodia compartida, acción «Salida a terminal» con foto y receptor.
3. **Selector compartido** conectado a Municipal.
4. **Cambios de modalidad** sobre el recorrido nuevo (M-02, M-07, M-03).
5. **Nacional** adopta el selector después, coordinado con Paola.

Cada fase termina con pruebas dirigidas y revisión visual de las pantallas tocadas antes de pasar a la siguiente.

## 9. Pendiente para el constructor

- Elegir la forma técnica de «Salida a terminal» sobre la custodia sin tocar el flujo de entrega de Tienda.
- Decidir el nombre de estatus de ciclo para municipal en CEDIS y su compatibilidad con reportes.
- Migración de municipales ya en curso (no cambiar su estado en vuelo).
- **Mis llamadas, a revisar:** el mecanismo se deduce de la ubicación; la carátula la genera CEDIS en la preparación; la carátula se genera desde los datos del pedido, la usa Tienda/PDV, y la salida solo exige receptor.

## 10. Decisiones del usuario (21 de noviembre)

- **Carátula:** se genera con los datos del pedido (terminal, consignatario, teléfono, condición PAGADO/POR COBRAR), sin captura adicional. Por ahora solo la utiliza Tienda. CEDIS no la usa de momento. *Mi llamada, a corregir:* se genera y se coloca en el lado de Tienda/PDV, donde llega el paquete, y deja de depender de la tarea de preparación de Tienda.
- **Salida a terminal:** basta el **receptor**. La foto no es obligatoria por ahora (sustituye mi llamada anterior de foto obligatoria). Queda prevista como mejora futura.
