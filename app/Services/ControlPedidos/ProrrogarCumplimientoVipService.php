<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\PlazosApartadoFisico;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProrrogarCumplimientoVipService
{
    public function __construct(
        private AsegurarCumplimientoFisicoService $asegurar,
        private RegistrarEventoCumplimientoFisicoService $eventos,
        private PreparacionTiendaConfig $config,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        string $motivo,
        ?int $versionEsperada = null,
    ): PedidoBmaCumplimientoFisico {
        if (! $usuario->can('control_pedidos.tienda.apartado.aprobar_prorroga')) {
            throw ValidationException::withMessages([
                'permiso' => 'La prórroga VIP requiere permiso de supervisor.',
            ]);
        }

        $motivo = trim($motivo);
        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Indique el motivo de la prórroga.',
            ]);
        }

        return DB::transaction(function () use ($tarea, $usuario, $motivo, $versionEsperada) {
            $this->asegurar->ejecutar($tarea);
            $cumplimiento = PedidoBmaCumplimientoFisico::query()
                ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($cumplimiento->prorroga_aplicada) {
                throw ValidationException::withMessages([
                    'motivo' => 'La prórroga VIP ya se aplicó una vez.',
                ]);
            }

            if ($cumplimiento->estado !== PedidoBmaCumplimientoFisico::ESTADO_SEPARADA) {
                throw ValidationException::withMessages([
                    'estado' => 'La prórroga solo mueve el vencimiento de mercancía ya separada, antes de la devolución.',
                ]);
            }

            if ($versionEsperada !== null && (int) $cumplimiento->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otra persona modificó este apartado. Actualice la página e intente de nuevo.',
                ]);
            }

            $base = $cumplimiento->vence_at?->copy() ?? now();
            $nuevoVence = PlazosApartadoFisico::sumarDiasHabiles($base, $this->config->diasProrrogaVip());

            $cumplimiento->update([
                'vence_at' => $nuevoVence,
                'prorroga_aplicada' => true,
                'prorroga_por_id' => $usuario->id,
                'prorroga_motivo' => $motivo,
                'prorroga_at' => now(),
                'version' => $cumplimiento->version + 1,
            ]);

            $this->eventos->ejecutar(
                $cumplimiento,
                PedidoBmaCumplimientoEvento::TIPO_PLAZO_EXTENDIDO,
                $usuario,
                'prorroga:'.$cumplimiento->id,
                $motivo,
                [
                    'vence_at' => $nuevoVence->toIso8601String(),
                    'dias_habiles' => $this->config->diasProrrogaVip(),
                ]
            );

            return $cumplimiento->fresh();
        });
    }
}
