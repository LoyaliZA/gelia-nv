# Comisiones de empaque — módulo en Gestión interna

Rama `main`, commit de partida `811f5fe`. No hay commit ni push de este trabajo. La migración sí se aplicó en la base MySQL de Sail.

En el árbol hay otros cambios de GELIA AI que no forman parte de este módulo. Este informe solo cubre comisiones de empaque.

## Alcance y decisiones

El módulo es independiente. No usa colaboradores, saldos, descuentos, recibos ni nómina de Recursos Humanos. No modifica pedidos, cajas, bultos, cumplimiento ni el reporte de pagos. Un pago de este módulo es la constancia de un pago hecho fuera del sistema.

La comisión se genera donde se registra el empaque, no por el origen del pedido ni por el destino.

| Decisión | Regla |
| --- | --- |
| Área | CEDIS si el empaque se registró en CEDIS. Tienda si se registró en preparación de tienda o en un registro manual ya aceptado. Call Center, Bellaroma y Aromas solo identifican el pedido |
| Tarifa | Plana por área y configurable, con vigencia. Semilla: CEDIS $10.00 y Tienda $6.00 desde 2026-01-01. Un cambio no recalcula cortes confirmados |
| Quién genera | La cuenta que registró el empaque. Participantes y ayudantes deben tener cuenta. Desactivar a una persona no borra su historia ni impide pagar su saldo |
| Reparto | Por área, versiones sin solape, suma exacta de 100 %. No se redistribuye solo al retirar a alguien. Un periodo con varios repartos se calcula por tramos |
| Registro manual | Permitido. Queda pendiente de revisión y no entra al importe por cortar hasta aceptarlo, con motivo, responsable y fecha |
| Resguardo | Si se empacó en CEDIS y también en tienda, por ahora cuenta en las dos áreas y queda marcado como excepción. Recibir el resguardo no es empacarlo. La regla se podrá cambiar después |
| Cancelación | Quien gestiona el módulo decide conservar o excluir, con motivo, responsable y fecha. Mientras no hay decisión, el importe queda aparte y no entra a un corte nuevo. Si ya estaba en un corte confirmado, el corte se conserva y la corrección es un ajuste |
| Reempaque | No genera sola una segunda comisión. Se presenta a revisión |
| Histórico | La fecha de inicio es obligatoria para importar. Vacía al instalar: no se cargan empaques viejos que pudieron pagarse en Excel |
| Corte | El borrador no consume cajas. Confirmar asigna folio, congela unidades, tarifas, repartos e importes, y la misma unidad no puede entrar a otro corte. Confirmar no marca pagado |
| Dinero | Centavos enteros. El residuo del reparto se asigna por residuo mayor y, si empatan, al participante de id menor. El total repartido coincide con el corte |
| Ajuste negativo | Se muestra. No se descuenta de nómina ni de un pago futuro salvo que se aplique de forma explícita al saldo |

## Fuentes que prevalecen

No se suman todos los `numero_cajas` ni la misma unidad desde varias fuentes. El `numero_cajas` de `operaciones_empaque` no crea unidades: es la suma de cabeceras.

| Flujo | Prevalece | No se usa como unidad |
| --- | --- | --- |
| CEDIS, pedido con `empacado_at` | Cajas activas de `pedido_bma_cajas`. Si no hay, cada bulto de `pedido_bma_bultos_empaque`, pendiente de clasificación. Si no hay bultos ni cajas, el `numero_cajas` de esa cabecera | Cajas retiradas. Total de la operación. Cabecera de los miembros cuando otro miembro ya tiene cajas activas |
| Consolidación | Cajas activas del miembro que las tiene, en la práctica el principal | Cabeceras de los demás miembros y el total de la operación. Un bulto de otro miembro queda en revisión por posible solape |
| Complemento | Cada pedido empacado conserva sus cajas o sus bultos | No se suman al `numero_cajas` del principal |
| Tienda | `pedido_bma_cumplimiento_fisico` con `empacado_at` y `bultos_salida`. Pendiente de separar cajas y bolsas. El peso no es requisito | El `numero_cajas` del mismo pedido. Responder la preparación, que no registra empaque |
| Resguardo | Las dos unidades, CEDIS y tienda, si las dos existen. Marca `excepcion_resguardo` | La recepción en punto de venta |
| Manual | Captura del módulo, solo después de aceptarla | No se inventa desde Call Center o Bellaroma |

