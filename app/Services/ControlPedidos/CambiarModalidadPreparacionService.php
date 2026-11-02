<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaHistorial;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CambiarModalidadPreparacionService
{
    /** El traslado o la recepción ya empezaron: otra instrucción no los da por hechos. */
    private const ESTADOS_BLOQUEADOS = [
        PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO,
        PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
        PedidoBmaTareaPreparacion::ESTADO_LIBERADA,
        PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
    ];

    public function __construct(
        private PreparacionTiendaConfig $config,
        private CalcularRequisitosPreparacionService $requisitosService,
        private AsegurarCumplimientoFisicoService $asegurar,
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        string $codigoModalidad,
        string $motivo,
        ?int $versionEsperada = null,
    ): PedidoBmaTareaPreparacion {
        if (! $usuario->can('control_pedidos.preparacion.solicitar')
            && ! $usuario->can('control_pedidos.preparacion.corregir')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para cambiar la modalidad.',
            ]);
        }

        $motivo = trim($motivo);
        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Indique el motivo del cambio de modalidad.',
            ]);
        }

        if (! $this->config->modalidadPermitida($codigoModalidad)) {
            throw ValidationException::withMessages([
                'modalidad' => 'La modalidad seleccionada no está habilitada.',
            ]);
        }

        $tarea->loadMissing(['pedido.estatus', 'modalidad', 'productos']);
        $pedido = $tarea->pedido;
        if (! $pedido || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw ValidationException::withMessages([
                'pedido' => 'No puede cambiar la modalidad de este pedido.',
            ]);
        }

        if (in_array($tarea->estado, self::ESTADOS_BLOQUEADOS, true)) {
            throw ValidationException::withMessages([
                'estado' => 'Esta tarea ya no admite cambio de modalidad.',
            ]);
        }

        if ($tarea->modalidad?->codigo === $codigoModalidad) {
            throw ValidationException::withMessages([
                'modalidad' => 'Seleccione una modalidad distinta a la actual.',
            ]);
        }

        $modalidad = CatalogoModalidadPreparacionPedido::query()
            ->where('codigo', $codigoModalidad)
            ->where('activo', true)
            ->firstOrFail();

        return DB::transaction(function () use ($tarea, $usuario, $modalidad, $motivo, $versionEsperada) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($tarea->pedido_bma_id);
            $pedido->loadMissing('estatus');
            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);
            $tarea->load(['modalidad', 'productos', 'cumplimientoFisico']);

            $clave = hash('sha256', implode('|', [
                'sustituir-instruccion',
                $tarea->id,
                $modalidad->id,
                $motivo,
            ]));
            $repetida = PedidoBmaTareaPreparacion::query()
                ->where('idempotencia_clave', $clave)
                ->first();
            if ($repetida) {
                return $repetida->fresh(['modalidad', 'almacen', 'productos', 'pedido.cliente', 'cumplimientoFisico']);
            }

            if (in_array($tarea->estado, [
                PedidoBmaTareaPreparacion::ESTADO_SUSTITUIDA,
                PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
            ], true)) {
                throw ValidationException::withMessages([
                    'instruccion' => InstruccionEntrega::MENSAJE_OBSOLETA,
                ]);
            }

            if ($versionEsperada !== null && (int) $tarea->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otra persona modificó esta tarea. Actualice la página e intente de nuevo.',
                ]);
            }

            if (in_array($tarea->estado, self::ESTADOS_BLOQUEADOS, true)) {
                throw ValidationException::withMessages([
                    'estado' => 'El movimiento ya inició. La instrucción nueva no lo da por recibido ni por terminado.',
                ]);
            }

            $abiertas = InstruccionEntrega::abiertas((int) $pedido->id);
            if ($abiertas->count() > 1) {
                throw ValidationException::withMessages([
                    'instruccion' => InstruccionEntrega::MENSAJE_AMBIGUA,
                ]);
            }
            if ($abiertas->first() === null || (int) $abiertas->first()->id !== (int) $tarea->id) {
                throw ValidationException::withMessages([
                    'instruccion' => InstruccionEntrega::MENSAJE_OBSOLETA,
                ]);
            }

            if (InstruccionEntrega::entregadaAlTransporte($pedido)) {
                throw new \RuntimeException(InstruccionEntrega::MENSAJE_PAQUETERIA);
            }

            $fisico = $tarea->cumplimientoFisico;
            if (
                $tarea->modalidad?->esEnvioMunicipio()
                && $fisico?->estado === PedidoBmaCumplimientoFisico::ESTADO_DESPACHADA
                && $modalidad->esDestinoSucursal()
            ) {
                throw ValidationException::withMessages([
                    'modalidad' => InstruccionEntrega::MENSAJE_RECOLECCION_TERMINAL,
                ]);
            }

            $estadoAnterior = $tarea->estado;
            $tarea->update([
                'estado' => PedidoBmaTareaPreparacion::ESTADO_SUSTITUIDA,
                'version' => (int) $tarea->version + 1,
            ]);

            $nueva = PedidoBmaTareaPreparacion::query()->create([
                'pedido_bma_id' => $pedido->id,
                'catalogo_modalidad_preparacion_id' => $modalidad->id,
                'almacen_id' => $tarea->almacen_id,
                'area_responsable_codigo' => 'TIENDA',
                'estado' => PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
                'solicitada_por_id' => $usuario->id,
                'solicitada_at' => now(),
                'fecha_limite' => $this->requisitosService->calcularFechaLimite($modalidad),
                'observaciones_solicitud' => $motivo,
                'tarea_anterior_id' => $tarea->id,
                'idempotencia_clave' => $clave,
                'requiere_traslado_cedis' => $modalidad->requiereTrasladoCedisPorDefecto(),
            ]);

            PedidoBmaTareaHistorial::query()->create([
                'pedido_bma_tarea_preparacion_id' => $tarea->id,
                'usuario_id' => $usuario->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => PedidoBmaTareaPreparacion::ESTADO_SUSTITUIDA,
                'accion' => 'sustituir_instruccion',
                'comentario' => $motivo,
                'meta_json' => [
                    'modalidad_anterior' => $tarea->modalidad?->codigo,
                    'modalidad_nueva' => $modalidad->codigo,
                    'instruccion_nueva_id' => $nueva->id,
                    'ubicacion' => $fisico?->ubicacion,
                    'estado_fisico' => $fisico?->estado,
                ],
            ]);

            if ($fisico) {
                $fisico->update([
                    'pedido_bma_tarea_preparacion_id' => $nueva->id,
                ]);
            } else {
                $this->asegurar->ejecutar($nueva);
            }

            foreach ($tarea->productos as $producto) {
                $nueva->productos()->create([
                    'producto_id' => $producto->producto_id,
                    'sku' => $producto->sku,
                    'descripcion_snapshot' => $producto->descripcion_snapshot,
                    'cantidad_solicitada' => $producto->cantidad_solicitada,
                    'orden' => $producto->orden,
                ]);
            }

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $json['version'] = 1;
            $json['codigo_modalidad'] = $modalidad->codigo;
            $pedido->update(['solicitud_preparacion_json' => $json]);

            if ($pedido->estatus) {
                $this->historialService->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $pedido->estatus->id,
                    $pedido->estatus->id,
                    "Instrucción de entrega sustituida por {$modalidad->nombre}. {$motivo}",
                    AccionesHistorialPedidoBma::CAMBIO_MODALIDAD_PREPARACION
                );
            }

            $this->notificarService->ejecutar(
                $pedido->fresh(['cliente', 'vendedor']),
                'pedido_preparacion_cambio_modalidad',
                "La instrucción de entrega cambió a {$modalidad->nombre}. La mercancía apartada permanece en su ubicación.",
                ['control_pedidos.tienda.ver'],
                $usuario->id,
                true,
                ['url' => '/control-pedidos/tienda?tarea='.$nueva->id]
            );

            return $nueva->fresh(['modalidad', 'almacen', 'productos', 'pedido.cliente', 'cumplimientoFisico']);
        });
    }
}
