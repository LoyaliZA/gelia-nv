/** @deprecated Use permisoDePlantilla — mantiene compatibilidad */
export function permisoHeredado(permisoName, rolesAsignados, roles) {
    return permisoDePlantilla(permisoName, rolesAsignados, roles);
}

export const DESCRIPCIONES_PERMISOS = {
    'solicitudes.ver_listado': 'Permite acceder al listado general de solicitudes.',
    'solicitudes.ver_detalle': 'Permite ver el detalle completo de una solicitud.',
    'solicitudes.crear': 'Permite crear nuevas solicitudes de proceso.',
    'solicitudes.editar': 'Permite editar solicitudes en estado editable.',
    'solicitudes.verificar': 'Permite marcar solicitudes como verificadas (auxiliar).',
    'solicitudes.reportar': 'Permite aprobar procesos y reportar errores.',
    'solicitudes.emitir_consulta': 'Permite consultar TAG o lista sobre una solicitud respondida.',
    'solicitudes.responder_consulta': 'Permite recibir y responder consultas de TAG y lista enviadas por el vendedor.',
    'solicitudes.confirmar_pago': 'Permite confirmar el pago de una solicitud (encargada).',
    'solicitudes.solicitar_cancelacion': 'Permite solicitar la cancelación de folios propios activos.',
    'solicitudes.cancelar': 'Permite confirmar cancelaciones pendientes y revertir cambios al cliente.',
    'solicitudes.confirmar_cambio_lista': 'Permite confirmar el ajuste de lista tras alerta de pago insuficiente.',
    'solicitudes.eliminar': 'Permite eliminar registros de solicitudes con respaldo en auditoría.',
    'solicitudes.eliminadas': 'Permite consultar solicitudes eliminadas (papelera).',
    'solicitudes.exportar': 'Permite exportar reportes de solicitudes financieras en PDF, Excel y CSV.',
    'solicitudes.consultar': 'Permiso legado de consulta TAG/lista (usar emitir/responder consulta).',
    'clientes.ver': 'Permite consultar el catálogo de clientes.',
    'clientes.crear': 'Permite registrar clientes manualmente.',
    'clientes.carga_masiva': 'Permite importar clientes de forma masiva.',
    'mis_clientes.gestionar': 'Permite ver la cartera propia y registrar clientes en Mis Clientes.',
    'clientes.correccion_emergencia': 'Permite corregir número y nombre intercambiados o en conflicto de unicidad.',
    'clientes.direcciones.ver': 'Permite consultar las direcciones de envío normalizadas de un cliente.',
    'clientes.direcciones.crear': 'Permite crear direcciones de envío para un cliente.',
    'clientes.direcciones.editar': 'Permite editar o versionar direcciones de envío.',
    'clientes.direcciones.desactivar': 'Permite desactivar o reactivar direcciones de envío.',
    'clientes.direcciones.ver_historial': 'Permite consultar el historial de versiones de direcciones.',
    'clientes.direcciones.revisar_solicitudes': 'Permite revisar y aprobar solicitudes públicas de dirección.',
    'clientes.direcciones.generar_enlace': 'Permite generar y revocar enlaces seguros del formulario de direcciones.',
    'clientes.limpieza': 'Permiso legado; usar funciones.limpieza_clientes.',
    'configuracion.ver_auditoria': 'Permite consultar la bitácora de auditoría de solicitudes.',
    'usuarios.gestionar': 'Permite administrar usuarios del sistema.',
    'usuarios.generar_permisos': 'Permite asignar permisos a otros usuarios.',
    'usuarios.archivar': 'Permite archivar usuarios del sistema.',
    'catalogos.gestionar': 'Permite administrar catálogos globales del sistema.',
    'catalogos.comisiones.ver': 'Permite consultar el catálogo de comisiones.',
    'catalogos.comisiones.gestionar': 'Permite editar el catálogo de comisiones.',
    'comisiones.gestionar': 'Permite administrar tabuladores de comisiones.',
    'listados.ver': 'Permite ver listados de productos para resurtido.',
    'listados.crear': 'Permite crear listados de productos.',
    'listados.editar': 'Permite editar listados de productos.',
    'listados.eliminar': 'Permite eliminar listados de productos.',
    'listados.configurar_porcentajes': 'Permite configurar porcentajes globales de listados.',
    'listados.guardar_generado': 'Permite guardar archivos Excel generados en el historial.',
    'listados.enviar': 'Permite enviar listados por correo y configurar destinatarios por tipo.',
    'listados.visualizar': 'Permite ver el historial de listados guardados y descargarlos.',
    'activos.ver': 'Permite ver el listado y detalle de activos.',
    'activos.crear': 'Permite registrar nuevos activos.',
    'activos.editar': 'Permite editar activos existentes.',
    'activos.asignar': 'Permite asignar activos a usuarios Gelia.',
    'activos.transferir': 'Permite transferir activos entre departamentos.',
    'activos.cambiar_estado': 'Permite cambiar el estado del activo (mantenimiento, baja).',
    'activos.ver_todos': 'Permite ver activos de todos los departamentos.',
    'activos.configurar_tipos': 'Permite gestionar el catálogo de tipos de activo.',
    'activos.exportar': 'Permite exportar activos a Excel.',
    'personalizacion.gestionar': 'Permite gestionar temas y personalización visual.',
    'facturas.ver_listado': 'Permite acceder al módulo de solicitudes de facturas.',
    'facturas.crear': 'Permite crear y reparar solicitudes de factura propias (comprobante de pago).',
    'facturas.responder': 'Permite emitir factura (PDF/XML) en solicitudes pendientes.',
    'facturas.reportar_error': 'Permite reportar error en solicitudes de factura pendientes.',
    'facturas.verificar': 'Permite marcar solicitudes de factura como verificadas.',
    'facturas.eliminar': 'Permite eliminar solicitudes de factura con auditoría.',
    'facturas.gestionar_datos_fiscales': 'Permite administrar datos fiscales de clientes desde el módulo de facturas.',
    'facturas.exportar': 'Permite exportar el listado de solicitudes de factura.',
    'traspasos.ver_listado': 'Permite acceder al módulo de solicitudes de traspaso.',
    'traspasos.crear': 'Permite crear solicitudes de traspaso de piezas.',
    'traspasos.responder': 'Permite registrar el folio y captura del traspaso generado.',
    'traspasos.reportar_error': 'Permite reportar error en solicitudes de traspaso pendientes.',
    'traspasos.verificar': 'Permite marcar solicitudes de traspaso como verificadas.',
    'traspasos.monitorear_alertas': 'Recibe todas las alertas de traspasos (estación de bocina / designado).',
    'traspasos.reporte_dia': 'Permite generar el reporte de traspasos planificados para el día siguiente.',
    'traspasos.eliminar': 'Permite eliminar solicitudes de traspaso con auditoría.',
    'cancelaciones_cotizaciones.ver_listado': 'Permite acceder al módulo de cancelaciones y cotizaciones.',
    'cancelaciones_cotizaciones.crear': 'Permite crear solicitudes de cancelación de remisión/pedido o cotización sobre pedido.',
    'cancelaciones_cotizaciones.reportar': 'Permite aprobar o reportar errores en solicitudes operativas.',
    'cancelaciones_cotizaciones.verificar': 'Permite marcar solicitudes operativas como verificadas.',
    'cancelaciones_cotizaciones.solicitar_cancelacion': 'Permite solicitar la cancelación de folios operativos propios.',
    'cancelaciones_cotizaciones.cancelar': 'Permite confirmar cancelaciones de folios operativos pendientes.',
    'cancelaciones_cotizaciones.exportar': 'Permite exportar el listado de cancelaciones y cotizaciones.',
    'cancelaciones_cotizaciones.eliminar': 'Permite eliminar solicitudes operativas con auditoría.',
    'control_pedidos.ver_listado': 'Permite acceder al módulo de control de pedidos (vista vendedora).',
    'control_pedidos.crear': 'Permite crear y enviar pedidos BMA.',
    'control_pedidos.editar': 'Permite editar pedidos en borrador o rechazados.',
    'control_pedidos.eliminar': 'Permite eliminar pedidos en borrador.',
    'control_pedidos.exportar': 'Permite exportar el listado de pedidos en CSV.',
    'control_pedidos.ver_detalle': 'Permite ver el detalle completo de un pedido.',
    'control_pedidos.auditar': 'Permite acceder al panel de auditoría de pedidos (auxiliar).',
    'control_pedidos.cedis': 'Permite acceder al panel de control de empaque en CEDIS.',
    'control_pedidos.delegado': 'Permite acceder al panel de delegado (asignación de guías y PDF).',
    'control_pedidos.configurar_catalogos': 'Permite administrar catálogos del módulo de control de pedidos.',
    'control_pedidos.direccion.seleccionar': 'Permite seleccionar una dirección normalizada en pedidos BMA.',
    'control_pedidos.direccion.cambiar': 'Permite cambiar la dirección de un pedido con auditoría.',
    'control_pedidos.direccion.cambiar_despues_remision': 'Permite cambiar dirección después de que exista remisión.',
    'control_pedidos.direccion.cambiar_despues_guia': 'Permite cambiar dirección e invalidar guía existente.',
    'control_pedidos.direccion.usar_manual': 'Permite capturar una dirección manual excepcional en un pedido.',
    'api_externa.gestionar': 'Permite administrar la API externa, aplicaciones, permisos y documentación.',
    'api_externa.ver_auditoria': 'Permite consultar la bitácora de uso de la API externa.',
    'funciones.limpieza_clientes': 'Permite acceder a la herramienta operativa de Limpieza de Clientes.',
    'funciones.asistencia': 'Permite acceder a la herramienta de Asistencia.',
    'funciones.avisos': 'Permite acceder a Avisos de mercancía.',
    'funciones.gastos': 'Permite acceder a Depuración de gastos.',
    'funciones.limpieza_archivos': 'Permite acceder a Limpieza de archivos.',
    'funciones.transacciones': 'Permite acceder a Depuración de transacciones.',
    'funciones.plantilla_bellaroma': 'Permiso legado de plantilla Bellaroma (usar plantilla_pedidos).',
    'contabilidad.ver': 'Permite acceder al módulo de contabilidad Bellaroma.',
    'contabilidad.pedidos.crear': 'Permite registrar pedidos manualmente en contabilidad.',
    'contabilidad.pedidos.editar': 'Permite editar pedidos de contabilidad no bloqueados.',
    'contabilidad.pedidos.eliminar': 'Permite eliminar pedidos de contabilidad no bloqueados.',
    'contabilidad.retiros.confirmar': 'Permite confirmar retiros bancarios individuales o por lote.',
    'contabilidad.plataformas.configurar': 'Permite configurar comisiones y frecuencia de pago de plataformas.',
    'contabilidad.importar': 'Permite importar listas de precios e histórico de pedidos.',
    'contabilidad.exportar': 'Permite exportar reportes de contabilidad en Excel y PDF.',
    'contabilidad.historial.editar_emergencia': 'Permite ediciones de emergencia en historial de lotes pagados.',
    'almacenes.ver': 'Permiso legacy del grupo Almacenes. No otorga acceso a Productos, Inventarios ni Costos; use los permisos específicos de cada submódulo.',
    'almacenes.productos.ver': 'Permite acceder a la vista de catálogo maestro de productos (SKU, descripción, marca, categoría y código de barras).',
    'almacenes.productos.gestionar': 'Permite crear, editar y eliminar productos en el catálogo maestro.',
    'almacenes.inventarios.ver': 'Permite consultar existencias, apartados, disponible, tránsitos, mínimos/máximos y ubicación por almacén.',
    'almacenes.inventarios.gestionar': 'Permite crear, editar y eliminar registros de inventario por producto y almacén.',
    'almacenes.inventarios.importar': 'Permite importar inventarios de forma masiva mediante el asistente de carga.',
    'almacenes.costos.ver': 'Permite consultar costo, costo de reposición y precio de venta por producto y almacén.',
    'almacenes.costos.gestionar': 'Permite crear, editar y eliminar costos y precios por producto y almacén.',
    'almacenes.costos.importar': 'Permite importar costos y precios de forma masiva (cuando esté habilitada la carga).',
    'entregas.cotizar': 'Permite usar el cotizador de entregas.',
    'entregas.configurar_zonas': 'Permite configurar el mapa logístico y zonas de entrega.',
    'cobranza.ver': 'Permite acceder al módulo Credibox / cobranza.',
    'cobranza.importar_reporte': 'Permite importar reportes de cobranza.',
    'cobranza.ejecutar_llamadas': 'Permite ejecutar llamadas de cobranza.',
    'cobranza.editar_credito': 'Permite editar créditos de clientes en cobranza.',
    'cobranza.confirmar_pago': 'Permite confirmar pagos en cobranza.',
    'cobranza.ver_bitacora': 'Permite consultar la bitácora de cobranza.',
    'cobranza.recibir_alertas': 'Permite recibir alertas de cobranza.',
    'cobranza.configurar_alertas': 'Permite configurar alertas de cobranza.',
    'cobranza.reparar_fecha': 'Permite reparar fechas en registros de cobranza.',
    'cobranza.reportes': 'Permite generar reportes de cobranza.',
    'cobranza.recalcular_creditos': 'Permite recalcular créditos en cobranza.',
    'ejercicio_escalonamiento.ver': 'Permite acceder al ejercicio de escalonamiento.',
    'woocommerce.ver': 'Permite ver productos vinculados a WooCommerce.',
    'woocommerce.sincronizar': 'Permite sincronizar precios/productos con WooCommerce.',
    'woocommerce.configurar': 'Permite configurar la vinculación WooCommerce.',
    'woocommerce.auditoria': 'Permite consultar la auditoría de WooCommerce.',
    'woocommerce.emergencia': 'Permite acciones de emergencia en WooCommerce.',
    'plantilla_pedidos.ver': 'Permite acceder a Plantilla de Pedidos.',
    'plantilla_pedidos.generar': 'Permite generar plantillas de pedidos.',
    'plantilla_pedidos.descargar': 'Permite descargar plantillas generadas.',
    'plantilla_pedidos.visualizar': 'Permite visualizar plantillas generadas.',
    'plantilla_pedidos.ver_programadas': 'Permite ver plantillas programadas.',
    'plantilla_pedidos.configurar': 'Permite configurar Plantilla de Pedidos.',
    'plantilla_pedidos.eliminar': 'Permite eliminar plantillas de pedidos.',
    'gestion_interna.directorio.ver': 'Permite consultar el directorio interno.',
    'gestion_interna.directorio.gestionar': 'Permite administrar el directorio interno.',
    'configuracion_sistema.gestionar': 'Permite administrar la configuración del sistema (variables / entorno).',
    'sistema.auditorias.ver': 'Permite consultar auditorías del sistema.',
    'sistema.auditorias.accesos.ver': 'Permite consultar la auditoría de accesos.',
    'soporte.reportar': 'Permite reportar tickets de soporte.',
    'soporte.gestionar': 'Permite atender tickets en el dashboard de soporte.',
    'soporte.administrar': 'Permite administrar SLA y catálogos de soporte.',
    'mensajeria.monitorear': 'Permite monitorear la mensajería del sistema.',
    'mensajeria.eliminar': 'Permite eliminar mensajes de mensajería.',
    'rh.ver': 'Permite acceder al módulo de Recursos Humanos.',
    'rh.colaboradores.crear': 'Permite dar de alta colaboradores.',
    'rh.colaboradores.editar': 'Permite editar colaboradores.',
    'rh.colaboradores.vincular_usuario': 'Permite vincular un colaborador con un usuario del sistema.',
    'rh.horas_extra.ver': 'Permite consultar horas extra.',
    'rh.horas_extra.crear': 'Permite registrar horas extra.',
    'rh.horas_extra.editar': 'Permite editar horas extra.',
    'rh.incidencias.ver': 'Permite consultar incidencias RH.',
    'rh.incidencias.crear': 'Permite crear incidencias RH.',
    'rh.incidencias.editar': 'Permite editar incidencias RH.',
    'rh.incidencias.aplicar': 'Permite aplicar incidencias RH.',
    'rh.incidencias.gerente.ver': 'Permite ver incidencias propias como gerente.',
    'rh.incidencias.gerente.crear': 'Permite crear incidencias propias como gerente.',
    'rh.deducciones.ver': 'Permite consultar deducciones.',
    'rh.deducciones.crear': 'Permite crear deducciones.',
    'rh.deducciones.editar': 'Permite editar deducciones.',
    'rh.deducciones.aplicar': 'Permite aplicar deducciones.',
    'rh.prestamos.ver': 'Permite consultar préstamos.',
    'rh.prestamos.crear': 'Permite crear préstamos.',
    'rh.prestamos.editar': 'Permite editar préstamos.',
    'rh.prestamos.detener': 'Permite detener préstamos activos.',
    'rh.prestamos.generar': 'Permite generar movimientos de préstamos.',
    'rh.salidas_personales.ver': 'Permite consultar salidas personales.',
    'rh.salidas_personales.crear': 'Permite registrar salidas personales.',
    'rh.salidas_personales.editar': 'Permite editar salidas personales.',
    'rh.salidas_personales.eliminar': 'Permite eliminar salidas personales.',
    'rh.salidas_personales.sellar': 'Permite sellar salidas personales.',
    'rh.banco_tiempo.ver': 'Permite consultar banco de tiempo.',
    'rh.banco_tiempo.crear': 'Permite registrar movimientos de banco de tiempo.',
    'rh.banco_tiempo.editar': 'Permite editar banco de tiempo.',
    'rh.banco_tiempo.saldar': 'Permite saldar banco de tiempo.',
    'rh.banco_tiempo.eliminar': 'Permite eliminar movimientos de banco de tiempo.',
    'rh.periodo_pago.ver': 'Permite consultar periodos de pago / nómina.',
    'rh.recibos.ver': 'Permite consultar recibos de nómina.',
    'rh.recibos.generar': 'Permite generar recibos de nómina.',
    'rh.comisiones_auditor.ver': 'Permite consultar comisiones de auditor.',
    'rh.configurar': 'Permite configurar parámetros de RH.',
    'rh.catalogos.puestos': 'Permite administrar el catálogo de puestos.',
    'rh.catalogos.bonos': 'Permite administrar el catálogo de bonos.',
    'rh.catalogos.tipos_faltas': 'Permite administrar el catálogo de tipos de faltas.',
    'rh.catalogos.incidencias_generales': 'Permite administrar el catálogo de incidencias generales.',
};