La bolsa no lleva tarifa cero silenciosa: se marca excluida y el desglose debe cuadrar con la cantidad de origen. Si falta tarifa para la fecha, la unidad queda «Sin tarifa para la fecha».

## Comportamiento

| Caso | Resultado |
| --- | --- |
| Empaque CEDIS con cajas activas | Área CEDIS. Importe = cajas × tarifa vigente. El costo de envío, seguro o guía no entra |
| Empaque municipal o de preparación en tienda, sin peso | Área Tienda. Pendiente de clasificación. No entra a CEDIS aunque el origen sea Call Center |
| Bulto o cabecera sin tipo | «Pendiente de clasificación». No entra a un corte |
| Desglose que no cuadra con el origen | Se rechaza y se indica la suma contra el total |
| Tres cajas de un tipo, dos de otro y una bolsa | Cinco cajas comisionables; la bolsa queda excluida |
| Registro manual | Pendiente de revisión. Aceptarlo lo deja listo para clasificar; rechazarlo lo saca del acumulado |
| Pedido cancelado, unidad aún libre | «Cancelación por decidir». No entra a un corte nuevo |
| Pedido cancelado, unidad ya en corte | El corte y el importe original se conservan. Se abre incidencia para un ajuste |
| Reversión de empaque | La unidad queda en revisión. No se borra |
| Reempaque posterior | La unidad nueva queda en revisión. No pasa sola a elegible |
| Empaque anterior a la fecha de inicio | No se importa |
| Empaque cuya fecha ya cubre un corte confirmado | Incorporación tardía. No se inserta en ese corte. Puede incluirse de forma explícita en uno posterior |
| Salida de un participante el día 14 | El primer corte conserva A 40 %, B 25 %, C 20 % y D 15 %. Pagar solo a D no reparte ese saldo entre el resto. El tramo siguiente usa el reparto nuevo |
| Participante inactivo | No entra a un reparto nuevo. Su saldo pendiente se puede pagar |
| Pago parcial y mismo reintento | El segundo envío con la misma clave no duplica. Un importe mayor al pendiente se rechaza |
| Dos confirmaciones del mismo intervalo | La segunda no vuelve a incluir las unidades |
| Corte confirmado | Folio `CE-año-#####`. Estado de pago pendiente, parcial o pagado, derivado de las asignaciones |
| Exportar | No confirma un corte ni registra un pago. Un borrador se marca provisional |

## Hipótesis descartadas

- Tomar el área del departamento actual de la persona. El área es el proceso que registró el empaque y se conserva.
- Tratar todo bulto como caja. Bulto y `bultos_salida` quedan pendientes de clasificación.
- Sumar `numero_cajas` del pedido, de la operación y de las cajas. Solo prevalece una fuente por flujo.
- Integrar colaboradores o nómina de Recursos Humanos. La revisión del registro manual vive en este módulo.
- Considerar pagado un corte al confirmarlo.
- Descontar un ajuste negativo del siguiente pago sin una aplicación explícita.
- Importar todo el histórico como pendiente. Sin fecha de inicio no hay importación.
- Contar la recepción de un resguardo como empaque.

## Identidad

La clave estable es la procedencia más el id de origen: `cedis_caja:{id}`, `cedis_bulto:{id}`, `cedis_cabecera:{pedido}`, `tienda_cumplimiento:{id}` o `manual:{aleatorio}`. Repetir la sincronización actualiza esa clave y no crea otra.

Quien empacó y quien cobra son datos distintos. El reparto no copia el usuario del empaque.

La exclusión de una unidad ya cortada está en `comisiones_empaque_corte_unidades.unidad_id`, único, dentro de la transacción de confirmación. La pantalla no es la única barrera.

## Archivos y migración

Aplicada en MySQL de Sail: `database/migrations/2026_10_18_191000_create_comisiones_empaque.php`.

El primer intento falló: el índice único de participantes del reparto superaba 64 caracteres. Se eliminaron solo las tablas parciales de este módulo, se acortó el índice a `ce_reparto_part_uq` y la migración terminó. No se usó `migrate:fresh`.

