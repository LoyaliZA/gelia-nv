<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaTareaHistorial;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosTareaPreparacion;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CambiarModalidadPreparacionService
{
    /** @var list<string> */
    private const ESTADOS_BLOQUEADOS = [
        PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO,
        PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
        PedidoBmaTareaPreparacion::ESTADO_LIBERADA,
        PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
    ];

    public function __construct(
        private PreparacionTiendaConfig $config,
        private CalcularRequisitosPreparacionService $requisitosService,
        private TransicionEstadoTareaPreparacionService $transicionService,
        private RegistrarCierreApartadoService $cierreApartado,
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

        return DB::transaction(function () use ($tarea, $usuario, $modalidad, $motivo, $versionEsperada, $pedido) {
            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);
            if ($versionEsperada !== null && (int) $tarea->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otra persona modificó esta tarea. Actualice la página e intente de nuevo.',
                ]);
            }

            $this->cierreApartado->ejecutar(
                $tarea,
                $usuario,
                PedidoBmaCumplimientoEvento::TIPO_CAMBIO_MODALIDAD,
                $motivo,
                'modalidad:'.$tarea->id.':'.$modalidad->id
            );

            if (MaquinaEstadosTareaPreparacion::puedeTransicionar($tarea->estado, PedidoBmaTareaPreparacion::ESTADO_CANCELADA)) {
                $this->transicionService->ejecutar(
                    $tarea,
                    PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
                    $usuario->id,
                    'cambiar_modalidad',
                    $motivo,
                    ['modalidad_nueva' => $modalidad->codigo],
                    null,
                    $usuario,
                    true
                );
            } else {
                PedidoBmaTareaHistorial::query()->create([
                    'pedido_bma_tarea_preparacion_id' => $tarea->id,
                    'usuario_id' => $usuario->id,
                    'estado_anterior' => $tarea->estado,
                    'estado_nuevo' => $tarea->estado,
                    'accion' => 'cambiar_modalidad',
                    'comentario' => $motivo,
                    'meta_json' => ['modalidad_nueva' => $modalidad->codigo],
                ]);
            }

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
                'requiere_traslado_cedis' => $modalidad->requiereTrasladoCedisPorDefecto(),
            ]);

            foreach ($tarea->productos as $producto) {
                $nueva->productos()->create([
                    'producto_id' => $producto->producto_id,
                    'sku' => $producto->sku,
                    'descripcion_snapshot' => $producto->descripcion_snapshot,
                    'cantidad_solicitada' => $producto->cantidad_solicitada,
                    'orden' => $producto->orden,
                ]);
            }

            $this->asegurar->ejecutar($nueva);

            if ($pedido->estatus) {
                $this->historialService->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $pedido->estatus->id,
                    $pedido->estatus->id,
                    "Cambio de modalidad de preparación a {$modalidad->nombre}. {$motivo}",
                    AccionesHistorialPedidoBma::CAMBIO_MODALIDAD_PREPARACION
                );
            }

            $this->notificarService->ejecutar(
                $pedido->fresh(['cliente', 'vendedor']),
                'pedido_preparacion_cambio_modalidad',
                "Cambió la modalidad de preparación a {$modalidad->nombre}. Revise el apartado y la nueva tarea.",
                ['control_pedidos.tienda.ver'],
                $usuario->id,
                true,
                ['url' => '/control-pedidos/tienda?tarea='.$nueva->id]
            );

            return $nueva->fresh(['modalidad', 'almacen', 'productos', 'pedido.cliente']);
        });
    }
}