/** Etiqueta corta en la UI de permisos (evita repetir solo «productos» / «inventarios» / «costos»). */
export const ETIQUETAS_PERMISOS = {
    'almacenes.ver': 'almacenes · grupo',
    'control_pedidos.cedis': 'gestionar cedis',
    'control_pedidos.delegado': 'asignar / actualizar guías',
    'control_pedidos.configurar_catalogos': 'configurar catálogos',
    'control_pedidos.auditar': 'auditar pedidos',
};

/** Permisos de excepción / estados avanzados (resalte en permisos específicos). */
export const PERMISOS_EXCEPCION = new Set([
    'control_pedidos.direccion.cambiar_despues_remision',
    'control_pedidos.direccion.cambiar_despues_guia',
]);

export function esPermisoExcepcion(permisoName) {
    return PERMISOS_EXCEPCION.has(permisoName);
}

/**
 * Submódulos UI por módulo Spatie (alineados al sidebar / paneles reales).
 * Los permisos no listados caen en un bloque «Otros» del mismo módulo.
 * Si el módulo no tiene mapa, se agrupa por entidad automáticamente.
 */
export const SUBMODULOS_UI_POR_MODULO = {
    control_pedidos: [
        {
            id: 'registrar',
            label: 'Registrar pedidos',
            descripcion: 'Gestión de pedidos: listado, alta, edición y direcciones en el pedido',
            permisos: [
                'control_pedidos.ver_listado',
                'control_pedidos.ver_detalle',
                'control_pedidos.crear',
                'control_pedidos.editar',
                'control_pedidos.eliminar',
                'control_pedidos.exportar',
                'control_pedidos.direccion.seleccionar',
                'control_pedidos.direccion.cambiar',
                'control_pedidos.direccion.usar_manual',
                'control_pedidos.direccion.cambiar_despues_remision',
                'control_pedidos.direccion.cambiar_despues_guia',
            ],
        },
        {
            id: 'auditar',
            label: 'Auditar pedidos',
            descripcion: 'Panel de auditoría (validar, remisión, aprobar / rechazar)',
            permisos: ['control_pedidos.auditar'],
        },
        {
            id: 'cedis',
            label: 'Control pedidos CEDIS',
            descripcion: 'Empaque y control operativo en CEDIS',
            permisos: ['control_pedidos.cedis'],
        },
        {
            id: 'delegado',
            label: 'Actualizar guías',
            descripcion: 'Asignación de guías, PDF e importación (delegado)',
            permisos: ['control_pedidos.delegado'],
        },
        {
            id: 'catalogos',
            label: 'Catálogos',
            descripcion: 'Administrar catálogos del módulo (orígenes, paqueterías, etc.)',
            permisos: ['control_pedidos.configurar_catalogos'],
        },
    ],
    solicitudes: [
        {
            id: 'listado',
            label: 'Listado y detalle',
            descripcion: 'Acceso al listado y detalle de solicitudes',
            permisos: ['solicitudes.ver_listado', 'solicitudes.ver_detalle', 'solicitudes.eliminadas'],
        },
        {
            id: 'captura',
            label: 'Captura',
            descripcion: 'Alta y edición de solicitudes',
            permisos: ['solicitudes.crear', 'solicitudes.editar'],
        },
        {
            id: 'flujo',
            label: 'Flujo operativo',
            descripcion: 'Verificar, reportar, consultas TAG/lista, pago y cancelación',
            permisos: [
                'solicitudes.verificar',
                'solicitudes.reportar',
                'solicitudes.emitir_consulta',
                'solicitudes.responder_consulta',
                'solicitudes.confirmar_pago',
                'solicitudes.solicitar_cancelacion',
                'solicitudes.cancelar',
                'solicitudes.confirmar_cambio_lista',
                'solicitudes.consultar',
            ],
        },
        {
            id: 'admin',
            label: 'Administración',
            descripcion: 'Eliminar y exportar reportes',
            permisos: ['solicitudes.eliminar', 'solicitudes.exportar'],
        },
    ],
    cancelaciones_cotizaciones: [
        {
            id: 'panel',
            label: 'Cancelaciones y cotizaciones',
            descripcion: 'Listado, flujo operativo, exportar y eliminar',
            permisos: [
                'cancelaciones_cotizaciones.ver_listado',
                'cancelaciones_cotizaciones.crear',
                'cancelaciones_cotizaciones.reportar',
                'cancelaciones_cotizaciones.verificar',
                'cancelaciones_cotizaciones.solicitar_cancelacion',
                'cancelaciones_cotizaciones.cancelar',
                'cancelaciones_cotizaciones.exportar',
                'cancelaciones_cotizaciones.eliminar',
            ],
        },
    ],
    mis_clientes: [
        {
            id: 'cartera',
            label: 'Mis clientes',
            descripcion: 'Cartera propia y altas de clientes',
            permisos: ['mis_clientes.gestionar'],
        },
    ],
    clientes: [
        {
            id: 'catalogo',
            label: 'Base de clientes',
            descripcion: 'Catálogo admin: consulta, altas y correcciones',
            permisos: [
                'clientes.ver',
                'clientes.crear',
                'clientes.carga_masiva',
                'clientes.correccion_emergencia',
                'clientes.limpieza',
            ],
        },
        {
            id: 'direcciones',
            label: 'Direcciones',
            descripcion: 'Panel de direcciones de envío (bajo Gestión de pedidos)',
            permisos: [
                'clientes.direcciones.ver',
                'clientes.direcciones.crear',
                'clientes.direcciones.editar',
                'clientes.direcciones.desactivar',
                'clientes.direcciones.ver_historial',
                'clientes.direcciones.revisar_solicitudes',
                'clientes.direcciones.generar_enlace',
            ],
        },
    ],
    entregas: [
        {
            id: 'cotizador',
            label: 'Cotizar entregas',
            descripcion: 'Cotizador de entregas',
            permisos: ['entregas.cotizar'],
        },
        {
            id: 'mapa',
            label: 'Mapa logístico',
            descripcion: 'Configuración de zonas y mapa',
            permisos: ['entregas.configurar_zonas'],
        },
    ],
    contabilidad: [
        {
            id: 'acceso',
            label: 'Acceso',
            descripcion: 'Entrar al módulo de contabilidad',
            permisos: ['contabilidad.ver'],
        },
        {
            id: 'pedidos',
            label: 'Pedidos',
            descripcion: 'Pedidos manuales de contabilidad',
            permisos: [
                'contabilidad.pedidos.crear',
                'contabilidad.pedidos.editar',
                'contabilidad.pedidos.eliminar',
            ],
        },
        {
            id: 'retiros',
            label: 'Retiros',
            descripcion: 'Confirmación de retiros bancarios',
            permisos: ['contabilidad.retiros.confirmar'],
        },
        {
            id: 'plataformas',
            label: 'Plataformas',
            descripcion: 'Comisiones y frecuencia de pago',
            permisos: ['contabilidad.plataformas.configurar'],
        },
        {
            id: 'import_export',
            label: 'Importar / exportar',
            descripcion: 'Cargas y reportes',
            permisos: ['contabilidad.importar', 'contabilidad.exportar'],
        },
        {
            id: 'historial',
            label: 'Historial',
            descripcion: 'Ediciones de emergencia en lotes pagados',
            permisos: ['contabilidad.historial.editar_emergencia'],
        },
    ],
    facturas: [
        {
            id: 'solicitudes',
            label: 'Solicitudes de factura',
            descripcion: 'Flujo de facturación',
            permisos: [
                'facturas.ver_listado',
                'facturas.crear',
                'facturas.responder',
                'facturas.reportar_error',
                'facturas.verificar',
                'facturas.eliminar',
                'facturas.exportar',
            ],
        },
        {
            id: 'datos_fiscales',
            label: 'Datos fiscales',
            descripcion: 'Administrar datos fiscales de clientes',
            permisos: ['facturas.gestionar_datos_fiscales'],
        },
    ],
    traspasos: [
        {
            id: 'listado',
            label: 'Solicitudes de traspaso',
            descripcion: 'Flujo de traspasos de piezas',
            permisos: [
                'traspasos.ver_listado',
                'traspasos.crear',
                'traspasos.responder',
                'traspasos.reportar_error',
                'traspasos.verificar',
                'traspasos.monitorear_alertas',
                'traspasos.reporte_dia',
                'traspasos.eliminar',
            ],
        },
    ],
    cobranza: [
        {
            id: 'panel',
            label: 'Credibox',
            descripcion: 'Acceso al módulo de cobranza',
            permisos: ['cobranza.ver'],
        },
        {
            id: 'operacion',
            label: 'Operación',
            descripcion: 'Importar, llamadas, crédito y pagos',
            permisos: [
                'cobranza.importar_reporte',
                'cobranza.ejecutar_llamadas',
                'cobranza.editar_credito',
                'cobranza.confirmar_pago',
            ],
        },
        {
            id: 'admin_tools',
            label: 'Herramientas admin',
            descripcion: 'Bitácora, alertas, reparación y reportes',
            permisos: [
                'cobranza.ver_bitacora',
                'cobranza.recibir_alertas',
                'cobranza.configurar_alertas',
                'cobranza.reparar_fecha',
                'cobranza.reportes',
                'cobranza.recalcular_creditos',
            ],
        },
    ],
    listados: [
        {
            id: 'crud',
            label: 'Listados',
            descripcion: 'Ver, crear, editar y eliminar listados',
            permisos: ['listados.ver', 'listados.crear', 'listados.editar', 'listados.eliminar'],
        },
        {
            id: 'config',
            label: 'Configuración',
            descripcion: 'Porcentajes globales',
            permisos: ['listados.configurar_porcentajes'],
        },
        {
            id: 'historial',
            label: 'Historial y envío',
            descripcion: 'Guardar, enviar y visualizar generados',
            permisos: ['listados.guardar_generado', 'listados.enviar', 'listados.visualizar'],
        },
    ],
    funciones: [
        {
            id: 'limpieza_clientes',
            label: 'Limpieza de clientes',
            descripcion: 'Herramienta operativa de limpieza',
            permisos: ['funciones.limpieza_clientes'],
        },
        {
            id: 'asistencia',
            label: 'Asistencia',
            descripcion: 'Herramienta de asistencia',
            permisos: ['funciones.asistencia'],
        },
        {
            id: 'avisos',
            label: 'Avisos',
            descripcion: 'Avisos de mercancía',
            permisos: ['funciones.avisos'],
        },
        {
            id: 'gastos',
            label: 'Gastos',
            descripcion: 'Depuración de gastos',
            permisos: ['funciones.gastos'],
        },
        {
            id: 'limpieza_archivos',
            label: 'Limpieza de archivos',
            descripcion: 'Herramienta de limpieza de archivos',
            permisos: ['funciones.limpieza_archivos'],
        },
        {
            id: 'transacciones',
            label: 'Transacciones',
            descripcion: 'Depuración de transacciones',
            permisos: ['funciones.transacciones'],
        },
        {
            id: 'legado',
            label: 'Legado',
            descripcion: 'Permisos antiguos en desuso',
            permisos: ['funciones.plantilla_bellaroma'],
        },
    ],
    ejercicio_escalonamiento: [
        {
            id: 'acceso',
            label: 'Ejercicio escalonamiento',
            descripcion: 'Acceso a la herramienta',
            permisos: ['ejercicio_escalonamiento.ver'],
        },
    ],
    woocommerce: [
        {
            id: 'productos',
            label: 'Productos',
            descripcion: 'Ver y sincronizar con WooCommerce',
            permisos: ['woocommerce.ver', 'woocommerce.sincronizar'],
        },
        {
            id: 'config',
            label: 'Configuración',
            descripcion: 'Ajustes de vinculación',
            permisos: ['woocommerce.configurar'],
        },
        {
            id: 'auditoria',
            label: 'Auditoría',
            descripcion: 'Bitácora de sincronización',
            permisos: ['woocommerce.auditoria'],
        },
        {
            id: 'emergencia',
            label: 'Emergencia',
            descripcion: 'Acciones excepcionales',
            permisos: ['woocommerce.emergencia'],
        },
    ],
    plantilla_pedidos: [
        {
            id: 'uso',
            label: 'Uso',
            descripcion: 'Ver, generar, descargar y visualizar',
            permisos: [
                'plantilla_pedidos.ver',
                'plantilla_pedidos.generar',
                'plantilla_pedidos.descargar',
                'plantilla_pedidos.visualizar',
                'plantilla_pedidos.ver_programadas',
            ],
        },
        {
            id: 'admin',
            label: 'Administración',
            descripcion: 'Configurar y eliminar plantillas',
            permisos: ['plantilla_pedidos.configurar', 'plantilla_pedidos.eliminar'],
        },
    ],
    rh: [
        {
            id: 'principal',
            label: 'RH · principal',
            descripcion: 'Dashboard y colaboradores',
            permisos: [
                'rh.ver',
                'rh.colaboradores.crear',
                'rh.colaboradores.editar',
                'rh.colaboradores.vincular_usuario',
            ],
        },
        {
            id: 'horas_extra',
            label: 'Horas extra',
            descripcion: 'Registro de horas extra',
            permisos: ['rh.horas_extra.ver', 'rh.horas_extra.crear', 'rh.horas_extra.editar'],
        },
        {
            id: 'deducciones',
            label: 'Deducciones e incidencias',
            descripcion: 'Incidencias RH y deducciones',
            permisos: [
                'rh.incidencias.ver',
                'rh.incidencias.crear',
                'rh.incidencias.editar',
                'rh.incidencias.aplicar',
                'rh.deducciones.ver',
                'rh.deducciones.crear',
                'rh.deducciones.editar',
                'rh.deducciones.aplicar',
            ],
        },
        {
            id: 'incidencias_gerente',
            label: 'Incidencias gerente',
            descripcion: 'Mis incidencias (vista gerente)',
            permisos: ['rh.incidencias.gerente.ver', 'rh.incidencias.gerente.crear'],
        },
        {
            id: 'prestamos',
            label: 'Préstamos',
            descripcion: 'Gestión de préstamos',
            permisos: [
                'rh.prestamos.ver',
                'rh.prestamos.crear',
                'rh.prestamos.editar',
                'rh.prestamos.detener',
                'rh.prestamos.generar',
            ],
        },
        {
            id: 'salidas',
            label: 'Salidas personales',
            descripcion: 'Registro y sello de salidas',
            permisos: [
                'rh.salidas_personales.ver',
                'rh.salidas_personales.crear',
                'rh.salidas_personales.editar',
                'rh.salidas_personales.eliminar',
                'rh.salidas_personales.sellar',
            ],
        },
        {
            id: 'banco_tiempo',
            label: 'Banco de tiempo',
            descripcion: 'Movimientos y saldo de banco de tiempo',
            permisos: [
                'rh.banco_tiempo.ver',
                'rh.banco_tiempo.crear',
                'rh.banco_tiempo.editar',
                'rh.banco_tiempo.saldar',
                'rh.banco_tiempo.eliminar',
            ],
        },
        {
            id: 'nomina',
            label: 'Nómina',
            descripcion: 'Periodo de pago, recibos y comisiones auditor',
            permisos: [
                'rh.periodo_pago.ver',
                'rh.recibos.ver',
                'rh.recibos.generar',
                'rh.comisiones_auditor.ver',
            ],
        },
        {
            id: 'configuracion',
            label: 'Configuración RH',
            descripcion: 'Parámetros y catálogos RH',
            permisos: [
                'rh.configurar',
                'rh.catalogos.puestos',
                'rh.catalogos.bonos',
                'rh.catalogos.tipos_faltas',
                'rh.catalogos.incidencias_generales',
            ],
        },
    ],
    activos: [
        {
            id: 'inventario',
            label: 'Inventario de activos',
            descripcion: 'CRUD, estados y exportación',
            permisos: [
                'activos.ver',
                'activos.crear',
                'activos.editar',
                'activos.cambiar_estado',
                'activos.ver_todos',
                'activos.exportar',
            ],
        },
        {
            id: 'asignaciones',
            label: 'Asignaciones',
            descripcion: 'Asignar y transferir activos',
            permisos: ['activos.asignar', 'activos.transferir'],
        },
        {
            id: 'tipos',
            label: 'Tipos de activo',
            descripcion: 'Catálogo de tipos',
            permisos: ['activos.configurar_tipos'],
        },
    ],
    gestion_interna: [
        {
            id: 'directorio',
            label: 'Directorio',
            descripcion: 'Directorio interno',
            permisos: ['gestion_interna.directorio.ver', 'gestion_interna.directorio.gestionar'],
        },
    ],
    almacenes: [
        {
            id: 'grupo',
            label: 'Grupo almacenes',
            descripcion: 'Permiso legacy de grupo (no abre submódulos por sí solo)',
            permisos: ['almacenes.ver'],
        },
        {
            id: 'productos',
            label: 'Productos',
            descripcion: 'Catálogo maestro de productos',
            permisos: ['almacenes.productos.ver', 'almacenes.productos.gestionar'],
        },
        {
            id: 'inventarios',
            label: 'Inventarios',
            descripcion: 'Existencias por almacén',
            permisos: [
                'almacenes.inventarios.ver',
                'almacenes.inventarios.gestionar',
                'almacenes.inventarios.importar',
            ],
        },
        {
            id: 'costos',
            label: 'Costos',
            descripcion: 'Costos y precios por producto/almacén',
            permisos: [
                'almacenes.costos.ver',
                'almacenes.costos.gestionar',
                'almacenes.costos.importar',
            ],
        },
    ],
    catalogos: [
        {
            id: 'globales',
            label: 'Catálogos globales',
            descripcion: 'Administración de catálogos del sistema',
            permisos: ['catalogos.gestionar'],
        },
        {
            id: 'comisiones_catalogo',
            label: 'Catálogo de comisiones',
            descripcion: 'Consulta y edición del catálogo de comisiones',
            permisos: ['catalogos.comisiones.ver', 'catalogos.comisiones.gestionar'],
        },
    ],
    comisiones: [
        {
            id: 'tabuladores',
            label: 'Tabuladores',
            descripcion: 'Administrar tabuladores de comisiones',
            permisos: ['comisiones.gestionar'],
        },
    ],
    usuarios: [
        {
            id: 'cuentas',
            label: 'Usuarios',
            descripcion: 'Administrar cuentas de usuario',
            permisos: ['usuarios.gestionar', 'usuarios.archivar'],
        },
        {
            id: 'enlaces',
            label: 'Enlaces y permisos',
            descripcion: 'Generar enlaces y asignar permisos',
            permisos: ['usuarios.generar_permisos'],
        },
    ],
    personalizacion: [
        {
            id: 'temas',
            label: 'Personalización',
            descripcion: 'Temas y apariencia visual',
            permisos: ['personalizacion.gestionar'],
        },
    ],
    configuracion: [
        {
            id: 'auditoria',
            label: 'Auditoría de solicitudes',
            descripcion: 'Bitácora de auditoría',
            permisos: ['configuracion.ver_auditoria'],
        },
    ],
    configuracion_sistema: [
        {
            id: 'sistema',
            label: 'Configuración del sistema',
            descripcion: 'Variables y entorno',
            permisos: ['configuracion_sistema.gestionar'],
        },
    ],
    sistema: [
        {
            id: 'auditorias',
            label: 'Auditorías',
            descripcion: 'Auditorías del sistema',
            permisos: ['sistema.auditorias.ver'],
        },
        {
            id: 'accesos',
            label: 'Auditoría de accesos',
            descripcion: 'Consulta de accesos',
            permisos: ['sistema.auditorias.accesos.ver'],
        },
    ],
    api_externa: [
        {
            id: 'gestion',
            label: 'API externa',
            descripcion: 'Aplicaciones, permisos y documentación',
            permisos: ['api_externa.gestionar'],
        },
        {
            id: 'auditoria',
            label: 'Auditoría API',
            descripcion: 'Bitácora de uso de la API',
            permisos: ['api_externa.ver_auditoria'],
        },
    ],
    soporte: [
        {
            id: 'reportar',
            label: 'Reportar',
            descripcion: 'Crear tickets de soporte',
            permisos: ['soporte.reportar'],
        },
        {
            id: 'agente',
            label: 'Atención',
            descripcion: 'Dashboard de agente de soporte',
            permisos: ['soporte.gestionar'],
        },
        {
            id: 'admin',
            label: 'Administración',
            descripcion: 'SLA y catálogos de soporte',
            permisos: ['soporte.administrar'],
        },
    ],
    mensajeria: [
        {
            id: 'monitoreo',
            label: 'Monitoreo',
            descripcion: 'Monitorear mensajería del sistema',
            permisos: ['mensajeria.monitorear'],
        },
        {
            id: 'moderacion',
            label: 'Moderación',
            descripcion: 'Eliminar mensajes',
            permisos: ['mensajeria.eliminar'],
        },
    ],
};

