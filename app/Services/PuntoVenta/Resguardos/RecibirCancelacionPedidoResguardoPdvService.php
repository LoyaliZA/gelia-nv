<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Events\PuntoVenta\CancelacionPedidoResguardoPdvRecibida;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecibirCancelacionPedidoResguardoPdvService
{
    public static function claveIdempotencia(int $pedidoBmaId, int $resguardoId): string
    {
        return 'pdv:cancel:'.$pedidoBmaId.':'.$resguardoId;
    }

    /**
     * @return Collection<int, ResguardoPdv>
     */
    public function ejecutar(PedidoBma $pedido, ?int $actorId = null, ?string $motivo = null): Collection
    {
        return DB::transaction(function () use ($pedido, $actorId, $motivo) {
            $resguardos = ResguardoPdv::query()
                ->where('pedido_bma_id', $pedido->id)
                ->whereIn('estado', [
                    ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
                    ResguardoPdv::ESTADO_EN_CUSTODIA,
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $afectados = collect();

            foreach ($resguardos as $resguardo) {
                $afectados->push($this->aplicar($resguardo, $pedido, $actorId, $motivo));
            }

            return $afectados;
        });
    }

    private function aplicar(
        ResguardoPdv $resguardo,
        PedidoBma $pedido,
        ?int $actorId,
        ?string $motivo,
    ): ResguardoPdv {
        $clave = self::claveIdempotencia((int) $pedido->id, (int) $resguardo->id);
        $existente = $this->eventoCancelacion($resguardo, $clave);

        if ($existente !== null) {
            return $resguardo->fresh();
        }

        $estadoAnterior = $resguardo->estado;
        $ahora = now();

        $resguardo->update([
            'entrega_bloqueada' => true,
            'version' => $resguardo->version + 1,
        ]);

        try {
            $evento = ResguardoPdvEvento::query()->create([
                'resguardo_id' => $resguardo->id,
                'tipo_evento' => ResguardoPdvEvento::TIPO_CANCELACION_RECIBIDA,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estadoAnterior,
                'actor_id' => $actorId,
                'ocurrido_at' => $ahora,
                'snapshot_json' => [
                    'pedido_bma_id' => (int) $pedido->id,
                    'motivo' => $motivo,
                    'exige_devolucion' => true,
                ],
                'idempotency_key' => $clave,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $recuperado = $this->eventoCancelacion($resguardo, $clave);
            if ($recuperado !== null) {
                return $resguardo->fresh();
            }

            throw $e;
        }

        CancelacionPedidoResguardoPdvRecibida::dispatch(
            $resguardo->fresh(),
            $evento,
            (int) $pedido->id,
            (int) $resguardo->sucursal_id,
        );

        return $resguardo->fresh();
    }

    private function eventoCancelacion(ResguardoPdv $resguardo, string $clave): ?ResguardoPdvEvento
    {
        return ResguardoPdvEvento::query()
            ->where('resguardo_id', $resguardo->id)
            ->where('tipo_evento', ResguardoPdvEvento::TIPO_CANCELACION_RECIBIDA)
            ->where('idempotency_key', $clave)
            ->first();
    }
}
