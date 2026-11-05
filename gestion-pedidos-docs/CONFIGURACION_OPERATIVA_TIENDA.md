# Configuración operativa y consulta por sucursal

Decisión del usuario e implementación del 4 de noviembre de 2026. Esta referencia actualiza la entrada inicial de MD descrita en documentos anteriores.

- La captura inicial conserva dos opciones: Revisar y apartar en tienda / Consultar disponibilidad en CEDIS. Mismo día se solicita con el checkbox de transferencia después del cierre vigente de la consulta. No se muestra un desglose del proceso ni restricciones internas de departamentos. Las solicitudes MD guardadas anteriormente siguen siendo compatibles.
- La consulta en Tienda muestra siempre el selector de sucursal habilitada, aunque haya solo una. El identificador conserva el almacén de revisión real. El nombre muestra sucursal y almacén para distinguirlos de la sucursal posterior de recolección.
- Catálogos → Almacenes conserva sus capacidades existentes: activo, visible en pedidos, tipo PDV y permite salida/surtido. Un almacén solo destino no recibe consultas de apartado. Se excluyen sucursales inactivas; se conserva compatibilidad con almacenes sin sucursal asignada. La lista piloto de almacenes habilitados de Preparación Tienda continúa restringiendo cuando no está vacía. Para habilitar City Center en el futuro, configurar su almacén PDV y esa lista cuando corresponda. Esta entrega no activa City Center ni modifica sus registros o la ramificación de traslado T-03.
- Administración → Configuración operativa usa el permiso existente `configuracion_sistema.gestionar`. Guarda con validación y auditoría en la misma tabla de configuración: zona horaria, recordatorios, cierre, resguardo general y prórroga VIP reutilizan sus claves; las alertas reutilizan `control_pedidos.plazos_retraso` y su servicio existente.
- `control_pedidos.tienda.operacion` contiene días de MD y corte, días de apartado, vencimiento ordinario y apertura/cierre del sábado, y días naturales de seguimiento de transferencia. Valores iniciales: MD lun–vie 13:00; apartado lun–sáb, cierre ordinario 17:00 y sábado 09:00–13:00; transferencia 20 días desde remisión. No hay migraciones ni cambios automáticos de datos del catálogo.
- Los validadores de cobertura/captura, el primer reporte de transferencia, la disponibilidad operativa MD y el plazo T-01 consumen los parámetros. El primer reporte guardado no se recalifica al editar horarios. Las nuevas remisiones registran el plazo de seguimiento aplicado; reemplazarlas lo conserva. Registros antiguos sin ese plazo usan el ajuste vigente. El plazo genera gestión, nunca liberación de mercancía pagada.
- La zona horaria corresponde a Preparación Tienda; las alertas generales de empaque/recolección conservan la zona de la aplicación y sus reglas previas. Los recordatorios y la evaluación periódica continúan mediante los trabajos programados existentes; no se crean otros cron.

## Continuidad

Entradas: `ConfiguracionOperativaController`, `ConfiguracionOperativaTienda`, `Admin/ConfiguracionOperativa/Index.jsx`, `DestinoRevisionRegistrarPedidos`, `FormularioRegistrarTienda`, `ContinuidadTienda` e `ImportesPagoTienda`.

Comprobación dirigida: 35 pruebas de servidor aprobadas (220 aserciones) y 11 pruebas de interfaz aprobadas. Incluyen permisos del nuevo submódulo, rechazo atómico de configuración inválida, aplicación real de días/cortes configurados, selección por capacidad e inactividad de sucursal, regresión de transferencia, custodia pagada y reporte original. Se aislaron configuración y rutas de pruebas mediante variables transmitidas al contenedor, con SQLite en memoria y caché array.

Build publicado una vez, sin EACCES, y rutas actualizadas. Formato PHP revisado y diferencias sin errores de espacios. No hay migraciones ni dependencias nuevas.

Revisión renderizada del componente real de Configuración operativa con el build publicado y datos ficticios: 1440×1000 y 390×844, claro y oscuro. Cuatro capturas y comprobación sin errores JS ni desbordamientos, conservadas en [referencias](../resources/design-references/configuracion-operativa/). Se inspeccionaron visualmente escritorio claro y móvil oscuro; composición, controles y acción de guardar conservan el tema GELIA. Reproducción: `./vendor/bin/sail node scripts/qa/tienda-recorrido/verificar-configuracion-operativa.mjs`. El visor no inicia sesión ni guarda datos; mensajes, audio y conexiones en tiempo real quedan fuera de su alcance. La captura de consulta y el recorrido real entre usuarios siguen pendientes de revisión autenticada.

Se consultó el catálogo real sin modificarlo: Carmen Cadena tiene código PDV y capacidad de surtido; City Center es solo destino, oculto en pedidos y actualmente tipo General. Para recibir consultas en el futuro requiere tipo PDV, visibilidad y capacidad de surtido; respetar la lista piloto si se configura.

No se hizo commit ni push. Incluir archivos nuevos y referencias al subir los cambios.
