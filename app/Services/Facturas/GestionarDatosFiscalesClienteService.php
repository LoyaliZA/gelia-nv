<?php

namespace App\Services\Facturas;

use App\Models\Cliente;
use App\Support\Facturas\ReglasCatalogosFiscales;
use Illuminate\Support\Facades\DB;

class GestionarDatosFiscalesClienteService
{
    public function actualizar(Cliente $cliente, array $datos): Cliente
    {
        return DB::transaction(function () use ($cliente, $datos) {
            if (array_key_exists('nombre_razon_social', $datos)) {
                $razon = ReglasCatalogosFiscales::normalizarRazonSocial($datos['nombre_razon_social'] ?? null);
                $datos['nombre_razon_social'] = $razon === '' ? null : $razon;
            }

            $cliente->update([
                'rfc' => $datos['rfc'] ?? null,
                'codigo_postal' => $datos['codigo_postal'] ?? null,
                'regimen_fiscal' => $datos['regimen_fiscal'] ?? null,
                'correo_electronico' => $datos['correo_electronico'] ?? null,
                'uso_factura' => $datos['uso_factura'] ?? null,
                'nombre_razon_social' => $datos['nombre_razon_social'] ?? null,
                'telefono' => $datos['telefono'] ?? null,
            ]);

            return $cliente->fresh();
        });
    }
}
