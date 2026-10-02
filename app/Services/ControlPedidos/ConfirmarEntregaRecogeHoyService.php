<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\MaquinaEstadosCumplimientoFisico;
use App\Support\ControlPedidos\PoliticaSalidaPreparacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmarEntregaRecogeHoyService
{
    public function __construct(
        private AsegurarCumplimientoFisicoService $asegurar,
        private RegistrarEventoCumplimientoFisicoService $eventos,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        string $receptor,
        ?int $versionEsperada = null,
    ): PedidoBmaCumplimientoFisico {
        if (! $usuario->can('control_pedidos.salida.confirmar_entrega')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para confirmar la entrega.',
            ]);
        }

        $politica = PoliticaSalidaPreparacion::desdeTarea($tarea);
        if (! $politica->entregaDirectaSinResguardo()) {
            throw ValidationException::withMessages([
                'modalidad' => 'La transferencia diferida se entrega desde el resguardo de PDV, una sola vez y después del pago.',
            ]);
        }

        $receptor = trim($receptor);
        if ($receptor === '') {
            throw ValidationException::withMessages([
                'receptor' => 'Indique quién recibe la mercancía.',
            ]);
        }

        return DB::transaction(function () use ($tarea, $usuario, $receptor, $versionEsperada) {
            $this->asegurar->ejecutar($tarea);
            $cumplimiento = PedidoBmaCumplimientoFisico::query()
                ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                ->lockForUpdate()
                ->firstOrFail();

            $clave = 'entrega:'.$tarea->id;
            if ($cumplimiento->estado === PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA) {
                $this->eventos->ejecutar(
                    $cumplimiento,
                    PedidoBmaCumplimientoEvento::TIPO_ENTREGADA,
                    $usuario,
                    $clave,
                    'Entrega ya confirmada.'
                );

                return $cumplimiento;
            }

            if ($cumplimiento->estado !== PedidoBmaCumplimientoFisico::ESTADO_LISTA_PARA_SALIDA) {
                throw ValidationException::withMessages([
                    'estado' => 'La entrega requiere autorización de salida. No pasa por CEDIS.',
                ]);
            }

            if ($versionEsperada !== null && (int) $cumplimiento->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otra persona modificó este apartado. Actualice la página e intente de nuevo.',
                ]);
            }

            MaquinaEstadosCumplimientoFisico::assertTransicion(
                $cumplimiento->estado,
                PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA
            );

            $ahora = now();
            $cumplimiento->update([
                'estado' => PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA,
                'entregada_at' => $ahora,
                'entregada_por_id' => $usuario->id,
                'receptor_nombre' => $receptor,
                'version' => $cumplimiento->version + 1,
            ]);

            $this->eventos->ejecutar(
                $cumplimiento,
                PedidoBmaCumplimientoEvento::TIPO_ENTREGADA,
                $usuario,
                $clave,
                'Entrega de recoge hoy. Sin remisión y sin aprobación de CEDIS.',
                [
                    'receptor' => $receptor,
                    'entregada_at' => $ahora->toIso8601String(),
                    'entregada_por_id' => $usuario->id,
                    'resguardo_pdv_id' => null,
                ]
            );

            return $cumplimiento->fresh();
        });
    }
}