Datos iniciales: áreas CEDIS y Tienda, tarifas de 1000 y 600 centavos desde 2026-01-01, configuración con `fecha_inicio` nula y zona `America/Mexico_City`. Los permisos quedaron en el catálogo, sin asignarse a roles.

Código nuevo:

- `app/Http/Controllers/GestionInterna/ComisionesEmpaqueController.php`
- `app/Models/ComisionesEmpaque/`
- `app/Services/ComisionesEmpaque/`
- `app/Support/ComisionesEmpaque/`
- `resources/js/Pages/GestionInterna/ComisionesEmpaque/Index.jsx`
- `tests/Feature/ComisionesEmpaque/ComisionesEmpaqueTest.php`
- `tests/Unit/ComisionesEmpaque/RepartirCentavosComisionEmpaqueTest.php`

También se tocó, solo para este módulo: `routes/web/modules/gestion-interna.php`, `resources/js/config/sidebarNavigation.js` y `resources/js/utils/permisos.js`. La caché de rutas se regeneró para que la entrada exista.

Entrada: Gestión interna → Comisiones de empaque. Pestañas Acumulado, Cortes, Pagos y Configuración. Preparar corte es una vista amplia del acumulado.

## Pruebas ejecutadas

Sail, SQLite en memoria. No es la suite completa de pedidos, empaques ni del reporte de pagos.

- `ComisionesEmpaqueTest`: 15 pruebas. Caja CEDIS sin tomar el costo de envío, tienda sin pesaje, desglose incompleto, bolsas, salida del día 14, pago parcial y reintento, segunda confirmación, consolidación, complemento, cancelación sobre corte, reempaque, histórico e incorporación tardía, resguardo en ambas áreas, registro manual y permisos de exportación. La de permisos falló primero porque la caché de rutas no incluía el módulo; después de regenerarla, pasó.
- `RepartirCentavosComisionEmpaqueTest`: 2 pruebas. Residuo mayor y empate por id menor. Pasaron.
- `npm run build`: terminó y publicó el manifiesto.

No se volvió a correr la suite de pedidos, empaques ni del reporte de pagos. Esas rutas no se modificaron.

## Verificación manual

No hubo recorrido autenticado en el navegador. No se comprobó el foco, la tabla en móvil ni un corte real desde la pantalla. Las pruebas HTTP y de servicio no sustituyen esa revisión.

Sí quedó aplicada la migración en la base de trabajo y la ruta `gestion-interna/comisiones-empaque` responde en el listado de rutas.

## Compatibilidad, riesgos y reversión

Pedidos, empaque, pesaje, confirmación, cancelación, envío y el reporte de pagos siguen en sus servicios. Este módulo solo lee esas tablas al pulsar Actualizar. Una falla de la sincronización no cambia esos registros.

Riesgo: la fecha de inicio sigue vacía, así que Actualizar no trae empaques hasta definirla. Las tarifas semilla aplican desde 2026-01-01; un empaque anterior a esa fecha, pero posterior a la fecha de inicio, queda sin tarifa. El resguardo cuenta dos veces mientras exista la excepción. Quien no tenga áreas asignadas no ve ninguna. El acceso global requiere el rol de administrador o el permiso `ver_todas`.

Reversión: `migrate` down de `2026_10_18_191000_create_comisiones_empaque` elimina las tablas de este módulo y los permisos del catálogo. No borra pedidos ni empaques. Hay que quitar también la entrada de navegación, los permisos de `permisos.js` y regenerar la caché de rutas.

## Pendiente

- Recorrer en navegador Acumulado, clasificación, corte, pago y configuración con una sesión que tenga los permisos.
- Asignar esos permisos a quien vaya a operar el módulo. El catálogo ya los tiene; ningún rol los recibió en la migración.
- Definir la fecha de inicio real antes de la primera sincronización.
- Cuando la operación lo precise, sustituir la excepción de resguardo que hoy cuenta en CEDIS y en tienda.
- La sincronización recorre desde la fecha de inicio en cada Actualizar. Si ese rango crece, el siguiente paso es una marca de agua; no está hecha.
- No hay calendario automático de quincena. El intervalo del corte se captura con fechas.
- El alcance vacío ya no abre todas las áreas. Hace falta asignar Tienda o CEDIS, o conceder `ver_todas` de forma explícita.
