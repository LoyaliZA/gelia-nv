export const SECCIONES = [
    { id: 'sistema', titulo: 'Sistema' },
    { id: 'sesion', titulo: 'Acceso y sesión' },
    { id: 'correo', titulo: 'Correo' },
    { id: 'pedidos', titulo: 'Pedidos' },
    { id: 'punto-venta', titulo: 'Punto de venta' },
    { id: 'saldos', titulo: 'Saldos a favor' },
    { id: 'escalonamiento', titulo: 'Escalonamiento' },
    { id: 'tienda', titulo: 'Tienda en línea' },
    { id: 'asistente', titulo: 'Asistente' },
    { id: 'notificaciones', titulo: 'Notificaciones del navegador' },
    { id: 'passkeys', titulo: 'Passkeys' },
    { id: 'otras', titulo: 'Otras configuraciones guardadas' },
];

const op = (value, label) => ({ value, label });

export const CATALOGO = {
    'session.lifetime': {
        seccion: 'sesion', orden: 10, control: 'number', min: 1,
        titulo: 'Minutos sin actividad',
        ayuda: 'Cierra la sesión si nadie usa el sistema durante este tiempo.',
    },
    'session.expire_on_close': {
        seccion: 'sesion', orden: 20, control: 'switch',
        titulo: 'Cerrar sesión al salir del navegador',
        ayuda: 'Al cerrar el navegador, la persona tiene que volver a entrar.',
    },
    'sesiones.jornada_cierre_activo': {
        seccion: 'sesion', orden: 30, control: 'switch',
        titulo: 'Cerrar sesiones al terminar la jornada',
        ayuda: 'Al llegar la hora de fin, se cierran las sesiones abiertas.',
    },
    'sesiones.jornada_hora_fin': {
        seccion: 'sesion', orden: 40, control: 'time',
        titulo: 'Hora de fin de jornada',
        ayuda: 'Hora local en la que termina la jornada de trabajo.',
    },
    'sesiones.jornada_zona_horaria': {
        seccion: 'sesion', orden: 50, control: 'text',
        titulo: 'Zona horaria de la jornada',
        ayuda: 'Ejemplo: America/Mexico_City.',
    },
    'mail.default': {
        seccion: 'correo', orden: 10, control: 'text',
        titulo: 'Medio de envío',
        ayuda: 'Forma en que salen los correos. Lo habitual es smtp.',
    },
    'mail.mailers.smtp.host': {
        seccion: 'correo', orden: 20, control: 'text',
        titulo: 'Servidor de correo',
        ayuda: 'Dirección del servidor que envía los mensajes.',
    },
    'mail.mailers.smtp.port': {
        seccion: 'correo', orden: 30, control: 'number', min: 1,
        titulo: 'Puerto',
        ayuda: 'Puerto del servidor. Con cifrado TLS suele ser 587.',
    },
    'mail.mailers.smtp.username': {
        seccion: 'correo', orden: 40, control: 'text',
        titulo: 'Usuario del correo',
        ayuda: 'Cuenta con la que el sistema entra al servidor.',
    },
    'mail.mailers.smtp.password': {
        seccion: 'correo', orden: 50, control: 'password',
        titulo: 'Contraseña del correo',
        ayuda: 'Contraseña o clave de aplicación. Dejar el campo vacío no la borra.',
    },
    'mail.mailers.smtp.encryption': {
        seccion: 'correo', orden: 60, control: 'select',
        titulo: 'Cifrado',
        ayuda: 'Protección de la conexión con el servidor de correo.',
        opciones: [op('tls', 'TLS'), op('ssl', 'SSL')],
    },
    'mail.from.address': {
        seccion: 'correo', orden: 70, control: 'text',
        titulo: 'Correo que aparece como remitente',
        ayuda: 'Dirección que ve quien recibe el mensaje.',
    },
    'mail.from.name': {
        seccion: 'correo', orden: 80, control: 'text',
        titulo: 'Nombre del remitente',
        ayuda: 'Nombre que acompaña al correo de salida.',
    },
    'control_pedidos.pagos.tolerancia_mxn': {
        seccion: 'pedidos', bloque: 'Pagos', orden: 10, control: 'decimal', min: 0, paso: '0.01',
        titulo: 'Diferencia permitida en pagos',
        ayuda: 'Pesos de diferencia que todavía se aceptan al conciliar un pago.',
    },
    'control_pedidos.auxiliar.ui_simplificada': {
        seccion: 'pedidos', bloque: 'Auxiliar', orden: 10, control: 'switch',
        titulo: 'Pantalla simplificada del auxiliar',
        ayuda: 'Muestra menos opciones a quien auxilia la preparación del pedido.',
    },
    'control_pedidos.envios.detalle_cajas': {
        seccion: 'pedidos', bloque: 'Envíos', orden: 10, control: 'switch',
        titulo: 'Costos por caja',
        ayuda: 'Muestra el costo de envío separado en cada caja.',
    },
    'control_pedidos.envios.moneda': {
        seccion: 'pedidos', bloque: 'Envíos', orden: 20, control: 'text',
        titulo: 'Moneda de los envíos',
        ayuda: 'Código de tres letras. Lo habitual es MXN.',
    },
    'control_pedidos.envios.reexpedicion_alcance': {
        seccion: 'pedidos', bloque: 'Envíos', orden: 30, control: 'select',
        titulo: 'Qué se puede reexpedir',
        ayuda: 'Define si una nueva guía cubre el pedido completo o una caja.',
        opciones: [op('pedido', 'El pedido completo'), op('caja', 'Cada caja por separado')],
    },
    'control_pedidos.envios.cedis_captura_costo': {
        seccion: 'pedidos', bloque: 'Envíos', orden: 40, control: 'switch',
        titulo: 'CEDIS puede capturar el costo',
        ayuda: 'Permite anotar el costo comercial del envío desde CEDIS.',
    },
    'control_pedidos.envios.editar_tras_recoleccion': {
        seccion: 'pedidos', bloque: 'Envíos', orden: 50, control: 'switch',
        titulo: 'Editar una caja ya recolectada',
        ayuda: 'Permite corregir peso o costo después de la recolección.',
    },
    'control_pedidos.envios.precision_decimales': {
        seccion: 'pedidos', bloque: 'Envíos', orden: 60, control: 'number', min: 0, max: 4,
        titulo: 'Decimales del costo',
        ayuda: 'Cantidad de decimales al redondear costos de envío.',
    },
    'control_pedidos.envios.requisitos_paqueteria': {
        seccion: 'pedidos', bloque: 'Envíos', orden: 70, control: 'json',
        titulo: 'Requisitos por paquetería',
        ayuda: 'Condiciones guardadas para cada paquetería. El identificador es el de la paquetería.',
    },
    'control_pedidos.ventas.formulario_progresivo': {
        seccion: 'pedidos', bloque: 'Ventas', orden: 10, control: 'switch',
        titulo: 'Formulario de venta por pasos',
        ayuda: 'El alta del pedido avanza por partes y puede guardarse sola.',
    },
    'control_pedidos.ventas.autosave_debounce_ms': {
        seccion: 'pedidos', bloque: 'Ventas', orden: 20, control: 'number', min: 0,
        titulo: 'Espera antes de guardar solo',
        ayuda: 'Milisegundos que espera el formulario antes de guardar lo escrito.',
    },
    'control_pedidos.ventas.max_reintentos_autosave': {
        seccion: 'pedidos', bloque: 'Ventas', orden: 30, control: 'number', min: 0,
        titulo: 'Reintentos si el guardado falla',
        ayuda: 'Cuántas veces vuelve a intentar guardar si hay un fallo.',
    },
    'control_pedidos.plazos_retraso': {
        seccion: 'pedidos', bloque: 'Plazos', orden: 10, control: 'json',
        titulo: 'Plazos de empaque y recolección',
        ayuda: 'Días y hora de corte para marcar un pedido como retrasado.',
    },
    'control_pedidos.preparacion_tienda': {
        seccion: 'pedidos', bloque: 'Preparación en tienda', orden: 10, control: 'preparacion',
        titulo: 'Preparación en tienda',
        ayuda: 'Activa el módulo y elige qué formas de entrega prepara la tienda.',
    },
    'control_pedidos.preparacion.dias_resguardo': {
        seccion: 'pedidos', bloque: 'Preparación en tienda', orden: 20, control: 'number', min: 1, max: 90,
        titulo: 'Días de resguardo',
        ayuda: 'Días que la mercancía puede quedar apartada en tienda.',
    },
    'control_pedidos.preparacion.recordatorio_hora_local': {
        seccion: 'pedidos', bloque: 'Preparación en tienda', orden: 30, control: 'time',
        titulo: 'Hora del recordatorio',
        ayuda: 'Hora local del aviso de mercancía pendiente de recoger.',
    },
    'control_pedidos.preparacion.zona_horaria': {
        seccion: 'pedidos', bloque: 'Preparación en tienda', orden: 40, control: 'text',
        titulo: 'Zona horaria',
        ayuda: 'Zona con la que se calculan recordatorios y cierres. Ejemplo: America/Mexico_City.',
    },
    'control_pedidos.preparacion.cierre_operativo_hora': {
        seccion: 'pedidos', bloque: 'Preparación en tienda', orden: 50, control: 'time',
        titulo: 'Hora de cierre operativo',
        ayuda: 'Hora local después de la cual ya no se opera la preparación del día.',
    },
    'control_pedidos.preparacion.dias_prorroga_vip': {
        seccion: 'pedidos', bloque: 'Preparación en tienda', orden: 60, control: 'number', min: 1, max: 5,
        titulo: 'Días extra de prórroga',
        ayuda: 'Días adicionales de resguardo cuando aplica una prórroga.',
    },
    'control_pedidos.cancelacion_operativa': {
        seccion: 'pedidos', bloque: 'Cancelaciones', orden: 10, control: 'json',
        titulo: 'Cancelación operativa',
        ayuda: 'Permite cancelar desde la operación y limitar quién puede usarlo.',
    },
    'control_pedidos.preparacion.dias_tipo': {
        seccion: 'pedidos', bloque: 'Cancelaciones', orden: 20, control: 'select',
        titulo: 'Cómo se cuentan los días',
        ayuda: 'Los plazos de resguardo cuentan días naturales o solo días hábiles.',
        opciones: [op('naturales', 'Días naturales'), op('habiles', 'Días hábiles')],
    },
    'control_pedidos.preparacion.anticipacion_aviso_horas': {
        seccion: 'pedidos', bloque: 'Cancelaciones', orden: 30, control: 'number', min: 1, max: 168,
        titulo: 'Aviso antes del vencimiento',
        ayuda: 'Horas de anticipación del aviso de que el resguardo está por vencer.',
    },
    'control_pedidos.preparacion.rol_resolutor_financiero': {
        seccion: 'pedidos', bloque: 'Cancelaciones', orden: 40, control: 'text',
        titulo: 'Quién libera el tema financiero',
        ayuda: 'Permiso de quien autoriza la liberación. Cámbielo solo si soporte lo indica.',
    },
    'control_pedidos.preparacion.evidencia_liberar': {
        seccion: 'pedidos', bloque: 'Cancelaciones', orden: 50, control: 'select',
        titulo: 'Evidencia para liberar',
        ayuda: 'Si hace falta adjuntar evidencia al liberar una cancelación.',
        opciones: [op('opcional', 'La evidencia es opcional'), op('obligatoria', 'Hay que adjuntar evidencia')],
    },
    'punto_venta.habilitado': {
        seccion: 'punto-venta', bloque: 'Módulo', orden: 10, control: 'switch',
        titulo: 'Punto de venta activo',
        ayuda: 'Abre las pantallas y rutas del punto de venta.',
    },
    'pdv.turnos.plazos': {
        seccion: 'punto-venta', bloque: 'Turnos', orden: 10, control: 'json',
        titulo: 'Tiempos de atención',
        ayuda: 'Espera, prórroga y avisos de los turnos en sucursal.',
    },
    'pdv.operacion.horario_cierre': {
        seccion: 'punto-venta', bloque: 'Horario', orden: 10, control: 'json',
        titulo: 'Horario de la sucursal',
        ayuda: 'Apertura y cierre generales. Los ajustes por sucursal que ya existan se conservan.',
    },
    'pdv.resguardos.registro_manual': {
        seccion: 'punto-venta', bloque: 'Resguardos', orden: 10, control: 'switch',
        titulo: 'Registro manual de resguardos',
        ayuda: 'Permite dar de alta un resguardo a mano en la sucursal.',
    },
    'pdv.resguardos.plazos_custodia': {
        seccion: 'punto-venta', bloque: 'Resguardos', orden: 20, control: 'json',
        titulo: 'Plazos de custodia',
        ayuda: 'Días de resguardo, aviso y rezago. Los ajustes por sucursal que ya existan se conservan.',
    },
    'pdv.publicidad.volumen_sala': {
        seccion: 'punto-venta', bloque: 'Publicidad', orden: 10, control: 'json',
        titulo: 'Volumen de publicidad en sala',
        ayuda: 'Porcentaje de videos de publicidad por sucursal. Si no hay fila, se usa 30.',
    },
    'saldos_favor.monto_minimo': {
        seccion: 'saldos', orden: 10, control: 'decimal', min: 0, paso: '0.01',
        titulo: 'Monto mínimo',
        ayuda: 'A partir de esta cantidad se genera un saldo a favor.',
    },
    'saldos_favor.vigencia_modo': {
        seccion: 'saldos', orden: 20, control: 'select',
        titulo: 'Cómo vence el saldo',
        ayuda: 'El saldo caduca a los varios días o en una fecha fija.',
        opciones: [op('dias', 'Después de varios días'), op('fecha_limite', 'En una fecha fija')],
    },
    'saldos_favor.vigencia_dias': {
        seccion: 'saldos', orden: 30, control: 'number', min: 0,
        titulo: 'Días de vigencia',
        ayuda: 'Días desde que se genera el saldo. Aplica cuando el vencimiento es por días.',
    },
    'saldos_favor.fecha_limite': {
        seccion: 'saldos', orden: 40, control: 'date',
        titulo: 'Fecha límite',
        ayuda: 'Día en que vencen los saldos. Aplica cuando el vencimiento es por fecha.',
    },
    'escalonamiento.autoridad_activa': {
        seccion: 'escalonamiento', orden: 10, control: 'switch',
        titulo: 'Publicar la lista vigente',
        ayuda: 'Muestra en clientes el monto y la lista de escalonamiento ya cortada.',
    },
    'escalonamiento.periodo_oficial_id': {
        seccion: 'escalonamiento', orden: 20, control: 'number', min: 1,
        titulo: 'Período que manda',
        ayuda: 'Identificador del período abierto. Vacío usa el período abierto más reciente.',
    },
    'tiendanube.app_id': {
        seccion: 'tienda', orden: 10, control: 'text',
        titulo: 'Identificador de la aplicación',
        ayuda: 'Identificador que entrega Tiendanube para la conexión.',
    },
    'tiendanube.app_secret': {
        seccion: 'tienda', orden: 20, control: 'password',
        titulo: 'Clave secreta',
        ayuda: 'Secreto de la aplicación. Dejar el campo vacío no lo borra.',
    },
    'tiendanube.store_id': {
        seccion: 'tienda', orden: 30, control: 'text',
        titulo: 'Identificador de la tienda',
        ayuda: 'Número de tienda en Tiendanube.',
    },
    'tiendanube.access_token': {
        seccion: 'tienda', orden: 40, control: 'password',
        titulo: 'Token de acceso',
        ayuda: 'Permiso de acceso a la tienda. Dejar el campo vacío no lo borra.',
    },
    'tiendanube.api_base': {
        seccion: 'tienda', orden: 50, control: 'text',
        titulo: 'Dirección de la API',
        ayuda: 'Dirección base de Tiendanube. Lo habitual es https://api.tiendanube.com/v1.',
    },
    'tiendanube.user_agent': {
        seccion: 'tienda', orden: 60, control: 'text',
        titulo: 'Nombre con el que se identifica el sistema',
        ayuda: 'Nombre que viaja en cada consulta a Tiendanube.',
    },
    'tiendanube.per_page': {
        seccion: 'tienda', orden: 70, control: 'number', min: 1,
        titulo: 'Productos por consulta',
        ayuda: 'Cuántos registros pide el sistema en cada página de sincronización.',
    },
    'tiendanube.webhook_url': {
        seccion: 'tienda', orden: 80, control: 'text',
        titulo: 'Dirección de avisos',
        ayuda: 'A dónde Tiendanube envía los avisos. Vacío usa la dirección del sistema.',
    },
    'deepseek.api_token': {
        seccion: 'asistente', orden: 10, control: 'password',
        titulo: 'Clave del asistente',
        ayuda: 'Clave de acceso al servicio de respuestas. Dejar el campo vacío no la borra.',
    },
    'deepseek.base_url': {
        seccion: 'asistente', orden: 20, control: 'text',
        titulo: 'Dirección del servicio',
        ayuda: 'Dirección base del asistente.',
    },
    'gelia_ai.acceso_modo': {
        seccion: 'asistente', orden: 30, control: 'select',
        titulo: 'Quién puede usar el asistente',
        ayuda: 'Alcance de personas que ven el chat.',
        opciones: [
            op('general', 'Cualquier persona con acceso al sistema'),
            op('usuarios', 'Solo las personas indicadas'),
            op('super_admin', 'Solo administración general'),
        ],
    },
    'gelia_ai.acceso_user_ids': {
        seccion: 'asistente', orden: 40, control: 'json',
        titulo: 'Personas con acceso',
        ayuda: 'Identificadores separados por coma. Solo aplica si el acceso es por personas indicadas.',
    },
    'gelia_ai.model': {
        seccion: 'asistente', orden: 50, control: 'text',
        titulo: 'Modelo de respuestas',
        ayuda: 'Nombre del modelo que redacta las respuestas.',
    },
    'webpush.vapid.subject': {
        seccion: 'notificaciones', orden: 10, control: 'text',
        titulo: 'Contacto del aviso',
        ayuda: 'Dirección del sitio o correo (mailto:) de quien envía las notificaciones.',
    },
    'webpush.vapid.public_key': {
        seccion: 'notificaciones', orden: 20, control: 'area',
        titulo: 'Clave pública',
        ayuda: 'Clave pública de las notificaciones del navegador.',
    },
    'webpush.vapid.private_key': {
        seccion: 'notificaciones', orden: 30, control: 'password',
        titulo: 'Clave privada',
        ayuda: 'Clave privada de las notificaciones. Dejar el campo vacío no la borra.',
    },
    'webauthn.enabled': {
        seccion: 'passkeys', orden: 10, control: 'switch',
        titulo: 'Entrar con passkey',
        ayuda: 'Permite iniciar sesión con huella, rostro o llave de seguridad.',
    },
    'webauthn.relying_party.name': {
        seccion: 'passkeys', orden: 20, control: 'text',
        titulo: 'Nombre del sitio',
        ayuda: 'Nombre que se muestra al guardar una passkey en el dispositivo.',
    },
    'webauthn.relying_party.id': {
        seccion: 'passkeys', orden: 30, control: 'text',
        titulo: 'Dominio del sitio',
        ayuda: 'Dominio sin https ni puerto. Debe coincidir con el sitio real.',
    },
    'webauthn.origins': {
        seccion: 'passkeys', orden: 40, control: 'text',
        titulo: 'Direcciones permitidas',
        ayuda: 'Direcciones completas separadas por coma. Ejemplo: https://app.ejemplo.com',
    },
};

