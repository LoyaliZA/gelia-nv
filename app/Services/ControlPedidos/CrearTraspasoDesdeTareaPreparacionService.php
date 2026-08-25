<?php

namespace App\Services\ControlPedidos;

use App\Models\Almacen;
use App\Models\AuditoriaSolicitudTraspaso;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoHorarioTraspaso;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\SolicitudTraspaso;
use App\Models\SolicitudTraspasoProducto;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrearTraspasoDesdeTareaPreparacionService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    public function ejecutar(PedidoBmaTareaPreparacion $tarea, User $usuario): SolicitudTraspaso
    {
        $tarea->loadMissing(['pedido.cliente', 'productos', 'almacen', 'modalidad']);

        if (! $tarea->requiere_traslado_cedis) {
            throw ValidationException::withMessages(['tarea' => 'Esta tarea no requiere traslado a CEDIS.']);
        }

        if ($tarea->solicitud_traspaso_id) {
            $existente = SolicitudTraspaso::query()->find($tarea->solicitud_traspaso_id);
            if ($existente) {
                return $existente->load(['productos', 'estado', 'almacenOrigen', 'cliente']);
            }
        }

        $porTarea = SolicitudTraspaso::query()
            ->where('tarea_preparacion_id', $tarea->id)
            ->first();
        if ($porTarea) {
            $tarea->update(['solicitud_traspaso_id' => $porTarea->id]);

            return $porTarea->load(['productos', 'estado', 'almacenOrigen', 'cliente']);
        }

        if ($tarea->productos->isEmpty()) {
            throw ValidationException::withMessages(['productos' => 'La tarea no tiene productos para trasladar.']);
        }

        $pedido = $tarea->pedido;
        if (! $pedido?->cliente_id) {
            throw ValidationException::withMessages(['cliente' => 'El pedido no tiene cliente para el traspaso.']);
        }

        $almacen = Almacen::query()->find($tarea->almacen_id);
        if (! $almacen) {
            throw ValidationException::withMessages(['almacen_id' => 'Almacén de origen inválido.']);
        }

        return DB::transaction(function () use ($tarea, $usuario, $pedido, $almacen) {
            $pedido->loadMissing(['vendedor.departamentos', 'vendedor.area.departamento', 'estatus', 'cliente']);

            $estadoPendiente = CatalogoEstadoSolicitud::where('nombre', 'Pendiente')->firstOrFail();
            $horario = CatalogoHorarioTraspaso::resolverParaHora(now()->format('H:i:s'));
            $fechaEstimada = $horario
                ? now()->startOfDay()->addDays((int) $horario->dias_para_entrega)->toDateString()
                : now()->toDateString();

            $totalPiezas = (int) $tarea->productos->sum(fn ($p) => max(1, (int) ($p->cantidad_encontrada ?? $p->cantidad_solicitada)));

            $intento = (int) $tarea->intento_traslado + 1;

            $solicitud = SolicitudTraspaso::query()->create([
                'folio' => SolicitudTraspaso::generarFolio(),
                'tarea_preparacion_id' => $tarea->id,
                'origen_codigo' => 'GESTION_PEDIDO',
                'vendedor_id' => $pedido->vendedor_id ?? $usuario->id,
                'departamento_id' => $pedido->vendedor?->departamentos?->first()?->id
                    ?? $pedido->vendedor?->area?->departamento_id,
                'cliente_id' => $pedido->cliente_id,
                'almacen_origen_id' => $almacen->id,
                'catalogo_estado_solicitud_id' => $estadoPendiente->id,
                'catalogo_horario_traspaso_id' => $horario?->id,
                'fecha_entrega_estimada' => $fechaEstimada,
                'total_piezas' => max(1, $totalPiezas),
            ]);

            foreach ($tarea->productos as $p) {
                SolicitudTraspasoProducto::query()->create([
                    'solicitud_traspaso_id' => $solicitud->id,
                    'producto_id' => $p->producto_id,
                    'sku' => $p->sku ?: ('SNAP-'.$p->id),
                    'descripcion' => $p->descripcion_snapshot,
                    'piezas' => max(1, (int) ($p->cantidad_encontrada ?? $p->cantidad_solicitada)),
                ]);
            }

            AuditoriaSolicitudTraspaso::create([
                'solicitud_traspaso_id' => $solicitud->id,
                'usuario_id' => $usuario->id,
                'estado_anterior_id' => null,
                'estado_nuevo_id' => $estadoPendiente->id,
                'motivo_reporte' => 'Traspaso generado desde preparación Tienda (Gestión de pedido).',
                'datos_snapshot' => [
                    'tarea_preparacion_id' => $tarea->id,
                    'pedido_bma_id' => $pedido->id,
                    'intento' => $intento,
                ],
            ]);

            $tarea->update([
                'solicitud_traspaso_id' => $solicitud->id,
                'intento_traslado' => $intento,
            ]);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->estatus->id,
                $pedido->estatus->id,
                "Se generó traspaso {$solicitud->folio} desde preparación Tienda.",
                AccionesHistorialPedidoBma::TRASLADO_PREPARACION_CREADO
            );

            $this->notificarService->ejecutar(
                $pedido->fresh(['cliente', 'vendedor']),
                'pedido_preparacion_tienda_lista_traslado',
                "La mercancía del pedido está lista para traslado ({$solicitud->folio}).",
                ['control_pedidos.tienda.trasladar'],
                $usuario->id,
                true,
                ['url' => '/control-pedidos/tienda?tarea='.$tarea->id]
            );

            return $solicitud->load(['productos', 'estado', 'almacenOrigen', 'cliente', 'horario']);
        });
    }
}
