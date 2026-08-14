<?php

namespace App\Services\SaldosAFavor;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\SafIncidencia;
use App\Models\User;
use App\Notifications\SaldoFavorIncidenciaAbiertaNotification;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\Notification;

class RegistrarIncidenciaSafService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function handle(
        string $tipo,
        string $descripcion,
        ?int $clienteId = null,
        ?int $pedidoBmaId = null,
        ?int $creditoId = null,
        ?int $usuarioId = null,
    ): SafIncidencia {
        $incidencia = SafIncidencia::create([
            'cliente_id' => $clienteId,
            'saf_credito_id' => $creditoId,
            'pedido_bma_id' => $pedidoBmaId,
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'estado' => SafIncidencia::ESTADO_ABIERTA,
            'creado_por_id' => $usuarioId,
        ]);

        // No aborta el pedido: solo bitácora + alerta para auxiliar.
        if ($pedidoBmaId) {
            $pedido = PedidoBma::query()->find($pedidoBmaId);
            $actorId = $usuarioId ?: (int) ($pedido?->vendedor_id ?? 0);
            if ($pedido && $actorId > 0) {
                $estatusId = (int) $pedido->catalogo_estatus_pedido_id;
                $this->historial->ejecutar(
                    $pedido->id,
                    $actorId,
                    $estatusId,
                    $estatusId,
                    $descripcion,
                    AccionesHistorialPedidoBma::INCIDENCIA_SAF
                );
            }
        }

        $revisores = User::permission('saldos_favor.revisar')->get();
        if ($revisores->isNotEmpty()) {
            Notification::send($revisores, new SaldoFavorIncidenciaAbiertaNotification($incidencia));
        }

        return $incidencia;
    }

    public function resolver(SafIncidencia $incidencia, ?int $usuarioId = null, ?string $nota = null): SafIncidencia
    {
        $descripcion = $incidencia->descripcion;
        if (filled($nota)) {
            $descripcion = trim($descripcion."\n\n[Resolución] ".$nota);
        }

        $updated = $incidencia->update([
            'descripcion' => $descripcion,
            'estado' => SafIncidencia::ESTADO_RESUELTA,
            'resuelto_por_id' => $usuarioId,
            'resuelto_at' => now(),
        ]);

        if (! $updated) {
            throw new \RuntimeException('No se pudo resolver la incidencia.');
        }

        if ($incidencia->pedido_bma_id && $usuarioId) {
            $pedido = PedidoBma::query()->find($incidencia->pedido_bma_id);
            if ($pedido) {
                $estatusId = (int) $pedido->catalogo_estatus_pedido_id;
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $estatusId,
                    $estatusId,
                    $nota ?: 'Incidencia de saldos a favor resuelta; se continúa el pedido.',
                    AccionesHistorialPedidoBma::CORRECCION_SAF
                );
            }
        }

        return $incidencia;
    }
}