function agruparPorEntidadComoSubmodulos(permisosDeModulo) {
    const { columnas, filas } = agruparPermisosEnMatriz(permisosDeModulo);
    return filas.map((fila) => {
        const permisos = [];
        columnas.forEach((col) => {
            if (col.key === 'otros') {
                permisos.push(...fila.celdas.otros);
            } else if (fila.celdas[col.key]) {
                permisos.push(fila.celdas[col.key]);
            }
        });
        return {
            id: fila.entidad,
            label: fila.entidadLabel,
            descripcion: null,
            permisos,
        };
    }).filter((g) => g.permisos.length > 0);
}

/** Agrupa permisos visibles de un módulo según SUBMODULOS_UI_POR_MODULO (o por entidad). */
export function agruparPermisosPorSubmodulo(modulo, permisosDeModulo) {
    const defs = SUBMODULOS_UI_POR_MODULO[modulo];
    if (!defs?.length) {
        return agruparPorEntidadComoSubmodulos(permisosDeModulo);
    }

    const porNombre = new Map((permisosDeModulo || []).map((p) => [p.name, p]));
    const asignados = new Set();
    const grupos = [];

    defs.forEach((def) => {
        const permisos = def.permisos
            .map((name) => porNombre.get(name))
            .filter(Boolean);
        permisos.forEach((p) => asignados.add(p.name));
        if (permisos.length > 0) {
            grupos.push({
                id: def.id,
                label: def.label,
                descripcion: def.descripcion || null,
                permisos,
            });
        }
    });

    const resto = (permisosDeModulo || []).filter((p) => !asignados.has(p.name));
    if (resto.length > 0) {
        grupos.push({
            id: 'otros',
            label: 'Otros',
            descripcion: null,
            permisos: resto,
        });
    }

    return grupos;
}

