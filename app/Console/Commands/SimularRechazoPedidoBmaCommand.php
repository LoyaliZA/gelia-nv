<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use Illuminate\Console\Command;

class SimularRechazoPedidoBmaCommand extends Command
{
    protected $signature = 'control-pedidos:simular-rechazo
                            {--pedido= : ID del pedido a rechazar}
                            {--vendedor= : ID del vendedor para crear pedido de prueba}
                            {--motivo=Datos incompletos en comprobante : Motivo del rechazo}';

    protected $description = 'Simula un rechazo de auxiliar sobre un pedido BMA (solo QA Fase 1)';

    public function handle(RegistrarHistorialPedidoService $historialService): int
    {
        $pedidoId = $this->option('pedido');

        if ($pedidoId) {
            $pedido = PedidoBma::findOrFail($pedidoId);
        } else {
            $pedido = $this->crearPedidoPrueba();
        }

        $estatusAnterior = $pedido->estatus;
        $estatusRechazado = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA)
            ?? CatalogoEstatusPedido::porCodigo('NARANJA');

        if (!$estatusRechazado) {
            $this->error('No se encontró estatus RECHAZADO_VENDEDORA.');

            return self::FAILURE;
        }

        $motivo = (string) $this->option('motivo');
        $admin = User::role(['Super Admin', 'Administrador'])->first() ?? User::first();

        $pedido->update([
            'catalogo_estatus_pedido_id' => $estatusRechazado->id,
            'motivo_rechazo' => $motivo,
        ]);

        $historialService->registrarTransicion(
            $pedido->id,
            $admin->id,
            $estatusAnterior,
            $estatusRechazado,
            'Rechazo simulado (QA): ' . $motivo
        );

        $this->info("Pedido {$pedido->folio} (ID {$pedido->id}) marcado como rechazado.");

        return self::SUCCESS;
    }

    private function crearPedidoPrueba(): PedidoBma
    {
        $vendedorId = $this->option('vendedor') ?? User::first()?->id;
        $cliente = Cliente::first();
        $pendiente = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR);

        if (!$vendedorId || !$cliente || !$pendiente) {
            throw new \RuntimeException('Faltan datos base (usuario, cliente o estatus). Ejecute migraciones y seed.');
        }

        return PedidoBma::create([
            'folio' => 'PBMA-TEST-' . now()->format('His'),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $vendedorId,
            'cliente_id' => $cliente->id,
            'catalogo_estatus_pedido_id' => $pendiente->id,
            'total_mercancia' => 1000,
            'total_a_cobrar' => 1000,
        ]);
    }
}