const DIAS = [
    op('1', 'Lun'), op('2', 'Mar'), op('3', 'Mié'), op('4', 'Jue'),
    op('5', 'Vie'), op('6', 'Sáb'), op('7', 'Dom'),
];

export const ESQUEMAS = {
    'control_pedidos.cancelacion_operativa': {
        campos: [
            {
                ruta: 'activo', tipo: 'switch',
                titulo: 'Cancelación operativa activa',
                ayuda: 'Con esto activo, la operación puede solicitar la cancelación.',
            },
            {
                ruta: 'departamentos_habilitados', tipo: 'ids',
                titulo: 'Departamentos',
                ayuda: 'Identificadores separados por coma. Vacío: todos los departamentos.',
            },
            {
                ruta: 'usuarios_piloto', tipo: 'ids',
                titulo: 'Personas en prueba',
                ayuda: 'Identificadores separados por coma. Vacío: cualquiera con permiso.',
            },
        ],
    },
    'control_pedidos.plazos_retraso': {
        campos: [
            { ruta: 'activo', tipo: 'switch', titulo: 'Control de retrasos activo', ayuda: 'Marca pedidos que se pasan del plazo de empaque o recolección.' },
            { ruta: 'hora_corte', tipo: 'time', titulo: 'Hora de corte', ayuda: 'Después de esta hora, el día cuenta como el siguiente.' },
            { ruta: 'dias_habiles', tipo: 'dias', titulo: 'Días que se trabajan', ayuda: 'Días en los que corre el plazo.', opciones: DIAS },
            { ruta: 'temporada_alta', tipo: 'switch', titulo: 'Temporada alta', ayuda: 'Suma días extra mientras esté activa.' },
            { ruta: 'dias_extra_temporada_alta', tipo: 'number', min: 0, titulo: 'Días extra en temporada alta', ayuda: 'Días que se agregan al plazo durante la temporada alta.' },
            { ruta: 'comercial.dias_empaque', tipo: 'number', min: 0, titulo: 'Empaque, envío comercial', ayuda: 'Días para empacar un pedido comercial.' },
            { ruta: 'comercial.dias_recoleccion', tipo: 'number', min: 0, titulo: 'Recolección, envío comercial', ayuda: 'Días para recolectar un pedido comercial.' },
            { ruta: 'local_regional.dias_empaque', tipo: 'number', min: 0, titulo: 'Empaque, envío local o regional', ayuda: 'Días para empacar un pedido local o regional.' },
            { ruta: 'local_regional.dias_recoleccion', tipo: 'number', min: 0, titulo: 'Recolección, envío local o regional', ayuda: 'Días para recolectar un pedido local o regional.' },
        ],
    },
    'pdv.turnos.plazos': {
        campos: [
            { ruta: 'espera_inicial_minutos', tipo: 'number', min: 1, titulo: 'Espera inicial', ayuda: 'Minutos de espera antes de pasar el turno.' },
            { ruta: 'prorroga_minutos', tipo: 'number', min: 1, titulo: 'Prórroga', ayuda: 'Minutos extra de atención.' },
            { ruta: 'ventana_reatencion_minutos', tipo: 'number', min: 1, titulo: 'Ventana para retomar', ayuda: 'Minutos para volver a atender un turno.' },
            { ruta: 'aviso_tolerancia_espera_minutos', tipo: 'number', min: 1, titulo: 'Aviso antes de que venza la espera', ayuda: 'Minutos de anticipación del aviso de espera.' },
            { ruta: 'aviso_tolerancia_prorroga_minutos', tipo: 'number', min: 1, titulo: 'Aviso antes de que venza la prórroga', ayuda: 'Minutos de anticipación del aviso de prórroga.' },
            { ruta: 'inicio_atencion_automatico', tipo: 'switch', titulo: 'Empezar la atención sola', ayuda: 'La atención arranca sin una acción manual.' },
            { ruta: 'cooldown_segundos', tipo: 'number', min: 0, titulo: 'Espera entre turnos', ayuda: 'Segundos mínimos entre un turno y el siguiente.' },
        ],
    },
    'pdv.resguardos.plazos_custodia': {
        campos: [
            { ruta: 'activo', tipo: 'switch', titulo: 'Control de custodia activo', ayuda: 'Calcula vencimiento, aviso y rezago de los resguardos.' },
            { ruta: 'zona_horaria', tipo: 'text', titulo: 'Zona horaria', ayuda: 'Ejemplo: America/Mexico_City.' },
            {
                ruta: 'tipo_dias', tipo: 'select', titulo: 'Cómo se cuentan los días',
                ayuda: 'Días naturales o solo días hábiles.',
                opciones: [op('naturales', 'Días naturales'), op('habiles', 'Días hábiles')],
            },
            { ruta: 'dias_habiles', tipo: 'dias', titulo: 'Días hábiles', ayuda: 'Días que cuentan cuando el plazo es en días hábiles.', opciones: DIAS },
            { ruta: 'custodia_dias', tipo: 'number', min: 1, titulo: 'Días de custodia', ayuda: 'Días que la mercancía permanece en resguardo.' },
            { ruta: 'aviso_previo_dias', tipo: 'number', min: 1, titulo: 'Aviso previo', ayuda: 'Días antes del vencimiento en los que se avisa.' },
            { ruta: 'rezago_dias', tipo: 'number', min: 1, titulo: 'Días para considerarlo rezago', ayuda: 'Días después del vencimiento para marcarlo como rezago.' },
        ],
    },
    'pdv.operacion.horario_cierre': {
        campos: [
            { ruta: 'activo', tipo: 'switch', titulo: 'Horario activo', ayuda: 'Aplica apertura y cierre a la operación de la sucursal.' },
            { ruta: 'zona_horaria', tipo: 'text', titulo: 'Zona horaria', ayuda: 'Ejemplo: America/Mexico_City.' },
            { ruta: 'hora_apertura', tipo: 'time', titulo: 'Hora de apertura', ayuda: 'Vacío: no hay una hora de apertura.' },
            { ruta: 'hora_cierre', tipo: 'time', titulo: 'Hora de cierre', ayuda: 'Hora en la que cierra la operación.' },
        ],
    },
    'pdv.publicidad.volumen_sala': {
        tipoRaiz: 'mapa',
        ruta: 'por_sucursal',
        modo: 'numero',
        tituloClave: 'Sucursal',
        tituloValor: 'Volumen (%)',
        ayudaFila: 'Identificador de la sucursal y porcentaje entre 0 y 100.',
    },
    'control_pedidos.envios.requisitos_paqueteria': {
        tipoRaiz: 'mapa',
        ruta: null,
        modo: 'texto',
        tituloClave: 'Paquetería',
        tituloValor: 'Requisito',
        ayudaFila: 'Identificador de la paquetería y el requisito guardado.',
    },
    'gelia_ai.acceso_user_ids': {
        tipoRaiz: 'ids',
        titulo: 'Identificadores',
        ayuda: 'Números separados por coma.',
    },
};

