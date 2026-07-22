<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;

class ActualizarGuiaPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    public function ejecutar(PedidoBma $pedido, string $numeroRastreo, int $usuarioId): PedidoBma
    {
        $guia = trim($numeroRastreo);

        if ($guia === '') {
            throw new \InvalidArgumentException('El número de guía es obligatorio.');
        }

        if ($pedido->estatus?->fase_ciclo !== CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO) {
            throw new \RuntimeException('Solo se puede corregir la guía en pedidos pendientes de envío.');
        }

        if (empty($pedido->numero_rastreo)) {
            throw new \RuntimeException('El pedido no tiene guía asignada para corregir.');
        }

        return DB::transaction(function () use ($pedido, $guia, $usuarioId) {
            $anterior = $pedido->numero_rastreo;

            $pedido->update([
                'numero_rastreo' => $guia,
                'guia_retraso' => true,
                'guia_corregida_at' => now(),
                'guia_corregida_por_id' => $usuarioId,
            ]);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                "Guía de rastreo corregida (provoca retraso): {$anterior} → {$guia}"
            );

            $pedido = $pedido->fresh(['cliente', 'paqueteria', 'estatus', 'vendedor', 'documentos']);

            $this->notificarService->ejecutar(
                $pedido,
                'pedido_guia_retraso',
                "La guía del pedido fue corregida y provoca un retraso. Anterior: {$anterior}. Nueva: {$guia}.",
                ['control_pedidos.cedis', 'control_pedidos.auditar'],
                $usuarioId,
                false,
                ['url' => '/control-pedidos/cedis?tab=PENDIENTES_ENVIO&q='.urlencode((string) ($pedido->folio_remision ?: $pedido->id))]
            );

            return $pedido;
        });
    }
}
