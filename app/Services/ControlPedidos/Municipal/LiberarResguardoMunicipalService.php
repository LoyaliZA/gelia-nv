<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\ConsultaMunicipal;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
use Illuminate\Support\Facades\DB;

/**
 * Liberación administrativa del conjunto. Conserva orígenes, vínculos y cobros de cada remisión.
 */
class LiberarResguardoMunicipalService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        $actor = User::findOrFail($usuarioId);
        if (! $actor->can('control_pedidos.liberar_resguardo') || ! $actor->can('control_pedidos.auditar')) {
            throw new \RuntimeException('Se requiere auditar y el permiso de liberar resguardo.');
        }
        if ((int) $pedido->vendedor_id === (int) $actor->id) {
            throw new \RuntimeException('Quien registró la venta no libera el resguardo.');
        }
        if (! ConsultaMunicipal::es($pedido)) {
            throw new \InvalidArgumentException('Este pedido no tiene un resguardo municipal activo.');
        }
        if (! empty(PoliticaResguardoEnvio::datos($pedido)['liberado_at'])
            && ! PoliticaResguardoEnvio::intencionActiva($pedido)) {
            return $pedido->fresh(['resguardosAnexados.resguardo', 'complementos']);
        }
        PoliticaResguardoEnvio::assertPuedeLiberar($pedido);

        $fase = $pedido->estatus?->fase_ciclo;
        if (! in_array($fase, [
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO,
        ], true)) {
            throw new \RuntimeException('Solo se libera el resguardo municipal en revisión o preparación.');
        }

        return DB::transaction(function () use ($pedido, $actor) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $miembros = PoliticaResguardoEnvio::miembros($pedido);
            if ($miembros->isNotEmpty()) {
                PedidoBma::query()->whereIn('id', $miembros->pluck('id'))->orderBy('id')->lockForUpdate()->get();
            }

            $json = PoliticaResguardoEnvio::fusionarJson($pedido, [
                'activo' => false,
                'acumulacion_abierta' => false,
                'causa_flag' => null,
                'liberado_at' => now()->toIso8601String(),
                'liberado_por_id' => $actor->id,
                'conjunto_liberado_at' => now()->toIso8601String(),
            ]);
            $pedido->fill(['solicitud_preparacion_json' => $json]);
            $pedido->es_resguardo = PoliticaResguardoEnvio::aplicarFlagCompartido($pedido, false);
            $pedido->save();

            $this->historial->ejecutar(
                $pedido->id,
                $actor->id,
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                'Resguardo municipal liberado. El conjunto autorizado puede continuar; las remisiones integrantes no se despachan por su cuenta.',
                AccionesHistorialPedidoBma::LIBERAR_RESGUARDO
            );

            foreach ($miembros as $miembro) {
                $this->conservarMiembro($miembro, $pedido, $actor);
            }

            return $pedido->fresh(['resguardosAnexados.resguardo', 'complementos']);
        });
    }

    private function conservarMiembro(PedidoBma $miembro, PedidoBma $principal, User $actor): void
    {
        // Conserva pedido_principal_id, pivot, remisión, totales y pagos. No suma cobros al principal.
        $this->historial->ejecutar(
            $miembro->id,
            $actor->id,
            $miembro->catalogo_estatus_pedido_id,
            $miembro->catalogo_estatus_pedido_id,
            sprintf(
                'El envío %s fue liberado. Esta remisión sigue integrada; no se despacha ni se cobra de nuevo.',
                $principal->folio
            ),
            AccionesHistorialPedidoBma::LIBERAR_RESGUARDO
        );
    }
}
