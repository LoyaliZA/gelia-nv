<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\User;
use App\Support\ControlPedidos\MaquinaEstadosCumplimientoFisico;
use App\Support\ControlPedidos\PoliticaSalidaPreparacion;
use Illuminate\Http\UploadedFile;
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
        ?UploadedFile $foto = null,
    ): PedidoBmaCumplimientoFisico {
        if (! $usuario->can('control_pedidos.salida.confirmar_entrega')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para confirmar la entrega.',
            ]);
        }

        $politica = PoliticaSalidaPreparacion::desdeTarea($tarea);
        if ($politica->esRecogeHoy()) {
            return $this->entregarApartado($tarea, $usuario, $receptor, $versionEsperada, $foto);
        }
        if ($politica->soloApartado()) {
            throw ValidationException::withMessages([
                'modalidad' => 'Recoge en tienda no confirma entrega. El cierre es la confirmación del apartado.',
            ]);
        }
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

    private function entregarApartado(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        string $receptor,
        ?int $versionEsperada,
        ?UploadedFile $foto,
    ): PedidoBmaCumplimientoFisico {
        if (! $foto) {
            throw ValidationException::withMessages([
                'foto' => 'Adjunte la fotografía de la entrega.',
            ]);
        }

        return DB::transaction(function () use ($tarea, $usuario, $receptor, $versionEsperada, $foto) {
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

            $pedidoVigente = \App\Models\ControlPedidos\PedidoBma::query()->lockForUpdate()->findOrFail($tarea->pedido_bma_id);
            if ($motivo = $pedidoVigente->motivoBloqueoEntregaTienda()) {
                throw ValidationException::withMessages(['ajuste' => $motivo]);
            }
            if ($cumplimiento->pago_confirmado_at === null) {
                throw ValidationException::withMessages([
                    'pago' => 'Falta el pago presencial confirmado. La entrega no lo sustituye.',
                ]);
            }
            if ($cumplimiento->estado !== PedidoBmaCumplimientoFisico::ESTADO_APARTADO_CONFIRMADO) {
                throw ValidationException::withMessages([
                    'estado' => 'La entrega del apartado espera el apartado confirmado y el pago presencial.',
                ]);
            }
            if ($versionEsperada !== null && (int) $cumplimiento->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otra persona modificó este apartado. Actualice la página e intente de nuevo.',
                ]);
            }

            $ruta = $foto->store("pedidos_bma/tareas_preparacion/{$tarea->id}", 'local');
            $tarea->documentos()->create([
                'tipo_evidencia' => PedidoBmaTareaDocumento::TIPO_EVIDENCIA_ENTREGA,
                'ruta_interna' => $ruta,
                'nombre_original' => $foto->getClientOriginalName(),
                'mime_type' => $foto->getMimeType(),
                'tamano_bytes' => $foto->getSize(),
                'hash_sha256' => hash_file('sha256', $foto->getRealPath()) ?: null,
                'subido_por_id' => $usuario->id,
                'subido_at' => now(),
            ]);

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
                'Entrega del apartado con pago presencial y fotografía.',
                [
                    'receptor' => $receptor,
                    'foto' => $ruta,
                    'entregada_at' => $ahora->toIso8601String(),
                ]
            );

            return $cumplimiento->fresh();
        });
    }
}