export const esBooleanoVerdadero = (valor) => valor === true || valor === 'true' || valor === '1' || valor === 1;

export function metaDe(config) {
    const conocida = CATALOGO[config?.clave];
    if (conocida) {
        return conocida;
    }

    const descripcion = (config?.descripcion || '').trim();
    let control = 'text';
    if (config?.tipo === 'boolean') control = 'switch';
    else if (config?.tipo === 'integer' || config?.tipo === 'decimal') control = 'number';
    else if (config?.tipo === 'json') control = 'area';

    return {
        seccion: 'otras',
        orden: 1000,
        bloque: 'Sin pantalla propia',
        control,
        titulo: descripcion || humanizarClave(config?.clave),
        ayuda: descripcion
            ? 'Ajuste guardado antes de esta pantalla. El texto se conserva tal cual.'
            : 'Ajuste guardado que todavía no tiene una pantalla propia.',
    };
}

export function humanizarClave(clave) {
    const segmentos = String(clave || '').split('.');
    const relevantes = segmentos.length >= 2 ? segmentos.slice(-2) : segmentos;

    return relevantes
        .join(' ')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

const ORDEN_BLOQUE = {
    pedidos: ['Pagos', 'Auxiliar', 'Envíos', 'Ventas', 'Plazos', 'Preparación en tienda', 'Cancelaciones'],
    'punto-venta': ['Módulo', 'Turnos', 'Horario', 'Resguardos', 'Publicidad'],
};

function ordenBloque(seccion, nombre) {
    const lista = ORDEN_BLOQUE[seccion] || [];
    const indice = lista.indexOf(nombre || '');
    return indice === -1 ? 500 : indice;
}

export function organizar(configuraciones) {
    const porSeccion = {};

    configuraciones.forEach((config) => {
        const meta = metaDe(config);
        if (!porSeccion[meta.seccion]) {
            porSeccion[meta.seccion] = [];
        }
        porSeccion[meta.seccion].push({ config, meta });
    });

    Object.values(porSeccion).forEach((items) => {
        items.sort((a, b) => {
            const bloque = ordenBloque(a.meta.seccion, a.meta.bloque) - ordenBloque(b.meta.seccion, b.meta.bloque);
            if (bloque !== 0) {
                return bloque;
            }
            return (a.meta.orden ?? 999) - (b.meta.orden ?? 999)
                || a.meta.titulo.localeCompare(b.meta.titulo, 'es');
        });
    });

    return porSeccion;
}

export function agruparBloques(items) {
    const bloques = [];
    items.forEach((item) => {
        const nombre = item.meta.bloque || '';
        const ultimo = bloques[bloques.length - 1];
        if (!ultimo || ultimo.nombre !== nombre) {
            bloques.push({ nombre, items: [item] });
        } else {
            ultimo.items.push(item);
        }
    });
    return bloques;
}

export function opcionesVisibles(meta, valorActual) {
    const opciones = meta.opciones ? [...meta.opciones] : [];
    const actual = String(valorActual ?? '');
    if (actual && !opciones.some((item) => item.value === actual)) {
        opciones.push({ value: actual, label: actual });
    }
    return opciones;
}
