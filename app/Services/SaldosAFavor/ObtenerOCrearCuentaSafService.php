<?php

namespace App\Services\SaldosAFavor;

use App\Models\Cliente;
use App\Models\SaldosAFavor\SafCuenta;
use Illuminate\Support\Facades\DB;

class ObtenerOCrearCuentaSafService
{
    public function handle(int $clienteId, string $moneda = 'MXN'): SafCuenta
    {
        $cliente = Cliente::findOrFail($clienteId);

        return SafCuenta::firstOrCreate(
            ['cliente_id' => $cliente->id, 'moneda' => $moneda],
            []
        );
    }

    public function handleLocked(int $clienteId, string $moneda = 'MXN'): SafCuenta
    {
        return DB::transaction(function () use ($clienteId, $moneda) {
            $cuenta = SafCuenta::where('cliente_id', $clienteId)
                ->where('moneda', $moneda)
                ->lockForUpdate()
                ->first();

            if ($cuenta) {
                return $cuenta;
            }

            return $this->handle($clienteId, $moneda);
        });
    }
}
