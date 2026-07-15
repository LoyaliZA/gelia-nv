<?php

namespace App\Console\Commands\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDireccion;
use App\Models\ClienteDireccion;
use Illuminate\Console\Command;

class DiagnosticarDireccionesPedidosCommand extends Command
{
    protected $signature = 'pedidos:diagnosticar-direcciones';

    protected $description = 'Diagnostica cobertura de snapshots de dirección en pedidos BMA';

    public function handle(): int
    {
        $total = PedidoBma::query()->count();
        $conSnapshot = PedidoBmaDireccion::query()->where('es_vigente', true)->distinct('pedido_bma_id')->count('pedido_bma_id');
        $conSeleccion = PedidoBma::query()->whereNotNull('cliente_direccion_id')->count();
        $soloTexto = PedidoBma::query()
            ->whereNotNull('domicilio_entrega')
            ->whereNull('cliente_direccion_id')
            ->count();
        $clientesSinDir = \App\Models\Cliente::query()
            ->whereDoesntHave('direcciones', fn ($q) => $q->where('esta_activa', true))
            ->count();

        $this->table(['Métrica', 'Valor'], [
            ['Pedidos totales', $total],
            ['Con snapshot vigente', $conSnapshot],
            ['Con cliente_direccion_id', $conSeleccion],
            ['Solo texto legado', $soloTexto],
            ['Clientes sin dirección activa', $clientesSinDir],
            ['Flag normalizadas', config('control_pedidos.direcciones_normalizadas') ? 'ON' : 'OFF'],
        ]);

        return self::SUCCESS;
    }
}
