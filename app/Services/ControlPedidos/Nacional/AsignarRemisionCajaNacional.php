<?php

namespace App\Services\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCajaRemision;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/** Una remisión activa vive en una sola caja. Lo pagado entra al valor asegurado y no se vuelve a cobrar. */
class AsignarRemisionCajaNacional
{
    /** @param  array<string, mixed>  $datos */
    public function ejecutar(PedidoBmaCaja $caja, int $usuarioId, array $datos): PedidoBmaCajaRemision
    {
        return DB::transaction(function () use ($caja, $usuarioId, $datos) {
            $caja = PedidoBmaCaja::query()->lockForUpdate()->findOrFail($caja->id);
            if ($caja->estaRecolectada()) {
                throw new RuntimeException('Una caja recolectada no cambia de contenido.');
            }

            $solicitudId = (string) ($datos['solicitud_id'] ?? '');
            if ($solicitudId === '') {
                throw new InvalidArgumentException('Falta la solicitud de asignación.');
            }
            $previa = PedidoBmaCajaRemision::query()->where('solicitud_id', $solicitudId)->first();
            if ($previa) {
                return $previa;
            }
            if (isset($datos['revision_esperada'], $datos['base_captura'])) {
                RevisionCapturaPedido::comprobar($caja->pedido()->firstOrFail(), $datos);
            }
            if (array_key_exists('cabe', $datos) && ! filter_var($datos['cabe'], FILTER_VALIDATE_BOOLEAN)) {
                throw new RuntimeException('La remisión no cabe en una caja. No hay despacho asegurado.');
            }

            $pedidoId = (int) ($datos['pedido_bma_id'] ?? 0);
            $folio = trim((string) ($datos['folio_externo'] ?? ''));
            if ($pedidoId < 1 && $folio === '') {
                throw new InvalidArgumentException('Indique la remisión.');
            }

            $ocupada = PedidoBmaCajaRemision::query()
                ->where('activa', true)
                ->where('pedido_bma_caja_id', '!=', $caja->id)
                ->when($pedidoId > 0, fn ($query) => $query->where('pedido_bma_id', $pedidoId))
                ->when($pedidoId < 1, fn ($query) => $query->where('folio_externo', $folio))
                ->exists();
            if ($ocupada) {
                throw new RuntimeException('Una remisión no se divide entre cajas.');
            }

            $origen = $pedidoId > 0 ? PedidoBma::query()->find($pedidoId) : null;
            $yaPagada = $origen && ($origen->pago_validado_at || ($origen->es_resguardo && filled($origen->numero_remision)));
            $valor = round((float) ($datos['valor_asegurado'] ?? $origen?->total_mercancia ?? 0), 2);

            try {
                return PedidoBmaCajaRemision::query()->create([
                    'pedido_bma_caja_id' => $caja->id,
                    'pedido_bma_id' => $pedidoId > 0 ? $pedidoId : null,
                    'solicitud_id' => $solicitudId,
                    'folio_externo' => $folio !== '' ? $folio : null,
                    'cliente_id' => $origen?->cliente_id,
                    'contenido' => trim((string) ($datos['contenido'] ?? '')),
                    'evidencia_documento_id' => isset($datos['evidencia_documento_id']) ? (int) $datos['evidencia_documento_id'] : null,
                    'valor_asegurado' => $valor,
                    'mercancia_nueva_por_cobrar' => $yaPagada ? 0 : round((float) ($datos['mercancia_nueva_por_cobrar'] ?? 0), 2),
                    'cabe' => true,
                    'activa' => true,
                    'exclusividad_activa' => $pedidoId > 0 ? 'p:'.$pedidoId : 'f:'.$folio,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new RuntimeException('Una remisión no se divide entre cajas.');
            }
        });
    }
}
