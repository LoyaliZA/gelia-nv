<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;

class EnviarPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        if (!$pedido->esEditablePorVendedora()) {
            throw new \RuntimeException('Solo se pueden enviar pedidos en borrador o rechazados.');
        }

        $this->validarCamposRequeridos($pedido);

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $estatusAnterior = $pedido->estatus;
            $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR)
                ?? CatalogoEstatusPedido::porCodigo('AZUL_1');

            if (!$estatusNuevo) {
                throw new \RuntimeException('No se encontró el estatus PENDIENTE_AUXILIAR.');
            }

            $pedido->update([
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                'motivo_rechazo' => null,
            ]);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusNuevo,
                'Pedido enviado a revisión del auxiliar.'
            );

            return $pedido->fresh(['cliente', 'estatus', 'documentos', 'almacenSalida', 'banco']);
        });
    }

    private function validarCamposRequeridos(PedidoBma $pedido): void
    {
        $faltantes = [];

        if (!$pedido->cliente_id) {
            $faltantes[] = 'cliente';
        }
        if (!$pedido->catalogo_almacen_salida_id) {
            $faltantes[] = 'almacén de salida';
        }
        if (!$pedido->catalogo_banco_id) {
            $faltantes[] = 'banco';
        }
        if (!$pedido->catalogo_tipo_caja_id) {
            $faltantes[] = 'tipo de caja';
        }
        if (!$pedido->catalogo_paqueteria_id) {
            $faltantes[] = 'paquetería';
        }
        if (!$pedido->catalogo_tipo_guia_id) {
            $faltantes[] = 'tipo de guía';
        }
        if (!$pedido->catalogo_zona_id) {
            $faltantes[] = 'zona';
        }
        if (empty($pedido->codigo_postal)) {
            $faltantes[] = 'código postal';
        }
        if (empty($pedido->domicilio_entrega)) {
            $faltantes[] = 'domicilio de entrega';
        }
        if ($pedido->total_mercancia <= 0) {
            $faltantes[] = 'total de mercancía';
        }
        if ($pedido->documentos()->count() === 0) {
            $faltantes[] = 'comprobante de pago';
        }

        if (!empty($faltantes)) {
            throw new \InvalidArgumentException('Complete los campos requeridos: ' . implode(', ', $faltantes) . '.');
        }
    }
}
