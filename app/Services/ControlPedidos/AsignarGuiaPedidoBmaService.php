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

        $pedido->loadMissing(['estatus', 'paqueteria', 'origen']);

        if ($pedido->es_resguardo) {
            throw new \RuntimeException('Un pedido en resguardo no puede recibir guía. Libere el resguardo primero.');
        }

        if (!$pedido->puedeAsignarGuia()) {
            throw new \RuntimeException('El pedido no está pendiente de guía.');
        }

        $fase = $pedido->estatus?->fase_ciclo;
        $yaEmpacado = $fase === CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA
            || $pedido->empacado_at !== null;

        return DB::transaction(function () use ($pedido, $guia, $usuarioId, $yaEmpacado) {
            $estatusAnterior = $pedido->estatus;

            if ($yaEmpacado) {
                $estatusPendienteEnvio = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO);

                if (!$estatusPendienteEnvio) {
                    throw new \RuntimeException('No se encontró el estatus PENDIENTE_DE_ENVIO.');
                }

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
            } else {
                $pedido->update([
                    'numero_rastreo' => $guia,
                    'guia_subida_at' => now(),
                ]);

                $this->historialService->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $estatusAnterior->id,
                    $estatusAnterior->id,
                    "Guía de rastreo asignada (pendiente de empaque): {$guia}"
                );
            }

            return $pedido->fresh(['cliente', 'paqueteria', 'estatus', 'vendedor', 'documentos', 'origen']);
        });
    }
}