/** Secciones del sidebar → módulos Spatie (orden de visualización en permisos atómicos). */
export const SECCIONES_SIDEBAR_PERMISOS = [
    {
        id: 'operaciones',
        label: 'Operaciones',
        descripcion: 'Solicitudes, comercial y logística (gestión de pedidos)',
        modulos: [
            'solicitudes',
            'cancelaciones_cotizaciones',
            'traspasos',
            'mis_clientes',
            'entregas',
            'control_pedidos',
        ],
    },
    {
        id: 'finanzas',
        label: 'Finanzas',
        descripcion: 'Contabilidad, facturas y cobranza',
        modulos: ['contabilidad', 'facturas', 'cobranza'],
    },
    {
        id: 'herramientas',
        label: 'Herramientas',
        descripcion: 'Listados, funciones operativas y ejercicios',
        modulos: ['listados', 'funciones', 'ejercicio_escalonamiento'],
    },
    {
        id: 'vinculaciones',
        label: 'Vinculaciones',
        descripcion: 'Integraciones externas',
        modulos: ['woocommerce'],
    },
    {
        id: 'gestion_interna',
        label: 'Gestión interna',
        descripcion: 'RH, activos, plantillas y directorio',
        modulos: ['plantilla_pedidos', 'rh', 'activos', 'gestion_interna'],
    },
    {
        id: 'almacenes',
        label: 'Almacenes',
        descripcion: 'Productos, inventarios y costos',
        modulos: ['almacenes'],
    },
    {
        id: 'soporte',
        label: 'Soporte',
        descripcion: 'Tickets y atención',
        modulos: ['soporte'],
    },
    {
        id: 'sistema',
        label: 'Sistema',
        descripcion: 'Administración, usuarios, catálogos y configuración',
        modulos: [
            'usuarios',
            'clientes',
            'catalogos',
            'comisiones',
            'personalizacion',
            'api_externa',
            'sistema',
            'configuracion',
            'configuracion_sistema',
            'mensajeria',
        ],
    },
];

