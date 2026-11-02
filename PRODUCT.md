# GELIA — contexto de producto

<!-- impeccable:product-schema 1 -->

Contexto basado en las decisiones del usuario y en la inspección del repositorio. Fecha de actualización: 2 de noviembre de 2026. Este archivo orienta el diseño; no acredita implementaciones, pruebas ni despliegues.

## Platform

web

## Users

GELIA es una aplicación interna de coordinación comercial, logística y administrativa. En Gestión de Pedidos el nombre operativo corresponde a una vista. Un nombre personal es referencia de rol y no una condición de código.

- Vendedora: vista Registrar pedidos. Recibe primero al cliente, registra la solicitud y consulta sus decisiones.
- Auxiliar de ventas: vista Auditar pedidos. Verifica cobertura y registra remisión o resultado de crédito cuando el paso lo exige.
- Auxiliar de tienda: vista Preparación Tienda. Recibe solicitudes, responde disponibilidad y ejecuta el trabajo físico. No crea el pedido como Registrar pedidos.
- CEDIS y empacadores: vista Control Pedidos. Confirman mercancía, preparan y registran la salida que les corresponde.
- Encargado de guías: vista Entregar guías.
- Gerencia: autoriza excepciones expresamente definidas, incluido el segundo traslado.
- Administración: autoriza y controla resoluciones financieras según el caso.

Tienda como modalidad de entrega no equivale a Preparación Tienda como vista. Preparación Tienda también atiende tareas municipales legítimas, en un segmento distinto de la recolección.

## Product Purpose

Coordinar el trabajo entre áreas con información reutilizable, responsables identificables, evidencias y siguientes acciones claras. El usuario debe entender qué está atendiendo, qué falta y qué puede hacer sin memorizar todo el proceso.

En Gestión de Pedidos se distinguen tres modalidades: Tienda, Envío municipal y Envío nacional. Comparten capacidades operativas y conservan sus reglas y recorridos propios.

## Operating Context

La operación utiliza pedidos y remisiones de Wizerp, comprobantes, instrucciones de entrega, fotografías, bolsas, cajas, carátulas, guías, traslados y custodia. GELIA coordina y registra resultados; no debe suponerse una integración automática con Wizerp, caja, Drive o paqueterías.

El trabajo de diseño y desarrollo se realiza por fases revisables. El usuario ejecuta las instrucciones en Cursor y comparte planes, cambios y pruebas para supervisión. Los commits por fase permiten revisar modificaciones concretas. No se declara una fase completa solo por implementar pantallas.

La fase 1 de instrucción vigente fue reportada como implementada. Su cierre sigue con observaciones de concurrencia, autorización del segundo traslado y tres fallos de pruebas no reejecutados. La comprobación de concurrencia puede avanzar en paralelo con el diseño de la fase 2.1 y debe quedar resuelta antes de habilitar acciones nuevas que dependan de ella. No queda reservada como única validación para el cierre 2.1.5. Este documento no da la fase 1 por validada.

## Capabilities and Constraints

### Fuente operativa

La referencia principal sigue siendo el análisis V02.1. Las respuestas finales AC-01 a AC-15 prevalecen donde contradigan el contenido anterior; una respuesta parcial no elimina reglas compatibles. Las decisiones explícitas posteriores del usuario actualizan la interpretación y no reescriben el archivo del análisis.

El 2 de noviembre de 2026 la planificación leyó `gestion-pedidos-docs/Analisis_de_Requerimientos_Gestion_de_pedidos_V02.1_Dudas_resueltas.md`. La ruta `docs/` de ese mismo nombre no estaba en el repositorio. Hasta comparar ambas copias, no se tratan como dos autoridades.

Los documentos históricos del módulo son antecedentes. El código acredita lo implementado, no determina el funcionamiento requerido. Este contexto no sustituye las fichas ni resuelve sus ambigüedades.

### Separaciones operativas

