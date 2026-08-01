<?php

namespace App\Services\Facturas;

use App\Models\Cliente;
use App\Support\Facturas\ReglasCatalogosFiscales;
use Illuminate\Support\Facades\DB;

class GestionarDatosFiscalesClienteService
{
    public function __construct(
        private RegistrarAuditoriaDatosFiscalesService $auditoria,
    ) {}

    public function actualizar(Cliente $cliente, array $datos, bool $auditar = true): Cliente
    {
        return DB::transaction(function () use ($cliente, $datos, $auditar) {
            if (array_key_exists('nombre_razon_social', $datos)) {
                $razon = ReglasCatalogosFiscales::normalizarRazonSocial($datos['nombre_razon_social'] ?? null);
                $datos['nombre_razon_social'] = $razon === '' ? null : $razon;
            }

            $campos = [
                'rfc',
                'codigo_postal',
                'regimen_fiscal',
                'correo_electronico',
                'uso_factura',
                'nombre_razon_social',
                'telefono',
            ];

            $payload = [];
            $tocados = [];
            foreach ($campos as $campo) {
                if (! array_key_exists($campo, $datos)) {
                    continue;
                }
                $payload[$campo] = $datos[$campo];
                $tocados[] = $campo;
            }

            if ($payload === []) {
                return $cliente->fresh() ?? $cliente;
            }

            $cliente->update($payload);
            if ($auditar) {
                $this->auditoria->clienteActualizado((int) $cliente->id, $tocados);
            }

            return $cliente->fresh();
        });
    }
}
