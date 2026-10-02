<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Support\ControlPedidos\MaquinaEstadosCumplimientoFisico;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SolicitarDevolucionCumplimientoService
{
    public function __construct(
        private AsegurarCumplimientoFisicoService $asegurar,
        private RegistrarEventoCumplimientoFisicoService $eventos,
        private NotificarPedidoBmaService $notificar,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        ?User $usuario,
        string $motivo,
        string $idempotencia,
        ?int $versionEsperada = null,
    ): PedidoBmaCumplimientoFisico {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Indique el motivo de la devolución.',
            ]);
        }

        return DB::transaction(function () use ($tarea, $usuario, $motivo, $idempotencia, $versionEsperada) {
            $this->asegurar->ejecutar($tarea);
            $cumplimiento = PedidoBmaCumplimientoFisico::query()
                ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($cumplimiento->estado, [
                PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE,
                PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL,
            ], true)) {
                $this->bloquearEntregaPdv($cumplimiento);
                $this->eventos->ejecutar(
                    $cumplimiento,
                    PedidoBmaCumplimientoEvento::TIPO_DEVOLUCION_SOLICITADA,
                    $usuario,
                    $idempotencia,
                    $motivo
                );

                return $cumplimiento;
            }

            if (! $cumplimiento->tieneMercanciaApartada()) {
                throw ValidationException::withMessages([
                    'estado' => 'No hay mercancía apartada para devolver.',
                ]);
            }

            if ($versionEsperada !== null && (int) $cumplimiento->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otra persona modificó este apartado. Actualice la página e intente de nuevo.',
                ]);
            }

            MaquinaEstadosCumplimientoFisico::assertTransicion(
                $cumplimiento->estado,
                PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE
            );

            $cumplimiento->update([
                'estado' => PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE,
                'devolucion_solicitada_at' => now(),
                'version' => $cumplimiento->version + 1,
            ]);

            $this->bloquearEntregaPdv($cumplimiento);

            $this->eventos->ejecutar(
                $cumplimiento,
                PedidoBmaCumplimientoEvento::TIPO_DEVOLUCION_SOLICITADA,
                $usuario,
                $idempotencia,
                $motivo
            );

            $pedido = $tarea->pedido()->first();
            if ($pedido) {
                $this->notificar->ejecutar(
                    $pedido,
                    'pedido_apartado_devolucion_pendiente',
                    'Una recogida no concretada quedó en devolución pendiente. Confirme ubicación y foto para devolverla al anaquel.',
                    ['control_pedidos.tienda.apartado.confirmar_devolucion', 'control_pedidos.tienda.ver'],
                    $usuario?->id,
                    true,
                    ['url' => '/control-pedidos/tienda?tab=DEVOLUCION_PENDIENTE']
                );
            }

            return $cumplimiento->fresh();
        });
    }

    private function bloquearEntregaPdv(PedidoBmaCumplimientoFisico $cumplimiento): void
    {
        if (! $cumplimiento->resguardo_pdv_id) {
            return;
        }

        ResguardoPdv::query()
            ->whereKey($cumplimiento->resguardo_pdv_id)
            ->where('estado', '!=', ResguardoPdv::ESTADO_ENTREGADO)
            ->update(['entrega_bloqueada' => true]);
    }
}
