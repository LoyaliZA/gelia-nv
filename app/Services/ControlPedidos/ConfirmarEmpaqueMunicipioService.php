<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\MaquinaEstadosCumplimientoFisico;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmarEmpaqueMunicipioService
{
    public function __construct(
        private CalcularRequisitosPreparacionService $requisitosService,
        private RegistrarEventoCumplimientoFisicoService $eventos,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        int $bultos,
        ?int $versionEsperada = null,
    ): PedidoBmaCumplimientoFisico {
        if (! $usuario->can('control_pedidos.tienda.empacar_municipio')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para registrar el empaque municipal.',
            ]);
        }

        if (! $tarea->modalidad?->esEnvioMunicipio()) {
            throw ValidationException::withMessages([
                'modalidad' => 'Esta acción aplica solo a envío a municipio.',
            ]);
        }

        if ($bultos < 1) {
            throw ValidationException::withMessages([
                'bultos' => 'Indique al menos un bulto.',
            ]);
        }

        return DB::transaction(function () use ($tarea, $usuario, $bultos, $versionEsperada) {
            $tarea->loadMissing(['documentos', 'productos', 'paqueteria', 'modalidad']);
            $cumplimiento = PedidoBmaCumplimientoFisico::query()
                ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($cumplimiento->estado !== PedidoBmaCumplimientoFisico::ESTADO_LISTA_PARA_SALIDA) {
                throw ValidationException::withMessages([
                    'estado' => 'Empaque después de autorizar la salida.',
                ]);
            }

            if ($versionEsperada !== null && (int) $cumplimiento->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otra persona modificó este registro. Actualice e intente de nuevo.',
                ]);
            }

            $req = $this->requisitosService->efectivos($tarea);
            $faltantes = $this->requisitosService->validarEmpaqueMunicipio($tarea, $req);
            if ($faltantes !== []) {
                throw ValidationException::withMessages(['requisitos' => $faltantes]);
            }

            MaquinaEstadosCumplimientoFisico::assertTransicion(
                $cumplimiento->estado,
                PedidoBmaCumplimientoFisico::ESTADO_EMPACADA
            );

            $cumplimiento->update([
                'estado' => PedidoBmaCumplimientoFisico::ESTADO_EMPACADA,
                'bultos_salida' => $bultos,
                'empacado_at' => now(),
                'empacado_por_id' => $usuario->id,
                'version' => $cumplimiento->version + 1,
            ]);

            $this->eventos->ejecutar(
                $cumplimiento,
                PedidoBmaCumplimientoEvento::TIPO_EMPACADA,
                $usuario,
                'empaque:'.$tarea->id,
                'Empaque municipal registrado.',
                ['bultos' => $bultos, 'empacador_id' => $usuario->id]
            );

            return $cumplimiento->fresh();
        });
    }
}
