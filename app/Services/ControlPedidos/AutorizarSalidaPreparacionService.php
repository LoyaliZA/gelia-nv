<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\MaquinaEstadosCumplimientoFisico;
use App\Support\ControlPedidos\PoliticaSalidaPreparacion;
use App\Support\ControlPedidos\SalidaMunicipalPreparacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutorizarSalidaPreparacionService
{
    public function __construct(
        private AsegurarCumplimientoFisicoService $asegurar,
        private RegistrarEventoCumplimientoFisicoService $eventos,
        private AbrirCustodiaDiferidaPdvService $custodia,
        private CalcularRequisitosPreparacionService $requisitosService,
        private GenerarHojaSalidaInternaService $hojaSalidaInterna,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        ?int $versionEsperada = null,
    ): PedidoBmaCumplimientoFisico {
        if (! $usuario->can('control_pedidos.salida.autorizar')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para autorizar la salida.',
            ]);
        }

        $tarea->loadMissing('pedido');
        $pedido = $tarea->pedido;
        if ($pedido && (int) $pedido->vendedor_id === (int) $usuario->id) {
            throw ValidationException::withMessages([
                'permiso' => 'Quien registró la venta no puede autorizar la salida.',
            ]);
        }

        $politica = PoliticaSalidaPreparacion::desdeTarea($tarea);
        if (! $politica->usaSalidaInterna()) {
            throw ValidationException::withMessages([
                'modalidad' => 'Esta modalidad no usa autorización interna de salida.',
            ]);
        }

        $caratulaMunicipal = null;
        if ($politica->esEnvioMunicipio()) {
            $caratulaMunicipal = SalidaMunicipalPreparacion::assertCaratulaColocada($tarea);
        }

        return DB::transaction(function () use ($tarea, $usuario, $versionEsperada, $politica, $caratulaMunicipal) {
            $this->asegurar->ejecutar($tarea);
            $cumplimiento = PedidoBmaCumplimientoFisico::query()
                ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                ->lockForUpdate()
                ->firstOrFail();

            $clave = 'salida:'.$tarea->id;
            if ($cumplimiento->estado === PedidoBmaCumplimientoFisico::ESTADO_LISTA_PARA_SALIDA
                || $cumplimiento->estado === PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA
                || ($politica->esEnvioMunicipio() && in_array($cumplimiento->estado, [
                    PedidoBmaCumplimientoFisico::ESTADO_EMPACADA,
                    PedidoBmaCumplimientoFisico::ESTADO_DESPACHADA,
                ], true))) {
                $this->eventos->ejecutar(
                    $cumplimiento,
                    PedidoBmaCumplimientoEvento::TIPO_SALIDA_AUTORIZADA,
                    $usuario,
                    $clave,
                    'Salida ya autorizada.'
                );

                return $cumplimiento;
            }

            if ($cumplimiento->estado !== PedidoBmaCumplimientoFisico::ESTADO_SEPARADA) {
                throw ValidationException::withMessages([
                    'estado' => 'Autorice la salida cuando la mercancía ya esté separada.',
                ]);
            }

            if ($cumplimiento->pago_confirmado_at === null) {
                throw ValidationException::withMessages([
                    'pago' => 'Valide el pago o registre el permiso de por cobrar antes de autorizar la salida.',
                ]);
            }

            if ($politica->esTransferenciaDiferida()
                && $cumplimiento->condicion_cobro !== PoliticaSalidaPreparacion::COBRO_PAGADO) {
                throw ValidationException::withMessages([
                    'pago' => 'La transferencia diferida se entrega después del pago.',
                ]);
            }

            if ($versionEsperada !== null && (int) $cumplimiento->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otra persona modificó este apartado. Actualice la página e intente de nuevo.',
                ]);
            }

            MaquinaEstadosCumplimientoFisico::assertTransicion(
                $cumplimiento->estado,
                PedidoBmaCumplimientoFisico::ESTADO_LISTA_PARA_SALIDA
            );

            $cumplimiento->update([
                'estado' => PedidoBmaCumplimientoFisico::ESTADO_LISTA_PARA_SALIDA,
                'salida_autorizada_at' => now(),
                'salida_autorizada_por_id' => $usuario->id,
                'version' => $cumplimiento->version + 1,
            ]);

            $req = $this->requisitosService->efectivos($tarea);
            $exigeRemision = ! empty($req['requiere_remision']);
            if ($politica->esEnvioMunicipio() && $exigeRemision && $caratulaMunicipal) {
                $tarea->loadMissing('documentos');
                $tieneRemision = $tarea->documentos
                    ->where('tipo_evidencia', \App\Models\ControlPedidos\PedidoBmaTareaDocumento::TIPO_REMISION)
                    ->isNotEmpty();
                if (! $tieneRemision) {
                    $this->hojaSalidaInterna->ejecutar($tarea, $usuario, $caratulaMunicipal);
                }
            }

            $this->eventos->ejecutar(
                $cumplimiento,
                PedidoBmaCumplimientoEvento::TIPO_SALIDA_AUTORIZADA,
                $usuario,
                $clave,
                $exigeRemision
                    ? 'Autorización de salida. Remisión o hoja interna al despachar.'
                    : 'Autorización interna de salida.',
                [
                    'condicion_cobro' => $cumplimiento->condicion_cobro,
                    'pedido_bma_pago_id' => $cumplimiento->pedido_bma_pago_id,
                    'exige_remision' => $exigeRemision,
                    'causa_remision' => $exigeRemision
                        ? $this->requisitosService->causaRemisionSalida($tarea)
                        : null,
                ]
            );

            if ($politica->abreResguardo()) {
                $this->custodia->ejecutar($tarea, $cumplimiento, $usuario);
            }

            return $cumplimiento->fresh();
        });
    }
}
