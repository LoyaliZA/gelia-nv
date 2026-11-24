<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\AssertPedidoNoBloqueadoFase7;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\AlcanceConsultaMunicipal;
use App\Support\ControlPedidos\ConsultaMunicipal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Municipal aprobado cuyo almacén es PDV: Tienda lo empaca y con marcarlo empacado el flujo concluye en Tienda
 * (sin traslado desde CEDIS, sin recepción ni custodia, sin guía).
 */
class MarcarEmpacadoMunicipalTiendaService
{
    public function __construct(private RegistrarHistorialPedidoService $historial) {}

    public function ejecutar(PedidoBma $pedido, User $usuario): PedidoBma
    {
        return DB::transaction(function () use ($pedido, $usuario) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $pedido->loadMissing('estatus');

            $seg = ConsultaMunicipal::seguimiento($pedido);
            if (! ConsultaMunicipal::es($pedido) || ($seg['area'] ?? null) !== 'tienda') {
                throw ValidationException::withMessages(['pedido' => 'Este pedido no se empaca en Tienda.']);
            }
            $permitido = collect(AlcanceConsultaMunicipal::porEmpacarTienda($usuario, 500))->contains('id', $pedido->id);
            if (! $permitido) {
                if ($pedido->empacado_at) {
                    throw ValidationException::withMessages(['pedido' => 'El pedido ya está empacado.']);
                }
                throw ValidationException::withMessages(['pedido' => 'El pedido no está listo para empacar en su sucursal.']);
            }
            AssertPedidoNoBloqueadoFase7::assert($pedido);
            $pedido->assertSinExistenciaAtendida();

            $anterior = $pedido->estatus;
            $nuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO)
                ?? throw new \RuntimeException('No se encontró el estatus de empacado.');

            $pedido->update([
                'catalogo_estatus_pedido_id' => $nuevo->id,
                'empacado_at' => now(),
                'empacado_por_id' => $usuario->id,
            ]);
            $this->historial->registrarTransicion(
                $pedido->id, $usuario->id, $anterior, $nuevo,
                'Pedido municipal empacado en Tienda. El flujo concluye en la sucursal.',
                AccionesHistorialPedidoBma::EMPAQUE
            );

            return $pedido->fresh();
        });
    }
}
