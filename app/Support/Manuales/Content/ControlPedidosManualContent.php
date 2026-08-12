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
                ['nombre' => 'Direcciones', 'ruta' => '/control-pedidos/direcciones', 'cargo' => 'Auxiliar / Direcciones'],
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
                ['fase' => 'PENDIENTE_AUXILIAR', 'quien' => 'Vendedora → Auxiliar', 'accion' => 'Enviar a auditoría (campos y pesaje completos)'],
                ['fase' => 'EN_CEDIS', 'quien' => 'Auxiliar', 'accion' => 'Validar pago + remisión PDF y aprobar'],
                ['fase' => 'PENDIENTE_DE_GUIA o PENDIENTE_DE_ENVIO', 'quien' => 'CEDIS', 'accion' => 'Empacar: si ofrece rastreo y no hay guía → pendiente de guía; si no → pendiente de envío'],
                ['fase' => 'PENDIENTE_DE_ENVIO', 'quien' => 'Guías (si aplica)', 'accion' => 'Asignar número de guía / PDF'],
                ['fase' => 'ENVIADO', 'quien' => 'CEDIS', 'accion' => 'Marcar enviado (requiere empaque y guía si la paquetería rastrea)'],
            ],
            'ramas' => [
                ['nombre' => 'Rechazo auxiliar', 'detalle' => 'PENDIENTE_AUXILIAR → RECHAZADO_VENDEDORA. Limpia remisión y validación de pago. La vendedora corrige y reenvía.'],
                ['nombre' => 'Error de datos (cola)', 'detalle' => 'Prioridad vendedora → auxiliar → CEDIS → guías. Cambia fase según dueño activo e invalida guía/remisión cuando corresponde.'],
                ['nombre' => 'Error CEDIS / empaque', 'detalle' => 'EN_CEDIS → INCIDENCIA_CEDIS (Error CEDIS). Sigue siendo empacable tras resolver o continuar.'],
                ['nombre' => 'Resguardo', 'detalle' => 'Flag es_resguardo bloquea empaque y guía hasta liberar. estatus_envio puede ser pendiente_liberacion.'],
                ['nombre' => 'Municipio diferido / anexo', 'detalle' => 'Sin costo al enviar: pendiente_regularizacion → anexar pago → revisión auxiliar.'],
            ],
        ];
    }

    /** @return list<array{fase: string, etiqueta: string, nota: string}> */
    private static function estatus(): array
    {
        return [
            ['fase' => 'BORRADOR', 'etiqueta' => 'Borrador', 'nota' => 'Editable por vendedora; eliminable'],
            ['fase' => 'PENDIENTE_AUXILIAR', 'etiqueta' => 'Pendiente Auxiliar', 'nota' => 'Auditoría: pago + remisión'],
            ['fase' => 'EN_CEDIS', 'etiqueta' => 'En CEDIS', 'nota' => 'Empaque / reportar error / apartado'],
            ['fase' => 'RECHAZADO_VENDEDORA', 'etiqueta' => 'Rechazado', 'nota' => 'Corregir y reenviar'],
            ['fase' => 'INCIDENCIA_CEDIS', 'etiqueta' => 'Error CEDIS', 'nota' => 'Problema de empaque/pesaje reportado'],
            ['fase' => 'PENDIENTE_DE_GUIA', 'etiqueta' => 'Pendiente de guía', 'nota' => 'Empacado; falta rastreo'],
            ['fase' => 'PENDIENTE_DE_ENVIO', 'etiqueta' => 'Pendiente de envío', 'nota' => 'Listo para marcar enviado'],
            ['fase' => 'ENVIADO', 'etiqueta' => 'Enviado', 'nota' => 'Fin operativo actual'],
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
                    ['titulo' => 'Pesaje CEDIS (si aplica)', 'detalle' => 'Si el origen requiere logística (y no es complementario), solicita pesaje. CEDIS responde con peso, cajas y tipo de caja. Sin pesaje no podrás enviar.'],
                    ['titulo' => 'Completar campos', 'detalle' => 'Cliente, origen, banco, almacén, mercancía, comprobante de pago, paquetería, tipo de guía, reexpedición, CP y domicilio (o dirección verificada).'],
                    ['titulo' => 'Enviar', 'detalle' => 'Pasa a PENDIENTE_AUXILIAR. Se limpia remisión/validación de pago previas y se notifica al auxiliar.'],
                    ['titulo' => 'Si te rechazan o reportan error', 'detalle' => 'Aparece en RECHAZADAS. Corrige los campos marcados y reenvía.'],
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
                    ['titulo' => 'Validar pago', 'detalle' => 'Marca pago_validado. Obligatorio antes de aprobar.'],
                    ['titulo' => 'Subir remisión PDF', 'detalle' => 'Documento tipo remisión. Obligatorio antes de aprobar.'],
                    ['titulo' => 'Aprobar', 'detalle' => 'Envía a EN_CEDIS (o a pendiente de guía si queda error de guías en cola y ya estaba empacado).'],
                    ['titulo' => 'Rechazar', 'detalle' => 'Motivo obligatorio → RECHAZADO_VENDEDORA.'],
                    ['titulo' => 'Reportar error de datos', 'detalle' => 'Selecciona campos incorrectos. No uses remisión vía este flujo estando en pendiente auxiliar: corrígela en tu bandeja.'],
                ],
                'elementos' => [
                    ['nombre' => 'Validar pago', 'uso' => 'Flag de auditoría; no cambia fase.'],
                    ['nombre' => 'Remisión PDF', 'uso' => 'Adjuntar / eliminar remisión.'],
                    ['nombre' => 'Aprobar / Rechazar', 'uso' => 'Transiciones principales de auditoría.'],
                    ['nombre' => 'Liberar resguardo', 'uso' => 'Con captura de costo/comprobante cuando aplica.'],
                    ['nombre' => 'Anexos de envío', 'uso' => 'Aprobar o rechazar regularización de costo.'],
                ],
                'errores_que_te_llegan' => [
                    'Error de remisión (campos remision / folio_remision) desde CEDIS o guías.',
                    'Alertas de error CEDIS / empaque (informativas).',
                ],
            ],
            [
                'id' => 'cedis',
                'cargo' => 'CEDIS',
                'titulo' => 'Control de empaque y envío',
                'ruta' => '/control-pedidos/cedis',
                'resumen' => 'Respondes pesajes, empacas (incluye grupo principal+complementos), reportas errores, apartas resguardos y marcas envíos.',
                'pasos' => [
                    ['titulo' => 'Responder pesaje', 'detalle' => 'Peso, cajas, tipo de caja → pesaje_listo para la vendedora.'],
                    ['titulo' => 'Empacar', 'detalle' => 'Requiere pago validado + remisión. Bloqueado si es_resguardo abierto. Destino: PENDIENTE_DE_GUIA o PENDIENTE_DE_ENVIO.'],
                    ['titulo' => 'Reportar error', 'detalle' => 'Campos incorrectos (incluye CEDIS) → dueño correspondiente; Error CEDIS si es empaque/pesaje.'],
                    ['titulo' => 'Marcar enviado', 'detalle' => 'Solo en PENDIENTE_DE_ENVIO, empacado, y con guía si ofrece rastreo.'],
                    ['titulo' => 'Revertir empacado', 'detalle' => 'Solo sin número de guía asignado; vuelve a EN_CEDIS.'],
                ],
                'elementos' => [
                    ['nombre' => 'Marcar empacado', 'uso' => 'Avanza fase según rastreo/guía.'],
                    ['nombre' => 'Marcar enviado', 'uso' => 'Cierre operativo → ENVIADO.'],
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
                    ['titulo' => 'Asignar guía (post-empaque)', 'detalle' => 'PENDIENTE_DE_GUIA → PENDIENTE_DE_ENVIO; notifica a CEDIS.'],
                    ['titulo' => 'Guía pre-empaque', 'detalle' => 'Puedes capturar en EN_CEDIS sin cambiar fase; al empacar irá directo a pendiente de envío.'],
                    ['titulo' => 'PDF de guía', 'detalle' => 'Adjunta el documento de la paquetería.'],
                    ['titulo' => 'Importar / exportar', 'detalle' => 'Plantilla CSV para carga masiva.'],
                    ['titulo' => 'Actualizar guía', 'detalle' => 'Corrección; puede marcar retraso y avisar a CEDIS.'],
                ],
                'elementos' => [
                    ['nombre' => 'Asignar guía', 'uso' => 'Número de rastreo obligatorio.'],
                    ['nombre' => 'Guía PDF', 'uso' => 'Alta / baja de documento.'],
                    ['nombre' => 'Importar', 'uso' => 'Carga masiva de guías.'],
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
            'vendedora' => ['pedido_rechazado_auxiliar', 'pedido_error_datos', 'pedido_enviado', 'pedido_pesaje_listo'],
            'auxiliar' => ['pedido_pendiente_auxiliar', 'pedido_error_remision', 'pedido_error_cedis', 'pedido_incidencia_cedis'],
            'cedis' => ['pedido_aprobado', 'pedido_consulta_pesaje', 'pedido_pendiente_envio', 'pedido_guia_asignada', 'pedido_error_guia', 'pedido_error_cedis', 'pedido_guia_retraso'],
            'guias' => ['pedido_pendiente_guia', 'pedido_error_guia'],
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
            'pedido_pendiente_guia' => 'Empacado; falta captura de guía.',
            'pedido_pendiente_envio' => 'Listo para marcar enviado.',
            'pedido_guia_asignada' => 'Guía asignada; pendiente de envío.',
            'pedido_enviado' => 'Pedido marcado como enviado.',
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
                'detalle' => 'Borrador → pesaje → enviar → validar pago + remisión → aprobar → empacar (con o sin guía) → asignar guía si falta → marcar enviado.',
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
        ];

        return array_values(array_filter(
            $todos,
            fn (array $e) => count(array_intersect($e['secciones'], $ids)) > 0
        ));
    }
}