/** Etiqueta legible del módulo Spatie en la UI de permisos. */
export const ETIQUETAS_MODULO_UI = {
    solicitudes: 'Cambio de lista y tags',
    cancelaciones_cotizaciones: 'Cancelación y cotización',
    mis_clientes: 'Mis clientes',
    entregas: 'Entregas / mapa logístico',
    control_pedidos: 'Gestión de pedidos',
    contabilidad: 'Contabilidad',
    facturas: 'Facturas',
    traspasos: 'Traspasos',
    cobranza: 'Credibox',
    listados: 'Listados',
    funciones: 'Funciones operativas',
    ejercicio_escalonamiento: 'Ejercicio escalonamiento',
    woocommerce: 'WooCommerce',
    plantilla_pedidos: 'Plantilla de pedidos',
    rh: 'Recursos humanos',
    activos: 'Control de activos',
    gestion_interna: 'Directorio interno',
    almacenes: 'Almacenes',
    soporte: 'Soporte',
    usuarios: 'Usuarios',
    clientes: 'Base de clientes / direcciones',
    catalogos: 'Catálogos globales',
    comisiones: 'Comisiones',
    personalizacion: 'Personalización',
    api_externa: 'API externa',
    sistema: 'Auditorías de sistema',
    configuracion: 'Auditoría de solicitudes',
    configuracion_sistema: 'Configuración del sistema',
    mensajeria: 'Mensajería',
};

