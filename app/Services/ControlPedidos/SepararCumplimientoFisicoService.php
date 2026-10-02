<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\MaquinaEstadosCumplimientoFisico;
use App\Support\ControlPedidos\PlazosApartadoFisico;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SepararCumplimientoFisicoService
{
    public function __construct(
        private AsegurarCumplimientoFisicoService $asegurar,
        private RegistrarEventoCumplimientoFisicoService $eventos,
        private PreparacionTiendaConfig $config,
        private SolicitarDevolucionCumplimientoService $devolucion,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        int $cantidad,
        string $ubicacion,
        ?int $versionEsperada = null,
    ): PedidoBmaCumplimientoFisico {
        if (! $usuario->can('control_pedidos.tienda.apartado.separar')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para separar mercancía.',
            ]);
        }

        if ($cantidad < 1) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad apartada debe ser al menos 1. Cero piezas no se registra como apartado.',
            ]);
        }

        $ubicacion = trim($ubicacion);
        if ($ubicacion === '') {
            throw ValidationException::withMessages([
                'ubicacion' => 'Indique la ubicación física del apartado.',
            ]);
        }

        $tarea->loadMissing('modalidad');
        if (in_array($tarea->estado, [
            PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
            PedidoBmaTareaPreparacion::ESTADO_LIBERADA,
        ], true)) {
            throw ValidationException::withMessages([
                'estado' => 'Esta tarea ya no admite apartado físico.',
            ]);
        }

        $cumplimiento = DB::transaction(function () use ($tarea, $usuario, $cantidad, $ubicacion, $versionEsperada) {
            $this->asegurar->ejecutar($tarea);
            $cumplimiento = PedidoBmaCumplimientoFisico::query()
                ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                ->lockForUpdate()
                ->firstOrFail();

            $clave = 'separar:'.$tarea->id;
            $ya = PedidoBmaCumplimientoEvento::query()->where('idempotencia_clave', $clave)->first();
            if ($ya && $cumplimiento->estado !== PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR) {
                return $cumplimiento;
            }

            if ($versionEsperada !== null && (int) $cumplimiento->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otra persona modificó este apartado. Actualice la página e intente de nuevo.',
                ]);
            }

            MaquinaEstadosCumplimientoFisico::assertTransicion(
                $cumplimiento->estado,
                PedidoBmaCumplimientoFisico::ESTADO_SEPARADA
            );

            $venceAt = null;
            $esRecogeHoy = $tarea->modalidad?->codigo === CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA;
            if ($esRecogeHoy) {
                $venceAt = PlazosApartadoFisico::cierreOperativo(
                    now(),
                    $this->config->zonaHoraria(),
                    $this->config->cierreOperativoHora()
                );
            } elseif ($tarea->fecha_limite) {
                $venceAt = $tarea->fecha_limite;
            }

            $cumplimiento->update([
                'estado' => PedidoBmaCumplimientoFisico::ESTADO_SEPARADA,
                'cantidad' => $cantidad,
                'ubicacion' => $ubicacion,
                'vence_at' => $venceAt,
                'version' => $cumplimiento->version + 1,
            ]);

            $this->eventos->ejecutar(
                $cumplimiento,
                PedidoBmaCumplimientoEvento::TIPO_PIEZA_SEPARADA,
                $usuario,
                $clave,
                'Apartado físico operativo. No descuenta inventario.',
                [
                    'cantidad' => $cantidad,
                    'ubicacion' => $ubicacion,
                    'vence_at' => $venceAt?->toIso8601String(),
                    'inventario_sincronizado' => false,
                ]
            );

            return $cumplimiento->fresh();
        });

        if ($esRecogeHoy && $cumplimiento->vence_at && now()->greaterThanOrEqualTo($cumplimiento->vence_at)
            && $cumplimiento->estado === PedidoBmaCumplimientoFisico::ESTADO_SEPARADA) {
            return $this->devolucion->ejecutar(
                $tarea,
                $usuario,
                'El cierre operativo de recoge hoy ya pasó.',
                'vence:'.$cumplimiento->id.':'.$cumplimiento->vence_at->toIso8601String(),
                null
            );
        }

        return $cumplimiento;
    }
}