- Distinguir pedido comercial, solicitud de entrega, preparación y movimiento físico, agrupación para entregar y control financiero.
- Separar modalidad, cobertura, causas de espera y ubicación. Pueden coexistir varios pendientes.
- Guardar borrador conserva captura; ejecutar una acción operativa requiere una instrucción explícita y condiciones válidas.
- Sustituir la entrega conserva compra, pagos y remisiones que sigan vigentes; no crea una nueva venta.
- Una compra pagada incorporada a otra entrega no vuelve a cobrarse.
- Un complemento se integra al confirmar recepción física; el aviso de salida no basta.
- Despacho, entrega a terminal y recepción final del cliente son eventos distintos.
- Los permisos y condiciones se comprueban en servidor. El estado visual no autoriza acciones por sí solo.
- El pago verificado cubre su pedido. El saldo generado conserva origen y restricción y no se aplica al pedido que lo originó.

### Rediseño de UX acordado

Planificar primero el recorrido de Tienda a través de las áreas; después Municipal y Nacional, y posteriormente sus cruces. La numeración de UX es distinta de la secuencia operativa anterior:

- 2.1.1: Registrar pedidos — Tienda.
- 2.1.2: Control Pedidos — confirmación y preparación para Tienda.
- 2.1.3: Auditar pedidos — cobertura y documentación.
- 2.1.4: Preparación Tienda — apartado, recepción, custodia y entrega.
- 2.1.5: Validación integral de Tienda y autorizaciones pertinentes.
- 2.2: Municipal; 2.3: Nacional; 2.4: cruces, anexos y agrupaciones.

Cada subfase identifica fichas del análisis, entradas, acciones, responsables, consecuencias, pruebas y pendientes. Una ficha puede atravesar varias vistas y solo se cierra al comprobar su recorrido completo. Los cruces de Tienda con envíos se cierran cuando ambos recorridos estén disponibles.

El orden inicial propuesto por el usuario es número de cliente, departamento precargado desde el principal del emisor, folio del pedido y modalidad. Evaluar el orden contra los casos y los datos realmente disponibles; no exigir repetición innecesaria ni confundir folio de pedido con remisión.

Para el segundo traslado, se acordó representar la solicitud como pendiente de autorización de gerencia, mostrando su causa. El tag debe reflejar un pendiente real con resolución; no constituye autorización ni una regla general para cualquier ajuste. La prohibición adicional de que quien vende autorice su propio traslado no está aprobada.

### Decisiones confirmadas el 2 de noviembre de 2026

Estas decisiones actualizan la interpretación del análisis. El archivo V02.1 no se modificó.

- T-01 empieza en Registrar pedidos. El cliente comunica piezas y cantidades; esa vista registra y solicita el apartado sin pago. Preparación Tienda comprueba disponibilidad y condición, responde, mantiene apartado lo aceptado y solo entrega después de confirmar el pago presencial. Preparación Tienda no crea el pedido.
- Guardar y consultar disponibilidad no exigen pago. El apartado T-01 tampoco. Transferencia y crédito conservan verificación y remisión en los pasos que les corresponden. La cobertura no desaparece para toda la modalidad Tienda.
- La revisión de disponibilidad, en Control Pedidos y en Preparación Tienda, registra solo excepciones: daño o detalle, falta de existencia y disponibilidad parcial. Sin excepciones, la revisión queda concluida únicamente con confirmación explícita y evidencia del pedido completo. Un borrador o la ausencia de marcas por pieza no significan revisión terminada. No se inventan inspecciones de las piezas no capturadas. La misma capacidad sirve a ambas vistas. El efecto sobre municipal y nacional queda identificado para sus fases y no se implementa con este diseño.
- Mismo día (Tienda MD) es una condición de atención de la modalidad Tienda con cobertura por transferencia. No es una cobertura alternativa y no se combina con crédito ni con pago al recoger. La facultad es configurable por departamento o usuario. Opera de lunes a viernes y no el sábado. La solicitud sin pago reportado queda prevista, no confirmada. El corte de las 13:00 corresponde a la hora en que el servidor acepta el reporte del pago: `pedido_bma_pagos.created_at` del primer reporte aceptado. Verificar el ingreso o registrar la remisión después de las 13:00 no rechaza un reporte que entró al corte. La entrega el mismo día además exige disponibilidad, documentación, preparación y traslado. Un reporte fuera del corte no cambia la modalidad ni reprograma.
- Las altas actuales de Municipal y Nacional siguen en su formulario. La sección nueva es de Tienda. Un cambio de modalidad sobre un pedido ya iniciado respeta los límites físicos, documentales y financieros, y no abre cruces nuevos.
- Adjuntar PDF o capturas de Wizerp en T-01 no está confirmado. No es requisito de este diseño.