export function etiquetaModuloUi(modulo) {
    return ETIQUETAS_MODULO_UI[modulo] || String(modulo || '').replace(/_/g, ' ');
}

/**
 * Reordena un mapa { modulo: permisos[] } según las secciones del sidebar.
 * Módulos no mapeados van a «Otros».
 */
export function agruparModulosPorSeccionSidebar(permisosAgrupadosPorModulo) {
    const pendientes = new Set(Object.keys(permisosAgrupadosPorModulo || {}));
    const secciones = [];

    SECCIONES_SIDEBAR_PERMISOS.forEach((sec) => {
        const modulos = [];
        sec.modulos.forEach((modulo) => {
            const permisos = permisosAgrupadosPorModulo?.[modulo];
            if (permisos?.length) {
                modulos.push({ modulo, permisos, label: etiquetaModuloUi(modulo) });
                pendientes.delete(modulo);
            }
        });
        if (modulos.length > 0) {
            secciones.push({
                id: sec.id,
                label: sec.label,
                descripcion: sec.descripcion || null,
                modulos,
            });
        }
    });

    if (pendientes.size > 0) {
        const modulos = [...pendientes]
            .sort((a, b) => etiquetaModuloUi(a).localeCompare(etiquetaModuloUi(b), 'es'))
            .map((modulo) => ({
                modulo,
                permisos: permisosAgrupadosPorModulo[modulo],
                label: etiquetaModuloUi(modulo),
            }));
        secciones.push({
            id: 'otros',
            label: 'Otros',
            descripcion: 'Módulos sin sección de menú asociada',
            modulos,
        });
    }

    return secciones;
}

