<?php

namespace App\Support\Manuales\Content;

/**
 * Texto compartido web + PDF del manual Gestión de pedidos.
 */
class ControlPedidosManualContent
{
    /** @return array<string, mixed> */
    public static function payload(array $idsSeccionesVisibles): array
    {
        $ids = array_values($idsSeccionesVisibles);

        return [
            'portada' => [
                'titulo' => 'Gestión de pedidos',
                'subtitulo' => 'Manual operativo · Control Pedidos BMA',
                'intro' => 'Este documento describe el ciclo de vida de un pedido desde el borrador hasta el envío, las pantallas por cargo y cómo se señalizan errores. Solo se incluyen los capítulos autorizados para tu perfil.',
            ],
            'overview' => self::overview(),
            'flujo' => self::flujo(),
            'estatus' => self::estatus(),
            'errores' => self::erroresFiltrados($ids),
            'secciones' => self::seccionesFiltradas($ids),
            'ejemplos' => self::ejemplosFiltrados($ids),
        ];
    }

    /** @return array<string, mixed> */
    private static function overview(): array
    {
        return [
            'titulo' => 'Qué es este módulo',
            'parrafos' => [
                'Gestión de pedidos (Control Pedidos BMA) concentra el flujo logístico de pedidos: captura por vendedora, auditoría por auxiliar, empaque/envío en CEDIS y captura de guías.',
                'No confundir con Contabilidad → Pedidos (ledger financiero). Este manual cubre solo la operación BMA bajo Logística.',
            ],
            'escritorios' => [
                ['nombre' => 'Registrar pedidos', 'ruta' => '/control-pedidos', 'cargo' => 'Vendedora'],
                ['nombre' => 'Auditar pedidos', 'ruta' => '/control-pedidos/auditar', 'cargo' => 'Auxiliar'],
                ['nombre' => 'Control Pedidos (CEDIS)', 'ruta' => '/control-pedidos/cedis', 'cargo' => 'CEDIS'],
                ['nombre' => 'Actualizar guías', 'ruta' => '/control-pedidos/delegado', 'cargo' => 'Guías'],
                ['nombre' => 'Plazos de retraso', 'ruta' => '/control-pedidos/plazos', 'cargo' => 'Superadmin / gerente'],
                ['nombre' => 'Direcciones', 'ruta' => '/control-pedidos/direcciones', 'cargo' => 'Auxiliar / Direcciones'],
                ['nombre' => 'Saldos a favor', 'ruta' => '/saldos-favor', 'cargo' => 'Finanzas / Administración'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function flujo(): array
    {
        return [
            'titulo' => 'Diagrama de flujo operativo',
            'camino_feliz' => [
                ['fase' => 'BORRADOR', 'quien' => 'Vendedora', 'accion' => 'Crear / autoguardar; solicitar pesaje CEDIS si el origen requiere logística'],
                ['fase' => 'PESAJE_PENDIENTE → PESAJE_RESPONDIDO', 'quien' => 'CEDIS → Vendedora', 'accion' => 'CEDIS responde pesaje; el pedido pasa a pesaje respondido'],
                ['fase' => 'PENDIENTE_AUXILIAR', 'quien' => 'Vendedora → Auxiliar', 'accion' => 'Enviar a auditoría. Hitos: pago en revisión → pago validado → pendiente de remisión'],
                ['fase' => 'EN_CEDIS', 'quien' => 'Auxiliar', 'accion' => 'Validar pago + remisión PDF y aprobar → pendiente de empaque'],
                ['fase' => 'PENDIENTE_DE_GUIA o PENDIENTE_DE_ENVIO', 'quien' => 'CEDIS', 'accion' => 'Empacar (no es enviado). Sin guía → pendiente de guía; con guía o sin rastreo → pendiente de recolección'],
                ['fase' => 'PENDIENTE_DE_ENVIO', 'quien' => 'Guías (si aplica)', 'accion' => 'Asignar número de guía / PDF → pendiente de recolección'],
                ['fase' => 'ENVIADO', 'quien' => 'CEDIS', 'accion' => 'Solo cuando la paquetería recogió el paquete'],
            ],
            'ramas' => [
                ['nombre' => 'Rechazo auxiliar', 'detalle' => 'PENDIENTE_AUXILIAR → RECHAZADO_VENDEDORA (Rechazado o devuelto para corrección). Limpia remisión y validación de pago. La vendedora corrige y reenvía.'],
                ['nombre' => 'Reabrir envío', 'detalle' => 'ENVIADO → PENDIENTE_DE_ENVIO con permiso control_pedidos.reabrir. Solo si la paquetería no recogió.'],
                ['nombre' => 'Error de datos (cola)', 'detalle' => 'Prioridad vendedora → auxiliar → CEDIS → guías. Cambia fase según dueño activo e invalida guía/remisión cuando corresponde.'],
                ['nombre' => 'Error CEDIS / empaque', 'detalle' => 'EN_CEDIS → INCIDENCIA_CEDIS (Error CEDIS). Sigue siendo empacable tras resolver o continuar.'],
                ['nombre' => 'Resguardo', 'detalle' => 'Flag es_resguardo bloquea empaque y guía hasta liberar. estatus_envio puede ser pendiente_liberacion.'],
                ['nombre' => 'Municipio diferido / anexo', 'detalle' => 'Sin costo al enviar: pendiente_regularizacion → anexar pago → revisión auxiliar.'],
                ['nombre' => 'Sin existencias', 'detalle' => 'CEDIS marca la pieza (no la omite). Overlay: pedido detenido hasta que Ventas retire, sustituya, espere, contacte o cancele. Sin envío parcial. Si ya estaba pagado y cambia mercancía → se invalida remisión/pago y vuelve a auditoría. CEDIS puede confirmar «ya hay existencias» (el estado físico se conserva).'],
                ['nombre' => 'Saldos a favor', 'detalle' => 'Monto mínimo y vigencia (días o fecha límite) se configuran en /saldos-favor/configurar. Aplicación FIFO (vence primero), varios créditos por pedido, sin elegir crédito. Uso parcial permitido con aviso de usar completo. Cubre mercancía+envío. Estados: Disponible, Reservado, Aplicado, Vencido, Cancelado. Error de saldos: alerta visual solo en Auditar (no detiene el pedido); se corrige, bitácora y continúa.'],
            ],
            'reaperturas' => [
                ['desde' => 'Pesaje pendiente / respondido', 'hacia' => 'Borrador', 'permiso' => 'crear / editar (dueña)', 'nota' => 'Volver a borrador'],
                ['desde' => 'Pesaje respondido', 'hacia' => 'Pesaje pendiente', 'permiso' => 'crear / editar', 'nota' => 'Re-pesaje'],
                ['desde' => 'Rechazado', 'hacia' => 'Auditoría / borrador / pesaje', 'permiso' => 'crear / editar (dueña)', 'nota' => 'Reenvío actual'],
                ['desde' => 'Pendiente empaque', 'hacia' => 'Auditoría', 'permiso' => 'cola de errores / auditar', 'nota' => 'Sin botón extra'],
                ['desde' => 'Empacado sin guía', 'hacia' => 'Pendiente empaque', 'permiso' => 'control_pedidos.cedis', 'nota' => 'Revertir empacado'],
                ['desde' => 'Pendiente recolección con guía', 'hacia' => 'Pendiente de guía', 'permiso' => 'control_pedidos.delegado', 'nota' => 'Invalidar / corregir guía (overlay retraso)'],
                ['desde' => 'Enviado', 'hacia' => 'Pendiente recolección', 'permiso' => 'control_pedidos.reabrir', 'nota' => 'Solo si la paquetería no recogió'],
                ['desde' => 'Entregado / Cancelado', 'hacia' => '—', 'permiso' => 'ninguno', 'nota' => 'Terminales'],
            ],
        ];
    }

    /** @return list<array{fase: string, etiqueta: string, nota: string}> */
    private static function estatus(): array
    {
        return [
            ['fase' => 'BORRADOR', 'etiqueta' => 'Borrador', 'nota' => 'Editable por vendedora; eliminable'],
            ['fase' => 'PESAJE_PENDIENTE', 'etiqueta' => 'Pesaje pendiente', 'nota' => 'Esperando respuesta CEDIS'],
            ['fase' => 'PESAJE_RESPONDIDO', 'etiqueta' => 'Pesaje respondido', 'nota' => 'CEDIS ya pesó; vendedora cotiza y envía a auditoría'],
            ['fase' => 'PENDIENTE_AUXILIAR', 'etiqueta' => 'Pendiente de auditoría', 'nota' => 'Hitos: pago en revisión / pago validado / pendiente de remisión'],
            ['fase' => 'EN_CEDIS', 'etiqueta' => 'Pendiente de empaque', 'nota' => 'Aprobado; empacar / reportar error / apartado'],
            ['fase' => 'RECHAZADO_VENDEDORA', 'etiqueta' => 'Rechazado o devuelto para corrección', 'nota' => 'Corregir y reenviar'],
            ['fase' => 'INCIDENCIA_CEDIS', 'etiqueta' => 'Error CEDIS', 'nota' => 'Problema de empaque/pesaje reportado'],
            ['fase' => 'PENDIENTE_DE_GUIA', 'etiqueta' => 'Pendiente de guía', 'nota' => 'Empacado; falta rastreo'],
            ['fase' => 'PENDIENTE_DE_ENVIO', 'etiqueta' => 'Pendiente de recolección o envío', 'nota' => 'Empacado + guía (si aplica); espera recolecta'],
            ['fase' => 'ENVIADO', 'etiqueta' => 'Enviado', 'nota' => 'La paquetería recogió el paquete'],
            ['fase' => 'overlay', 'etiqueta' => 'Con retraso', 'nota' => 'Badge guia_retraso; no sustituye la fase'],
            ['fase' => 'overlay', 'etiqueta' => 'Sin existencias', 'nota' => 'Revisión sin_existencia abierta; detiene enviar/aprobar/empacar hasta decisión de Ventas o stock_ok de CEDIS'],
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return list<array<string, mixed>>
     */
    private static function seccionesFiltradas(array $ids): array
    {
        $todas = self::todasLasSecciones();

        return array_values(array_filter(
            $todas,
            fn (array $s) => in_array($s['id'], $ids, true)
        ));
    }

    /** @return list<array<string, mixed>> */
    private static function todasLasSecciones(): array
    {
        return [
            [
                'id' => 'vendedora',
                'cargo' => 'Vendedora',
                'titulo' => 'Registrar pedidos',
                'ruta' => '/control-pedidos',
                'resumen' => 'Creas el pedido, solicitas pesaje cuando aplica, completas datos y lo envías a auditoría. También atiendes rechazos y errores de datos tuyos.',
                'pasos' => [
                    ['titulo' => 'Crear o autoguardar', 'detalle' => 'El pedido nace en BORRADOR. Puedes ir guardando sin enviar.'],
                    ['titulo' => 'Pesaje CEDIS (si aplica)', 'detalle' => 'Si el origen requiere logística (y no es complementario), solicita pesaje (PESAJE_PENDIENTE). CEDIS responde → PESAJE_RESPONDIDO. Sin pesaje no podrás enviar.'],
                    ['titulo' => 'Completar campos', 'detalle' => 'Cliente, origen, banco, almacén, mercancía, comprobante de pago, paquetería, tipo de guía, reexpedición, CP y domicilio (o dirección verificada).'],
                    ['titulo' => 'Enviar', 'detalle' => 'Pasa a PENDIENTE_AUXILIAR. Se limpia remisión/validación de pago previas y se notifica al auxiliar.'],
                    ['titulo' => 'Si te rechazan o reportan error', 'detalle' => 'Aparece en RECHAZADAS. Corrige los campos marcados y reenvía.'],
                    ['titulo' => 'Saldo a favor', 'detalle' => 'Indica el monto a aplicar; el sistema reparte FIFO (vence primero). No se elige crédito. Varios saldos en un pedido sí; uso parcial permitido con aviso de preferir completo.'],
                    ['titulo' => 'Sin existencias', 'detalle' => 'Tab Sin existencias + alerta. El pedido no se envía ni se empaca hasta atender cada pieza: contactar, esperar, retirar, sustituir o cancelar. Retirar/sustituir recálcula mercancía, envío, seguro y saldo a favor.'],
                ],
                'elementos' => [
                    ['nombre' => 'Tabs del listado', 'uso' => 'Filtra por estado (todas, borradores, rechazadas, enviados, etc.).'],
                    ['nombre' => 'Nuevo pedido', 'uso' => 'Abre el formulario modal de captura.'],
                    ['nombre' => 'Solicitar pesaje / re-pesaje', 'uso' => 'Encola consulta a CEDIS; espera pesaje_listo.'],
                    ['nombre' => 'Enviar', 'uso' => 'Valida campos requeridos; falla con mensaje si falta algo.'],
                    ['nombre' => 'Anexar pago de envío', 'uso' => 'Cuando el costo quedó pendiente (municipio diferido / resguardo liberado).'],
                ],
                'errores_que_te_llegan' => [
                    'Rechazo del auxiliar (motivo libre).',
                    'Error de datos con dueño vendedora (domicilio, destinatario, teléfono, paquetería, tipo de guía, referencia, CP, ciudad/estado).',
                ],
            ],
            [
                'id' => 'auxiliar',
                'cargo' => 'Auxiliar',
                'titulo' => 'Auditar pedidos',
                'ruta' => '/control-pedidos/auditar',
                'resumen' => 'Revisas pago y remisión, apruebas hacia CEDIS, rechazas, reportas errores de datos, gestionas anexos de envío y liberas resguardos.',
                'pasos' => [
                    ['titulo' => 'Validar pago', 'detalle' => 'Hito «Pago en revisión» → «Pago validado». Obligatorio antes de aprobar. No cambia fase.'],
                    ['titulo' => 'Subir remisión PDF', 'detalle' => 'Hito «Pendiente de remisión» hasta adjuntar. Obligatorio antes de aprobar.'],
                    ['titulo' => 'Aprobar', 'detalle' => 'Envía a pendiente de empaque (EN_CEDIS). Requiere permiso control_pedidos.auditar.aprobar. No aprobar sin pago+remisión.'],
                    ['titulo' => 'Rechazar', 'detalle' => 'Motivo obligatorio → RECHAZADO_VENDEDORA.'],
                    ['titulo' => 'Reportar error de datos', 'detalle' => 'Selecciona campos incorrectos. No uses remisión vía este flujo estando en pendiente auxiliar: corrígela en tu bandeja.'],
                    ['titulo' => 'Alerta de saldos a favor', 'detalle' => 'Si hay incidencia SAF, ves un aviso ámbar (solo auxiliar). No cambia la fase del pedido. Corrige el monto si aplica, marca revisado (bitácora) y continúa.'],
                ],
                'elementos' => [
                    ['nombre' => 'Validar pago', 'uso' => 'Flag de auditoría; no cambia fase.'],
                    ['nombre' => 'Remisión PDF', 'uso' => 'Adjuntar / eliminar remisión.'],
                    ['nombre' => 'Aprobar / Rechazar', 'uso' => 'Transiciones principales de auditoría.'],
                    ['nombre' => 'Liberar resguardo', 'uso' => 'Con captura de costo/comprobante cuando aplica. Requiere permiso control_pedidos.liberar_resguardo.'],
                    ['nombre' => 'Anexos de envío', 'uso' => 'Aprobar o rechazar regularización de costo.'],
                    ['nombre' => 'Alerta SAF', 'uso' => 'Banner no bloqueante; resolver incidencia y seguir.'],
                ],
                'errores_que_te_llegan' => [
                    'Error de remisión (campos remision / folio_remision) desde CEDIS o guías.',
                    'Alertas de error CEDIS / empaque (informativas).',
                    'Alerta de saldos a favor (no bloqueante).',
                ],
            ],
            [
                'id' => 'cedis',
                'cargo' => 'CEDIS',
                'titulo' => 'Control de empaque y envío',
                'ruta' => '/control-pedidos/cedis',
                'resumen' => 'Respondes pesajes, empacas (incluye grupo principal+complementos), reportas errores, apartas resguardos y marcas envíos.',
                'pasos' => [
                    ['titulo' => 'Responder pesaje', 'detalle' => 'Peso, cajas, tipo de caja y revisión física. Si no hay existencias, márquela (no la omita) → Ventas queda detenida hasta elegir acción.'],
                    ['titulo' => 'Empacar', 'detalle' => 'Requiere pago+remisión, sin errores graves de guía/pago/productos y sin piezas sin existencias abiertas. Empacado ≠ enviado. Destino: pendiente de guía o de recolección.'],
                    ['titulo' => 'Reportar error', 'detalle' => 'Campos incorrectos (incluye CEDIS) → dueño correspondiente; Error CEDIS si es empaque/pesaje.'],
                    ['titulo' => 'Confirmar recolección', 'detalle' => 'Solo en pendiente de recolección, empacado, y con guía si ofrece rastreo. Marca ENVIADO cuando la paquetería recogió. Requiere permiso control_pedidos.cedis.enviar.'],
                    ['titulo' => 'Revertir empacado', 'detalle' => 'Solo sin número de guía asignado; vuelve a pendiente de empaque. Reabrir ENVIADO requiere permiso control_pedidos.reabrir.'],
                ],
                'elementos' => [
                    ['nombre' => 'Marcar empacado', 'uso' => 'Avanza fase según rastreo/guía.'],
                    ['nombre' => 'Paquetería recogió', 'uso' => 'Cierre operativo → ENVIADO (solo recolecta real). Requiere permiso control_pedidos.cedis.enviar.'],
                    ['nombre' => 'Reportar error', 'uso' => 'Documenta campo incorrecto y dueño de corrección.'],
                    ['nombre' => 'Apartado resguardo', 'uso' => 'Evidencia fotográfica de piezas apartadas.'],
                ],
                'errores_que_te_llegan' => [
                    'Pedidos en resguardo no empacables hasta liberación.',
                    'Error de guía grave: no enviar hasta corregir.',
                ],
            ],
            [
                'id' => 'guias',
                'cargo' => 'Guías',
                'titulo' => 'Actualizar guías',
                'ruta' => '/control-pedidos/delegado',
                'resumen' => 'Asignas número de rastreo, PDF de guía, importación masiva y corrección de guías. No asignas guía a pedidos en resguardo.',
                'pasos' => [
                    ['titulo' => 'Asignar guía (post-empaque)', 'detalle' => 'PENDIENTE_DE_GUIA → pendiente de recolección; notifica a CEDIS.'],
                    ['titulo' => 'Guía pre-empaque', 'detalle' => 'Puedes capturar en EN_CEDIS sin cambiar fase; al empacar irá directo a pendiente de recolección.'],
                    ['titulo' => 'PDF de guía', 'detalle' => 'Adjunta el documento de la paquetería.'],
                    ['titulo' => 'Importar / exportar', 'detalle' => 'Plantilla CSV para todos. Importar masivo requiere permiso control_pedidos.delegado.importar.'],
                    ['titulo' => 'Actualizar guía', 'detalle' => 'Corrección; puede marcar retraso y avisar a CEDIS.'],
                ],
                'elementos' => [
                    ['nombre' => 'Asignar guía', 'uso' => 'Número de rastreo obligatorio.'],
                    ['nombre' => 'Guía PDF', 'uso' => 'Alta / baja de documento.'],
                    ['nombre' => 'Importar', 'uso' => 'Carga masiva de guías. Requiere permiso control_pedidos.delegado.importar.'],
                    ['nombre' => 'Reportar error de datos', 'uso' => 'Si el dato incorrecto es de vendedora o auxiliar.'],
                ],
                'errores_que_te_llegan' => [
                    'pedido_error_guia / pedido_pendiente_guia.',
                    'Guía invalidada al reportar error en domicilio/paquetería/rastreo.',
                ],
            ],
            [
                'id' => 'direcciones',
                'cargo' => 'Direcciones',
                'titulo' => 'Direcciones de envío',
                'ruta' => '/control-pedidos/direcciones',
                'resumen' => 'Administras direcciones normalizadas del cliente, enlaces públicos y solicitudes de alta/cambio para que la vendedora seleccione domicilio verificado al enviar.',
                'pasos' => [
                    ['titulo' => 'Buscar cliente', 'detalle' => 'Consulta y gestiona sus direcciones.'],
                    ['titulo' => 'Crear / editar / principal', 'detalle' => 'Mantiene el catálogo usable en pedidos.'],
                    ['titulo' => 'Enlace público', 'detalle' => 'Genera o revoca enlace para captura del cliente.'],
                    ['titulo' => 'Solicitudes', 'detalle' => 'Aprobar, rechazar, pedir corrección o vincular.'],
                ],
                'elementos' => [
                    ['nombre' => 'Listado de direcciones', 'uso' => 'CRUD + marcar principal.'],
                    ['nombre' => 'Enlace', 'uso' => 'Formulario seguro externo.'],
                    ['nombre' => 'Bandeja de solicitudes', 'uso' => 'Revisión antes de quedar disponibles en el pedido.'],
                ],
                'errores_que_te_llegan' => [
                    'Si direcciones normalizadas están activas, el envío del pedido exige dirección verificada seleccionada.',
                ],
            ],
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return list<array{tipo: string, cuando: string, cargos: list<string>}>
     */
    private static function erroresFiltrados(array $ids): array
    {
        $mapa = [
            'vendedora' => ['pedido_rechazado_auxiliar', 'pedido_error_datos', 'pedido_enviado', 'pedido_pesaje_listo', 'pedido_sin_existencia', 'pedido_retraso_empaque', 'pedido_retraso_recoleccion'],
            'auxiliar' => ['pedido_pendiente_auxiliar', 'pedido_error_remision', 'pedido_error_cedis', 'pedido_incidencia_cedis'],
            'cedis' => ['pedido_aprobado', 'pedido_consulta_pesaje', 'pedido_pendiente_envio', 'pedido_guia_asignada', 'pedido_error_guia', 'pedido_error_cedis', 'pedido_guia_retraso', 'pedido_sin_existencia', 'pedido_retraso_empaque', 'pedido_retraso_recoleccion'],
            'guias' => ['pedido_pendiente_guia', 'pedido_error_guia', 'pedido_retraso_recoleccion'],
            'direcciones' => [],
        ];

        $tipos = [];
        foreach ($ids as $id) {
            foreach ($mapa[$id] ?? [] as $t) {
                $tipos[$t] = true;
            }
        }

        $catalogo = [
            'pedido_consulta_pesaje' => 'Consulta de pesaje pendiente en CEDIS.',
            'pedido_pesaje_listo' => 'CEDIS respondió el pesaje; ya puedes cotizar/enviar.',
            'pedido_sin_existencia' => 'Producto sin existencias; el pedido está detenido hasta que Ventas elija acción.',
            'pedido_pendiente_auxiliar' => 'Pedido en bandeja de auditoría.',
            'pedido_aprobado' => 'Pedido aprobado; visible en CEDIS.',
            'pedido_rechazado_auxiliar' => 'Rechazado; corrige y reenvía.',
            'pedido_error_datos' => 'Datos de vendedora incorrectos.',
            'pedido_error_remision' => 'Remisión / folio a corregir en auditoría.',
            'pedido_error_guia' => 'Error grave de guía; no enviar hasta corregir.',
            'pedido_error_cedis' => 'Error CEDIS (empaque/pesaje) a corregir.',
            'pedido_error_estado' => 'Aviso informativo: se reportó un error; solo el responsable corrige.',
            'pedido_incidencia_cedis' => 'Error de empaque reportado.',
            'pedido_guia_retraso' => 'Retraso por error/corrección post-guía.',
            'pedido_retraso_empaque' => 'No empacado dentro del plazo (alerta distinta a recolección).',
            'pedido_retraso_recoleccion' => 'Listo para envío pero no marcado enviado a tiempo.',
            'pedido_pendiente_guia' => 'Empacado; falta captura de guía.',
            'pedido_pendiente_envio' => 'Empacado; pendiente de recolección.',
            'pedido_guia_asignada' => 'Guía asignada; pendiente de recolección.',
            'pedido_enviado' => 'La paquetería recogió el paquete.',
        ];

        $out = [];
        foreach (array_keys($tipos) as $tipo) {
            if (! isset($catalogo[$tipo])) {
                continue;
            }
            $out[] = [
                'tipo' => $tipo,
                'cuando' => $catalogo[$tipo],
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $ids
     * @return list<array{titulo: string, detalle: string, secciones: list<string>}>
     */
    private static function ejemplosFiltrados(array $ids): array
    {
        $todos = [
            [
                'titulo' => 'Caso feliz',
                'detalle' => 'Borrador → pesaje pendiente → pesaje respondido → auditoría (hitos pago/remisión) → aprobar → empacar ≠ enviado → guía → pendiente de recolección → paquetería recogió.',
                'secciones' => ['vendedora', 'auxiliar', 'cedis', 'guias'],
            ],
            [
                'titulo' => 'Rechazo y reenvío',
                'detalle' => 'Auxiliar rechaza con motivo. Pedido en RECHAZADAS. Vendedora corrige y vuelve a PENDIENTE_AUXILIAR.',
                'secciones' => ['vendedora', 'auxiliar'],
            ],
            [
                'titulo' => 'Error de datos a vendedora',
                'detalle' => 'CEDIS o auxiliar marca domicilio/teléfono incorrecto → RECHAZADO_VENDEDORA, se invalida guía si existía y se limpia remisión/pago.',
                'secciones' => ['vendedora', 'auxiliar', 'cedis'],
            ],
            [
                'titulo' => 'Empaque con y sin guía',
                'detalle' => 'Paquetería con rastreo y sin número → PENDIENTE_DE_GUIA. Sin rastreo o con guía ya capturada → PENDIENTE_DE_ENVIO.',
                'secciones' => ['cedis', 'guias'],
            ],
            [
                'titulo' => 'Sin existencias',
                'detalle' => 'CEDIS marca la pieza. Ventas retira, sustituye, espera, contacta o cancela. Sin envío parcial. Recálculo + bitácora. Si ya estaba pagado y cambia mercancía, vuelve a auditoría sin remisión/pago.',
                'secciones' => ['vendedora', 'auxiliar', 'cedis'],
            ],
            [
                'titulo' => 'Retraso de empaque vs recolección',
                'detalle' => 'Si el pedido no se empaca a tiempo (desde pago validado + corte) se alerta retraso de empaque. Si ya está listo en PENDIENTE_DE_ENVIO y no se marca enviado, se alerta retraso de recolección. Son alertas distintas; plazos en /control-pedidos/plazos.',
                'secciones' => ['vendedora', 'cedis', 'guias'],
            ],
        ];

        return array_values(array_filter(
            $todos,
            fn (array $e) => count(array_intersect($e['secciones'], $ids)) > 0
        ));
    }
}