### Decisiones abiertas

Siguen sin regla aprobada: momento exacto del vencimiento de crédito, duración VIP, consecuencia financiera de mercancía pagada liberada, excedentes menores a $10, remisión asegurada que no cabe en una caja, absorción de costos internos, archivo de Wizerp en T-01, piezas ya disponibles en PDV dentro de Mismo día (Tienda MD) y la continuación cuando el reporte del pago queda fuera del corte de las 13:00. No elegir valores ni bloquear acciones confirmadas por inferencia.

## Brand Commitments

Dirección visual compartida: leer `GUIA_DISENO_GELIA.md` antes de elegir o aplicar skills de diseño. Contiene las referencias de Punto de venta, solicitudes, facturas, cancelaciones y resguardos, junto con el modal Registrar pedido revisado con el usuario el 2 de noviembre de 2026. Las skills aportan técnicas dentro de esa dirección; Impeccable es opcional. La coherencia se comprueba en la composición completa, no solo en el uso de tokens o la ausencia de desbordamientos.

Nombre de producto: GELIA. Lenguaje operativo en español, directo y concreto. Prioridad explícita del usuario: comprensión, accesibilidad y eficiencia por encima de decoración. El rediseño puede sustituir funcionamiento incompatible; conservar piezas exige que encajen en el proceso requerido.

## Evidence on Hand

- Requisitos operativos: archivo V02.1 indicado arriba.
- Propuesta visual aislada de Registrar pedidos — Tienda: `docs/visual-refresh/registrar-pedidos-tienda/`. No forma parte de la aplicación.
- Contexto general: `README.md`.
- Backend del módulo: `app/Services/ControlPedidos`, `app/Models/ControlPedidos` y `app/Support/ControlPedidos`.
- Interfaces: `resources/js/Pages/ControlPedidos`; los vínculos de PDV deben inspeccionarse en su módulo correspondiente.
- Sistema visual: `resources/css/gelia`, `resources/js/utils/geliaTheme.js`, componentes GELIA y `docs/style-system`. Comprobar imports y valores efectivos antes de proponer estilos.
- Reglas y skills de Cursor: `.cursor/rules` y `.cursor/skills`. Leer las instrucciones reales; su presencia no demuestra que se hayan ejecutado.
- El repositorio declara Anime.js y Motion. En esta revisión Anime.js 4.4.1 está instalado; comprobar vigencia antes de implementar movimiento.

No inventar métricas de ahorro, evidencia de uso, aprobaciones, pruebas realizadas ni entregas confirmadas.

## Product Principles

1. Mostrar una siguiente acción comprensible y las alternativas válidas para el caso y el responsable.
2. Reutilizar información válida y pedir confirmación solo de lo afectado por el cambio.
3. Completar consecuencias físicas, documentales y financieras; una pantalla no constituye un proceso completo.
4. Explicar pendientes mediante causa, responsable y siguiente acción. El color por sí solo no comunica el estado.
5. Incorporar movimiento solo si ayuda a comprender un cambio o da respuesta inmediata sin retrasar tareas repetitivas.

## Accessibility & Inclusion

La facilidad de comprensión es un requisito explícito del usuario. Diseñar para personal con distinta familiaridad con el sistema: etiquetas visibles, términos consistentes, mensajes accionables, recorrido por teclado, foco visible, errores asociados al campo y estados que no dependan únicamente del color. Respetar reducción de movimiento y mantener acceso a información y acciones en pantallas pequeñas. Esto expresa objetivos de diseño, no una certificación de cumplimiento.