export function etiquetaPermiso(permisoName) {
    if (ETIQUETAS_PERMISOS[permisoName]) {
        return ETIQUETAS_PERMISOS[permisoName];
    }
    const parts = permisoName.split('.');
    if (parts.length <= 2) {
        return parts[1]?.replace(/_/g, ' ') || permisoName;
    }
    const accion = parts[parts.length - 1].replace(/_/g, ' ');
    const contexto = parts.slice(1, -1).join(' ').replace(/_/g, ' ');
    return `${contexto} · ${accion}`;
}

/** Etiqueta en la matriz: sin repetir la entidad (ya está en la columna Entidad). */
export function etiquetaPermisoEnMatriz(permisoName) {
    if (ETIQUETAS_PERMISOS[permisoName]) {
        return ETIQUETAS_PERMISOS[permisoName];
    }
    const parts = (permisoName || '').split('.');
    if (parts.length <= 1) {
        return permisoName;
    }
    return parts[parts.length - 1].replace(/_/g, ' ');
}

export function descripcionPermiso(permisoName) {
    return DESCRIPCIONES_PERMISOS[permisoName] || null;
}

/** Normaliza permisos compartidos por Inertia (array o objeto indexado por id). */
export function permisosUsuario(auth) {
    const raw = auth?.user?.permissions;
    if (Array.isArray(raw)) return raw;
    if (raw && typeof raw === 'object') return Object.values(raw);
    return [];
}

/** Evalúa permiso atómico con bypass de Super Admin (mismo criterio que Sidebar). */
export function puedePermiso(auth, permiso) {
    const roles = auth?.user?.roles || [];
    if (roles.includes('Super Admin')) return true;
    return permisosUsuario(auth).includes(permiso);
}

/** Emite consulta TAG/Lista (compatible con nombre legado solicitudes.consultar). */
export function puedeEmitirConsultaSolicitud(auth) {
    return puedePermiso(auth, 'solicitudes.emitir_consulta') || puedePermiso(auth, 'solicitudes.consultar');
}

/** Responde consultas TAG/Lista (compatible con solicitudes.reportar). */
export function puedeResponderConsultaSolicitud(auth) {
    return puedePermiso(auth, 'solicitudes.responder_consulta') || puedePermiso(auth, 'solicitudes.reportar');
}

export function permisoDePlantilla(permisoName, plantillasActivas, roles) {
    return (roles || [])
        .filter((r) => (plantillasActivas || []).includes(r.name))
        .some((r) => (r.permissions || []).some((p) => p.name === permisoName));
}

export function plantillasDePermiso(permisoName, plantillasActivas, roles) {
    return (roles || [])
        .filter((r) => (plantillasActivas || []).includes(r.name))
        .filter((r) => (r.permissions || []).some((p) => p.name === permisoName))
        .map((r) => r.name);
}

/** @deprecated Use plantillasDePermiso */
export function rolesHeredantes(permisoName, rolesAsignados, roles) {
    return plantillasDePermiso(permisoName, rolesAsignados, roles);
}

export function permisosDePlantilla(plantillasActivas, roles) {
    const nombres = new Set();
    (roles || [])
        .filter((r) => (plantillasActivas || []).includes(r.name))
        .forEach((r) => (r.permissions || []).forEach((p) => nombres.add(p.name)));
    return [...nombres];
}

/** @deprecated Use permisosDePlantilla */
export function permisosHeredadosDeRoles(rolesAsignados, roles) {
    return permisosDePlantilla(rolesAsignados, roles);
}

export function deduplicarPermisos(permisosIndividuales) {
    return [...new Set(permisosIndividuales || [])];
}

/** @deprecated Ya no filtra plantillas — solo deduplica */
export function filtrarPermisosDirectos(permisosIndividuales) {
    return deduplicarPermisos(permisosIndividuales);
}

export function usuarioPuedeAsignarPermiso(permisoName, permisosUsuario, esSuperAdmin) {
    if (esSuperAdmin) return true;
    return (permisosUsuario || []).includes(permisoName);
}

/** Permisos que un gerente no puede otorgar a colaboradores (aunque los tenga en su cuenta). */
export const PERMISOS_NO_DELEGABLES_POR_GERENTE = new Set([
    'configuracion.ver_auditoria',
    'usuarios.gestionar',
    'usuarios.generar_permisos',
    'personalizacion.gestionar',
    'api_externa.gestionar',
    'api_externa.ver_auditoria',
]);

