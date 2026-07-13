<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;

class AsignarGuiaPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, string $numeroRastreo, int $usuarioId): PedidoBma
    {
        $guia = trim($numeroRastreo);

        if ($guia === '') {
            throw new \InvalidArgumentException('El número de guía es obligatorio.');
        }

        if ($pedido->estatus?->fase_ciclo !== CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA) {
            throw new \RuntimeException('El pedido no está pendiente de guía.');
        }

        $estatusPendienteEnvio = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO);

        if (!$estatusPendienteEnvio) {
            throw new \RuntimeException('No se encontró el estatus PENDIENTE_DE_ENVIO.');
        }

        return DB::transaction(function () use ($pedido, $guia, $usuarioId, $estatusPendienteEnvio) {
            $estatusAnterior = $pedido->estatus;

            $pedido->update([
                'numero_rastreo' => $guia,
                'guia_subida_at' => now(),
                'catalogo_estatus_pedido_id' => $estatusPendienteEnvio->id,
            ]);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusPendienteEnvio,
                "Guía de rastreo asignada: {$guia}"
            );

            return $pedido->fresh(['cliente', 'paqueteria', 'estatus', 'vendedor', 'documentos']);
        });
    }
}
