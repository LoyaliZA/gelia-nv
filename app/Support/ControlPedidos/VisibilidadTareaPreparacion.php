<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use Illuminate\Database\Eloquent\Builder;

final class VisibilidadTareaPreparacion
{
    public static function filtrarTienda(Builder $query, User $usuario, PreparacionTiendaConfig $config): Builder
    {
        $almacenes = $config->almacenesHabilitados();
        if ($almacenes !== []) {
            $query->whereIn('almacen_id', $almacenes);
        }

        return $query->where('area_responsable_codigo', 'TIENDA');
    }

    public static function puedeVer(User $usuario, PedidoBmaTareaPreparacion $tarea): bool
    {
        if ($usuario->can('control_pedidos.tienda.ver')) {
            return true;
        }

        return self::puedeVerComoVentas($usuario, $tarea);
    }

    public static function puedeVerComoVentas(User $usuario, PedidoBmaTareaPreparacion $tarea): bool
    {
        $tarea->loadMissing('pedido');
        $pedido = $tarea->pedido;
        if (! $pedido) {
            return false;
        }

        return VisibilidadPedidoBma::puedeConsultar($usuario, $pedido);
    }

    public static function payloadTienda(PedidoBmaTareaPreparacion $tarea, ?User $usuario = null): array
    {
        $tarea->loadMissing([
            'pedido.cliente', 'pedido.vendedor', 'modalidad', 'almacen', 'productos', 'asignadaA',
            'solicitudTraspaso.estado', 'enviadaCedisPor', 'recibidaCedisPor', 'atendidaPor',
            'paqueteria', 'caratulas',
        ]);

        $pedido = $tarea->pedido;
        $verTelefono = $usuario?->can('control_pedidos.tienda.ver_identificacion')
            || $usuario?->can('control_pedidos.tienda.ver');
        $caratula = $tarea->caratulas
            ->sortByDesc('version')
            ->first(fn ($c) => in_array($c->estado, [PedidoBmaCaratula::ESTADO_GENERADA, PedidoBmaCaratula::ESTADO_COLOCADA], true));

        return [
            'id' => $tarea->id,
            'estado' => $tarea->estado,
            'estado_label' => PedidoBmaTareaPreparacion::LABELS[$tarea->estado] ?? $tarea->estado,
            'version' => $tarea->version,
            'fecha_limite' => $tarea->fecha_limite?->toIso8601String(),
            'solicitada_at' => $tarea->solicitada_at?->toIso8601String(),
            'atendida_at' => $tarea->atendida_at?->toIso8601String(),
            'tiempo_atencion' => self::tiempoAtencion($tarea),
            'observaciones_solicitud' => $tarea->observaciones_solicitud,
            'observaciones_respuesta' => $tarea->observaciones_respuesta,
            'modalidad' => $tarea->modalidad ? [
                'id' => $tarea->modalidad->id,
                'codigo' => $tarea->modalidad->codigo,
                'nombre' => $tarea->modalidad->nombre,
                'es_transferencia' => $tarea->modalidad->esTransferencia(),
                'es_envio_municipio' => $tarea->modalidad->esEnvioMunicipio(),
            ] : null,
            'almacen' => $tarea->almacen ? [
                'id' => $tarea->almacen->id,
                'codigo' => $tarea->almacen->codigo,
                'nombre' => $tarea->almacen->nombre,
            ] : null,
            'pedido' => $pedido ? [
                'id' => $pedido->id,
                'folio' => $pedido->folio,
                'folio_remision' => $pedido->folio_remision,
                'folio_visible' => \App\Models\ControlPedidos\PedidoBmaReferencia::visible($pedido)['folio'],
                'folio_etiqueta' => \App\Models\ControlPedidos\PedidoBmaReferencia::visible($pedido)['etiqueta'],
                'contacto_nombre' => $pedido->contacto_nombre_snapshot,
                'prioridad_md' => (bool) $pedido->prioridad_md,
                'origen_solicitud' => $pedido->origen_solicitud,
                'cliente_nombre' => $pedido->cliente?->nombre_comercial ?: $pedido->cliente?->nombre ?: $pedido->contacto_nombre_snapshot,
                'cantidad_piezas' => $pedido->cantidad_piezas,
            ] : null,
            'responsable' => $tarea->asignadaA ? [
                'id' => $tarea->asignadaA->id,
                'name' => $tarea->asignadaA->name,
            ] : null,
            'productos' => $tarea->productos->map(fn ($p) => [
                'id' => $p->id,
                'pedido_bma_origen_id' => $p->pedido_bma_origen_id,
                'sku' => $p->sku,
                'descripcion_snapshot' => $p->descripcion_snapshot,
                'cantidad_solicitada' => $p->cantidad_solicitada,
                'cantidad_encontrada' => $p->cantidad_encontrada,
                'estado_fisico' => $p->estado_fisico,
                'observacion' => $p->observacion,
                'orden' => $p->orden,
            ])->values()->all(),
            'piezas_solicitadas' => $tarea->piezasSolicitadas(),
            'requiere_traslado_cedis' => (bool) $tarea->requiere_traslado_cedis,
            'peso_real_kg' => $tarea->peso_real_kg,
            'peso_volumetrico_kg' => $tarea->peso_volumetrico_kg,
            'catalogo_tipo_caja_id' => $tarea->catalogo_tipo_caja_id,
            'observaciones_fisicas' => $tarea->observaciones_fisicas,
            'intento_traslado' => $tarea->intento_traslado,
            'enviada_cedis_at' => $tarea->enviada_cedis_at?->toIso8601String(),
            'recibida_cedis_at' => $tarea->recibida_cedis_at?->toIso8601String(),
            'motivo_rechazo_cedis' => $tarea->motivo_rechazo_cedis,
            'solicitud_traspaso' => $tarea->relationLoaded('solicitudTraspaso') && $tarea->solicitudTraspaso ? [
                'id' => $tarea->solicitudTraspaso->id,
                'folio' => $tarea->solicitudTraspaso->folio,
                'folio_traspaso' => $tarea->solicitudTraspaso->folio_traspaso,
                'estado' => $tarea->solicitudTraspaso->estado?->nombre,
            ] : null,
            'progreso_traslado' => self::progresoTraslado($tarea),
            'entrega_municipal' => $tarea->modalidad?->esEnvioMunicipio() ? [
                'destinatario_nombre' => $tarea->destinatario_nombre,
                'destinatario_telefono' => $verTelefono ? $tarea->destinatario_telefono : self::enmascararTelefono($tarea->destinatario_telefono),
                'municipio_destino' => $tarea->municipio_destino,
                'direccion_referencia' => $tarea->direccion_referencia,
                'modalidad_cobro' => $tarea->modalidad_cobro,
                'destinatario_es_cliente' => (bool) $tarea->destinatario_es_cliente,
                'transporte' => $tarea->paqueteria ? [
                    'id' => $tarea->paqueteria->id,
                    'nombre' => $tarea->paqueteria->nombre,
                ] : null,
            ] : null,
            'caratula' => $caratula ? [
                'id' => $caratula->id,
                'version' => $caratula->version,
                'estado' => $caratula->estado,
                'generada_at' => $caratula->generada_at?->toIso8601String(),
                'colocada_at' => $caratula->colocada_at?->toIso8601String(),
                'modalidad_cobro' => $caratula->modalidad_cobro,
                'municipio_destino' => $caratula->municipio_destino,
                'destinatario_nombre' => $caratula->destinatario_nombre,
            ] : null,
            'progreso_caratula' => self::progresoCaratula($tarea),
            'apartado' => self::payloadApartado($tarea),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function payloadApartado(PedidoBmaTareaPreparacion $tarea): ?array
    {
        if (! $tarea->exists) {
            return null;
        }
        if (! $tarea->relationLoaded('cumplimientoFisico')) {
            $tarea->load('cumplimientoFisico');
        }
        $apartado = $tarea->cumplimientoFisico;
        if (! $apartado) {
            return null;
        }

        return [
            'estado' => $apartado->estado,
            'estado_label' => PedidoBmaCumplimientoFisico::LABELS[$apartado->estado] ?? $apartado->estado,
            'cantidad' => (int) $apartado->cantidad,
            'ubicacion' => $apartado->ubicacion,
            'vence_at' => $apartado->vence_at?->toIso8601String(),
            'version' => (int) $apartado->version,
            'prorroga_aplicada' => (bool) $apartado->prorroga_aplicada,
            'condicion_cobro' => $apartado->condicion_cobro,
            'pago_confirmado_at' => $apartado->pago_confirmado_at?->toIso8601String(),
            'folio_operacion' => $apartado->folio_operacion,
            'salida_autorizada_at' => $apartado->salida_autorizada_at?->toIso8601String(),
            'entregada_at' => $apartado->entregada_at?->toIso8601String(),
            'receptor_nombre' => $apartado->receptor_nombre,
            'resguardo_pdv_id' => $apartado->resguardo_pdv_id,
            'entrega_pdv_id' => $apartado->entrega_pdv_id,
            'bultos_salida' => $apartado->bultos_salida,
            'empacado_at' => $apartado->empacado_at?->toIso8601String(),
            'despachada_at' => $apartado->despachada_at?->toIso8601String(),
            'vendedor_id' => $tarea->pedido?->vendedor_id,
            'inventario_sincronizado' => false,
            'aviso' => 'Apartado físico operativo. No descuenta ni sincroniza el inventario.',
        ];
    }

    /**
     * @return array{minutos: ?int, etiqueta: string, en_curso: bool}
     */
    public static function tiempoAtencion(PedidoBmaTareaPreparacion $tarea): array
    {
        $inicio = $tarea->solicitada_at;
        if (! $inicio) {
            return ['minutos' => null, 'etiqueta' => '—', 'en_curso' => false];
        }

        $terminales = [
            PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
            PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
            PedidoBmaTareaPreparacion::ESTADO_LIBERADA,
            PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
        ];
        $fin = $tarea->atendida_at;
        $enCurso = ! in_array($tarea->estado, $terminales, true);
        if ($fin === null && ! $enCurso) {
            $fin = $tarea->recibida_cedis_at ?? $tarea->enviada_cedis_at;
        }
        if ($fin === null && $enCurso) {
            $fin = now();
        }
        if ($fin === null) {
            return ['minutos' => null, 'etiqueta' => '—', 'en_curso' => $enCurso];
        }

        $minutos = max(0, (int) $inicio->diffInMinutes($fin));
        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;
        $etiqueta = $horas > 0 ? "{$horas} h {$resto} min" : "{$minutos} min";

        return ['minutos' => $minutos, 'etiqueta' => $etiqueta, 'en_curso' => $enCurso];
    }

    public static function enmascararTelefono(?string $tel): ?string
    {
        if ($tel === null || $tel === '') {
            return $tel;
        }
        $digits = preg_replace('/\D+/', '', $tel) ?? '';
        if (strlen($digits) < 4) {
            return '****';
        }

        return str_repeat('*', max(0, strlen($digits) - 4)).substr($digits, -4);
    }

    /**
     * @return list<array{clave: string, label: string, hecho: bool, en: ?string, por: ?string}>
     */
    public static function progresoTraslado(PedidoBmaTareaPreparacion $tarea): array
    {
        if (! $tarea->requiere_traslado_cedis) {
            return [];
        }

        $orden = [
            PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO,
            PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO,
            PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
        ];
        $idx = array_search($tarea->estado, $orden, true);
        if ($tarea->estado === PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA
            || $tarea->estado === PedidoBmaTareaPreparacion::ESTADO_RECHAZADA_CEDIS) {
            $idx = 2;
        }

        return [
            [
                'clave' => 'preparando',
                'label' => 'Preparando en Tienda',
                'hecho' => $idx !== false && $idx >= 1,
                'en' => $tarea->solicitada_at?->toIso8601String(),
                'por' => null,
            ],
            [
                'clave' => 'lista',
                'label' => 'Lista para traslado',
                'hecho' => $idx !== false && $idx >= 2,
                'en' => $tarea->atendida_at?->toIso8601String(),
                'por' => $tarea->atendidaPor?->name,
            ],
            [
                'clave' => 'en_traslado',
                'label' => 'En traslado',
                'hecho' => $idx !== false && $idx >= 3,
                'en' => $tarea->enviada_cedis_at?->toIso8601String(),
                'por' => $tarea->enviadaCedisPor?->name,
            ],
            [
                'clave' => 'recibida',
                'label' => 'Recibida en CEDIS',
                'hecho' => $tarea->estado === PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
                'en' => $tarea->recibida_cedis_at?->toIso8601String(),
                'por' => $tarea->recibidaCedisPor?->name,
            ],
        ];
    }

    /**
     * @return list<array{clave: string, label: string, hecho: bool, en: ?string, por: ?string}>
     */
    public static function progresoCaratula(PedidoBmaTareaPreparacion $tarea): array
    {
        if (! $tarea->modalidad?->esEnvioMunicipio()) {
            return [];
        }

        $tarea->loadMissing(['caratulas', 'atendidaPor']);
        $caratula = $tarea->caratulas->sortByDesc('version')->first(
            fn ($c) => in_array($c->estado, [PedidoBmaCaratula::ESTADO_GENERADA, PedidoBmaCaratula::ESTADO_COLOCADA], true)
        );

        $orden = [
            PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA,
            PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
        ];
        $idx = array_search($tarea->estado, $orden, true);

        return [
            [
                'clave' => 'preparando',
                'label' => 'Preparando en Tienda',
                'hecho' => $idx !== false && $idx >= 1,
                'en' => $tarea->solicitada_at?->toIso8601String(),
                'por' => null,
            ],
            [
                'clave' => 'lista_caratula',
                'label' => 'Lista para carátula',
                'hecho' => $idx !== false && $idx >= 2,
                'en' => $tarea->atendida_at?->toIso8601String(),
                'por' => $tarea->atendidaPor?->name,
            ],
            [
                'clave' => 'caratula_generada',
                'label' => 'Carátula generada',
                'hecho' => (bool) $caratula,
                'en' => $caratula?->generada_at?->toIso8601String(),
                'por' => null,
            ],
            [
                'clave' => 'colocada',
                'label' => 'Carátula colocada',
                'hecho' => $tarea->estado === PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA
                    || $caratula?->estado === PedidoBmaCaratula::ESTADO_COLOCADA,
                'en' => $caratula?->colocada_at?->toIso8601String(),
                'por' => null,
            ],
        ];
    }

    public static function pedidoTienePreparacionActiva(PedidoBma $pedido): bool
    {
        return $pedido->tareasPreparacion()
            ->whereIn('estado', [
                PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
                PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
                PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA,
                PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA,
                PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA,
            ])
            ->exists();
    }
}