export function permisoNoDelegablePorGerente(permisoName, esSuperAdmin) {
    if (esSuperAdmin) return false;
    return PERMISOS_NO_DELEGABLES_POR_GERENTE.has(permisoName);
}

export function permisoProtegidoParaEditor(meta, usuarioActualId, esSuperAdmin) {
    if (esSuperAdmin || !meta) return false;
    const asignador = meta.asignado_por;
    if (!asignador) return false;
    if (asignador.es_super_admin || asignador.es_administrador) return true;
    if (asignador.id != null && usuarioActualId != null && Number(asignador.id) !== Number(usuarioActualId)) {
        return true;
    }
    return false;
}

export function gerentePuedeMostrarPermisoInactivo(permisoName, permisosUsuario, esSuperAdmin) {
    if (esSuperAdmin) return true;
    if (!usuarioPuedeAsignarPermiso(permisoName, permisosUsuario, esSuperAdmin)) return false;
    if (permisoNoDelegablePorGerente(permisoName, esSuperAdmin)) return false;
    return true;
}

export function filtrarRolesAsignables(roles, _permisosUsuario, esSuperAdmin) {
    return roles || [];
}

export function filtrarPermisosAsignables(todosLosPermisos, permisosUsuario, esSuperAdmin) {
    if (esSuperAdmin) return todosLosPermisos || [];
    return (todosLosPermisos || []).filter((p) => (permisosUsuario || []).includes(p.name));
}

export function construirPlantillaPorPermiso(permisos, plantillaNombre, roles) {
    if (!plantillaNombre) return {};
    const permisosPlantilla = permisosDePlantilla([plantillaNombre], roles);
    const map = {};
    (permisos || []).forEach((p) => {
        if (permisosPlantilla.includes(p)) {
            map[p] = plantillaNombre;
        }
    });
    return map;
}

export const COLUMNAS_ACCION_MATRIZ = [
    {
        key: 'ver',
        label: 'Ver',
        aliases: ['ver', 'ver_listado', 'ver_detalle', 'ver_todos', 'verificar'],
    },
    {
        key: 'crear',
        label: 'Crear',
        aliases: ['crear', 'carga_masiva', 'importar'],
    },
    {
        key: 'editar',
        label: 'Editar',
        aliases: [
            'editar', 'gestionar', 'asignar', 'transferir', 'cambiar_estado', 'configurar',
            'responder', 'reportar', 'emitir_consulta', 'responder_consulta', 'confirmar_pago',
            'solicitar_cancelacion', 'cancelar', 'confirmar_cambio_lista', 'confirmar',
            'reparar_fecha', 'editar_emergencia', 'gestionar_datos_fiscales', 'exportar',
            'generar_permisos', 'limpieza_clientes',
        ],
    },
    {
        key: 'eliminar',
        label: 'Eliminar',
        aliases: ['eliminar', 'archivar'],
    },
    { key: 'otros', label: 'Otros', aliases: [] },
];

export function parsePermisoEstructura(permisoName) {
    const parts = (permisoName || '').split('.');
    if (parts.length <= 1) {
        return { entidad: parts[0] || permisoName, accion: parts[0] || permisoName };
    }
    if (parts.length === 2) {
        return { entidad: parts[0], accion: parts[1] };
    }
    return {
        entidad: parts.slice(1, -1).join('.'),
        accion: parts[parts.length - 1],
    };
}

export function mapearAccionAColumna(accion) {
    for (const col of COLUMNAS_ACCION_MATRIZ) {
        if (col.key === 'otros') continue;
        if (col.aliases.includes(accion)) return col.key;
    }
    return 'otros';
}

export function etiquetaEntidadPermiso(entidad) {
    return entidad.replace(/_/g, ' ');
}

function crearFilaMatriz(entidad) {
    return {
        entidad,
        entidadLabel: etiquetaEntidadPermiso(entidad),
        celdas: { ver: null, crear: null, editar: null, eliminar: null, otros: [] },
    };
}

/** Agrupa permisos de un módulo en filas entidad × columnas de acción. */
export function agruparPermisosEnMatriz(permisosDeModulo) {
    const filasMap = new Map();

    (permisosDeModulo || []).forEach((permiso) => {
        const { entidad, accion } = parsePermisoEstructura(permiso.name);
        const columna = mapearAccionAColumna(accion);

        if (!filasMap.has(entidad)) {
            filasMap.set(entidad, crearFilaMatriz(entidad));
        }
        const fila = filasMap.get(entidad);

        if (columna === 'otros') {
            fila.celdas.otros.push(permiso);
        } else if (!fila.celdas[columna]) {
            fila.celdas[columna] = permiso;
        } else {
            fila.celdas.otros.push(permiso);
        }
    });

    return {
        columnas: COLUMNAS_ACCION_MATRIZ,
        filas: [...filasMap.values()].sort((a, b) => a.entidadLabel.localeCompare(b.entidadLabel, 'es')),
    };
}

export function permisoCoincideBusqueda(permisoName, query) {
    if (!query?.trim()) return true;
    const q = query.trim().toLowerCase();
    const label = etiquetaPermiso(permisoName).toLowerCase();
    const desc = (descripcionPermiso(permisoName) || '').toLowerCase();
    return (
        permisoName.toLowerCase().includes(q)
        || label.includes(q)
        || desc.includes(q)
    );
}

export function filtrarPermisosPorBusqueda(permisos, query) {
    if (!query?.trim()) return permisos || [];
    return (permisos || []).filter((p) => permisoCoincideBusqueda(p.name, query));
}

export function calcularDiffPlantilla(activos, plantillaNombre, roles) {
    if (!plantillaNombre) {
        return {
            heredados: [],
            agregados: [],
            removidos: [],
            personalizados: [],
            tienePlantilla: false,
        };
    }

    const heredados = permisosDePlantilla([plantillaNombre], roles);
    const activosSet = new Set(activos || []);
    const agregados = (activos || []).filter((p) => !heredados.includes(p));
    const removidos = heredados.filter((p) => !activosSet.has(p));
    const personalizados = [...new Set([...agregados, ...removidos])];

    return { heredados, agregados, removidos, personalizados, tienePlantilla: true };
}

export function resolverOrigenPermiso(meta, isDePlantilla, plantillaNombre, usuarioActualId) {
    if (!meta || meta?.plantilla_origen === 'sistema:migracion') {
        return { tipo: 'sistema', tooltip: 'Heredado por actualización del sistema' };
    }
    if (
        meta?.asignado_por?.id != null
        && usuarioActualId != null
        && Number(meta.asignado_por.id) === Number(usuarioActualId)
    ) {
        return { tipo: 'custom', tooltip: 'Asignado por ti' };
    }
    if (meta?.asignado_por?.nombre) {
        return { tipo: 'otro', tooltip: `Asignado por: ${meta.asignado_por.nombre}` };
    }
    if (isDePlantilla || meta?.plantilla_origen) {
        const nombre = meta?.plantilla_origen || plantillaNombre;
        return {
            tipo: 'plantilla',
            tooltip: nombre ? `Heredado de plantilla: ${nombre}` : 'Heredado de plantilla',
        };
    }
    return { tipo: 'custom', tooltip: 'Asignado manualmente' };
}
