<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\ControlPedidos\PedidoBmaTareaProducto;
use App\Models\Producto;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\RegistrarPedidos\DestinoRevisionRegistrarPedidos;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Registra la decisión del cliente y la devuelve a quien ejecuta. No vuelve disponible una pieza inexistente. */
final class RegistrarDecisionClienteTiendaService
{
    public const MENSAJE_INEXISTENTE = 'Una pieza inexistente no se vuelve disponible por aceptarla.';

    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificar,
        private CompromisoInventarioTienda $inventario,
    ) {}

    public function ejecutar(PedidoBma $pedido, User $actor, int $revisionId, string $accion, string $nota = '', string $skuSustituto = ''): PedidoBma
    {
        if (! VisibilidadPedidoBma::puedeMutarComoVendedora($actor, $pedido)) {
            throw ValidationException::withMessages([
                'permiso' => 'Quien registró el pedido informa la decisión del cliente.',
            ]);
        }

        $accion = trim($accion);
        $nota = trim($nota);
        if (! in_array($accion, PedidoBmaRevisionProducto::RESOLUCIONES_DECISION_TIENDA, true)) {
            throw ValidationException::withMessages(['accion' => 'Elija la decisión del cliente.']);
        }

        return DB::transaction(function () use ($pedido, $actor, $revisionId, $accion, $nota, $skuSustituto) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $revision = PedidoBmaRevisionProducto::query()
                ->where('pedido_bma_id', $pedido->id)
                ->whereKey($revisionId)
                ->lockForUpdate()
                ->first();
            if (! $revision) {
                throw ValidationException::withMessages(['revision' => 'No se encontró la pieza con excepción.']);
            }

            if ($revision->resolucion === $accion) {
                return $pedido->fresh(['revisionesProducto', 'estatus']);
            }
            if ($revision->resolucion !== null && $revision->resolucion !== '') {
                throw ValidationException::withMessages([
                    'revision' => 'Esa pieza ya tiene una decisión. No se sustituye en silencio.',
                ]);
            }

            $this->validar($pedido, $revision, $accion, $nota);
            if ($accion === PedidoBmaRevisionProducto::RESOLUCION_SUSTITUIR) {
                $this->reservarSustituta($pedido, $revision, $skuSustituto);
            }

            $revision->update([
                'resolucion' => $accion,
                'resolucion_nota' => $nota !== '' ? $nota : null,
                'resolucion_por_id' => $actor->id,
                'resolucion_at' => now(),
                'retirada_at' => in_array($accion, [
                    PedidoBmaRevisionProducto::RESOLUCION_RETIRAR,
                    PedidoBmaRevisionProducto::RESOLUCION_DESISTIR,
                ], true) ? now() : null,
            ]);

            if (in_array($accion, [
                PedidoBmaRevisionProducto::RESOLUCION_RETIRAR,
                PedidoBmaRevisionProducto::RESOLUCION_DESISTIR,
            ], true)) {
                $this->soltarPieza($pedido, (string) $revision->sku);
            }
            $pedido->refresh();
            $this->cerrarEsperaSiCorresponde($pedido, $actor);
            $estatusId = $pedido->catalogo_estatus_pedido_id;
            if ($estatusId) {
                $this->historial->ejecutar(
                    $pedido->id,
                    $actor->id,
                    $estatusId,
                    $estatusId,
                    sprintf(
                        'Decisión del cliente: %s — %s.',
                        $revision->descripcion_producto,
                        PedidoBmaRevisionProducto::LABELS_RESOLUCION[$accion] ?? $accion
                    ),
                    AccionesHistorialPedidoBma::DECISION_SIN_EXISTENCIA
                );
            }

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $area = (string) ($json['area_revision'] ?? '');
            $permiso = $area === DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA
                ? 'control_pedidos.tienda.ver'
                : 'control_pedidos.cedis';
            $this->notificar->ejecutar(
                $pedido->fresh(['cliente', 'vendedor']),
                'pedido_decision_cliente',
                'Hay una decisión del cliente. Continúe solo con las piezas aceptadas.',
                [$permiso],
                $actor->id,
                true
            );

            return $pedido->fresh(['revisionesProducto', 'estatus']);
        });
    }

    private function validar(PedidoBma $pedido, PedidoBmaRevisionProducto $revision, string $accion, string $nota): void
    {
        $inexistente = $revision->estado_fisico === PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA
            || ((int) $revision->cantidad_encontrada === 0 && $revision->cantidad_solicitada !== null);

        if ($accion === PedidoBmaRevisionProducto::RESOLUCION_ACEPTAR_DISPONIBLE && $inexistente) {
            throw ValidationException::withMessages(['accion' => self::MENSAJE_INEXISTENTE]);
        }

        if ($accion === PedidoBmaRevisionProducto::RESOLUCION_ACEPTAR_CONDICION) {
            if ($inexistente || ! in_array($revision->estado_fisico, [
                PedidoBmaRevisionProducto::ESTADO_REGULAR,
                PedidoBmaRevisionProducto::ESTADO_MALO,
                PedidoBmaRevisionProducto::ESTADO_DANADO,
            ], true)) {
                throw ValidationException::withMessages([
                    'accion' => 'Aceptar la condición solo aplica a una pieza existente con detalle.',
                ]);
            }
        }

        if (in_array($accion, [
            PedidoBmaRevisionProducto::RESOLUCION_ALTERNATIVA,
            PedidoBmaRevisionProducto::RESOLUCION_DESISTIR,
        ], true) && $nota === '') {
            throw ValidationException::withMessages(['nota' => 'Indique qué acordó con el cliente.']);
        }

        if ($accion === PedidoBmaRevisionProducto::RESOLUCION_SUSTITUIR
            && ! $pedido->tienePdfPedido()
            && ! $pedido->tieneAnexoPiezas()) {
            throw ValidationException::withMessages([
                'archivo' => 'Para sustituir, adjunte el pedido actualizado. La versión anterior se conserva.',
            ]);
        }
    }

    private function cerrarEsperaSiCorresponde(PedidoBma $pedido, User $actor): void
    {
        $abiertas = $pedido->revisionesProducto()
            ->whereNull('resolucion')
            ->whereIn('estado_fisico', [
                PedidoBmaRevisionProducto::ESTADO_REGULAR,
                PedidoBmaRevisionProducto::ESTADO_MALO,
                PedidoBmaRevisionProducto::ESTADO_DANADO,
                PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA,
            ])
            ->exists();
        $this->publicarContinuacion($pedido);
        if ($abiertas) {
            return;
        }

        $pedido->refresh();
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $revision = is_array($json['revision_disponibilidad'] ?? null) ? $json['revision_disponibilidad'] : [];
        $revision['estado'] = 'decision_registrada';
        $revision['decision_at'] = now()->toIso8601String();
        $revision['decision_por_id'] = $actor->id;
        $json['revision_disponibilidad'] = $revision;
        $pedido->update([
            'solicitud_preparacion_json' => $json,
            'tiene_observaciones_fisicas' => $pedido->revisionesProducto()
                ->where('resolucion', PedidoBmaRevisionProducto::RESOLUCION_ALTERNATIVA)
                ->exists(),
        ]);
    }

    private function publicarContinuacion(PedidoBma $pedido): void
    {
        $pedido->refresh();
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $aceptadas = $pedido->revisionesProducto()
            ->whereIn('resolucion', [
                PedidoBmaRevisionProducto::RESOLUCION_ACEPTAR_DISPONIBLE,
                PedidoBmaRevisionProducto::RESOLUCION_ACEPTAR_CONDICION,
            ])
            ->pluck('sku')
            ->filter()
            ->values()
            ->all();
        $retiradas = $pedido->revisionesProducto()
            ->whereIn('resolucion', [
                PedidoBmaRevisionProducto::RESOLUCION_RETIRAR,
                PedidoBmaRevisionProducto::RESOLUCION_DESISTIR,
            ])
            ->pluck('sku')
            ->filter()
            ->values()
            ->all();
        $json['continuacion_ejecutor'] = [
            'estado' => 'vigente',
            'piezas_aceptadas' => $aceptadas,
            'piezas_retiradas' => $retiradas,
            'texto' => 'Continúe solo con las piezas aceptadas. Las retiradas ya no están comprometidas.',
        ];
        $pedido->update(['solicitud_preparacion_json' => $json]);

        $tarea = InstruccionEntrega::abiertas($pedido->id)->first();
        if ($tarea) {
            $tarea->update(['observaciones_solicitud' => $json['continuacion_ejecutor']['texto']]);
        }
    }

    private function reservarSustituta(PedidoBma $pedido, PedidoBmaRevisionProducto $revision, string $skuSustituto): void
    {
        $skuSustituto = trim($skuSustituto);
        if ($skuSustituto === '' || $skuSustituto === (string) $revision->sku) {
            throw ValidationException::withMessages([
                'sku_sustituto' => 'Indique una pieza sustituta distinta para confirmar su disponibilidad.',
            ]);
        }
        $producto = Producto::query()->where('sku', $skuSustituto)->first();
        $tarea = InstruccionEntrega::abiertas($pedido->id)->first();
        $almacenId = (int) ($tarea?->almacen_id ?: $pedido->almacen_id);
        if (! $producto || $almacenId < 1) {
            throw ValidationException::withMessages([
                'sku_sustituto' => 'La pieza sustituta no tiene disponibilidad confirmada.',
            ]);
        }
        $this->inventario->comprometer(
            $pedido,
            $almacenId,
            (int) $producto->id,
            (string) max(1, (int) ($revision->cantidad_solicitada ?: 1)),
            'sustituta:'.$revision->id,
            $skuSustituto
        );
        $this->soltarPieza($pedido, (string) $revision->sku);
    }

    private function soltarPieza(PedidoBma $pedido, string $sku): void
    {
        if ($sku === '') {
            return;
        }
        $pedido->refresh();
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        foreach ((array) ($json['compromisos_inventario'] ?? []) as $clave => $fila) {
            if (is_array($fila) && ($fila['sku'] ?? null) === $sku && ($fila['activo'] ?? false) === true) {
                $this->inventario->liberarClave($pedido, (string) $clave);
            }
        }
        $tarea = InstruccionEntrega::abiertas($pedido->id)->first();
        if (! $tarea) {
            return;
        }
        PedidoBmaTareaProducto::query()
            ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
            ->where('sku', $sku)
            ->update([
                'cantidad_encontrada' => 0,
                'observacion' => 'Retirada por decisión del cliente. Ya no está comprometida.',
            ]);
    }
}
