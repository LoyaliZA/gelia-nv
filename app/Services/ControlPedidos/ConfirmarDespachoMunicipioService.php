<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\MaquinaEstadosCumplimientoFisico;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmarDespachoMunicipioService
{
    public function __construct(
        private RegistrarEventoCumplimientoFisicoService $eventos,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        string $receptorTransportista,
        ?int $versionEsperada = null,
    ): PedidoBmaCumplimientoFisico {
        if (! $usuario->can('control_pedidos.tienda.despachar_municipio')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para registrar el despacho municipal.',
            ]);
        }

        $receptorTransportista = trim($receptorTransportista);
        if ($receptorTransportista === '') {
            throw ValidationException::withMessages([
                'receptor' => 'Indique quién recibe el bulto (transportista o persona de entrega).',
            ]);
        }

        return DB::transaction(function () use ($tarea, $usuario, $receptorTransportista, $versionEsperada) {
            $cumplimiento = PedidoBmaCumplimientoFisico::query()
                ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                ->lockForUpdate()
                ->firstOrFail();

            $clave = 'despacho:'.$tarea->id;
            if ($cumplimiento->estado === PedidoBmaCumplimientoFisico::ESTADO_DESPACHADA) {
                $this->eventos->ejecutar(
                    $cumplimiento,
                    PedidoBmaCumplimientoEvento::TIPO_DESPACHADA,
                    $usuario,
                    $clave,
                    'Despacho ya registrado.'
                );

                return $cumplimiento;
            }

            if ($cumplimiento->estado !== PedidoBmaCumplimientoFisico::ESTADO_EMPACADA) {
                throw ValidationException::withMessages([
                    'estado' => 'Registre el empaque antes del despacho.',
                ]);
            }

            if ($versionEsperada !== null && (int) $cumplimiento->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otra persona modificó este registro. Actualice e intente de nuevo.',
                ]);
            }

            MaquinaEstadosCumplimientoFisico::assertTransicion(
                $cumplimiento->estado,
                PedidoBmaCumplimientoFisico::ESTADO_DESPACHADA
            );

            $cumplimiento->update([
                'estado' => PedidoBmaCumplimientoFisico::ESTADO_DESPACHADA,
                'despachada_at' => now(),
                'despachada_por_id' => $usuario->id,
                'receptor_nombre' => $receptorTransportista,
                'version' => $cumplimiento->version + 1,
            ]);

            $this->eventos->ejecutar(
                $cumplimiento,
                PedidoBmaCumplimientoEvento::TIPO_DESPACHADA,
                $usuario,
                $clave,
                'Salida al transportista registrada.',
                ['receptor' => $receptorTransportista, 'bultos' => $cumplimiento->bultos_salida]
            );

            return $cumplimiento->fresh();
        });
    }
}
