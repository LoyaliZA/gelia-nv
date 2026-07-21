<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Notifications\AlertaPedidoBma;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotificarPedidoBmaService
{
    /**
     * @param  list<string>  $permisos
     */
    public function ejecutar(
        PedidoBma $pedido,
        string $tipoAlerta,
        string $mensaje,
        array $permisos,
        ?int $excluirUsuarioId = null,
        bool $incluirVendedora = true,
        array $extras = [],
    ): void {
        $enviar = function () use ($pedido, $tipoAlerta, $mensaje, $permisos, $excluirUsuarioId, $incluirVendedora, $extras) {
            try {
                $destinatarios = $this->resolverDestinatarios($pedido, $permisos, $excluirUsuarioId, $incluirVendedora);
                if ($destinatarios->isEmpty()) {
                    return;
                }

                Notification::send(
                    $destinatarios,
                    new AlertaPedidoBma($pedido, $tipoAlerta, $mensaje, $extras)
                );
            } catch (\Throwable $e) {
                Log::error('No se pudo notificar pedido BMA', [
                    'pedido_id' => $pedido->id,
                    'tipo' => $tipoAlerta,
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($enviar);
        } else {
            $enviar();
        }
    }

    /**
     * @param  list<string>  $permisos
     */
    private function resolverDestinatarios(
        PedidoBma $pedido,
        array $permisos,
        ?int $excluirUsuarioId,
        bool $incluirVendedora,
    ): Collection {
        $usuarios = User::permission($permisos)->get();

        if ($incluirVendedora && $pedido->vendedor_id) {
            $vendedor = User::find($pedido->vendedor_id);
            if ($vendedor) {
                $usuarios = $usuarios->push($vendedor);
            }
        }

        return $usuarios
            ->unique('id')
            ->when($excluirUsuarioId, fn (Collection $c) => $c->reject(fn (User $u) => (int) $u->id === (int) $excluirUsuarioId))
            ->values();
    }
}
