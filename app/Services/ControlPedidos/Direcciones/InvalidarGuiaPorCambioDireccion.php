<?php

namespace App\Services\ControlPedidos\Direcciones;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvalidarGuiaPorCambioDireccion
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId, string $motivo): PedidoBma
    {
        return DB::transaction(function () use ($pedido, $usuarioId, $motivo) {
            $estatusAnterior = $pedido->estatus;
            $rastreoAnterior = $pedido->numero_rastreo;

            $guias = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->get();
            foreach ($guias as $guia) {
                // Conservar archivo histórico: no borrar disco; marcar soft-context en historial.
                $guia->update([
                    'nombre_original' => '[INVALIDADA] '.($guia->nombre_original ?? 'guia.pdf'),
                ]);
            }

            $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA)
                ?? $estatusAnterior;

            $pedido->update([
                'numero_rastreo' => null,
                'guia_subida_at' => null,
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
            ]);

            $this->historial->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusNuevo,
                "Guía invalidada por cambio de dirección. Rastreo anterior: {$rastreoAnterior}. Motivo: {$motivo}"
            );

            return $pedido->fresh();
        });
    }
}
