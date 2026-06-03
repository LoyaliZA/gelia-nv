<?php

namespace App\Services\Clientes;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CorreccionEmergenciaNumeroClienteService
{
    /**
     * Actualiza identidad liberando un número ocupado (intercambio o reasignación temporal).
     */
    public function aplicar(Cliente $cliente, string $nuevoNumero, string $nuevoNombre, array $datosAdicionales): Cliente
    {
        return DB::transaction(function () use ($cliente, $nuevoNumero, $nuevoNombre, $datosAdicionales) {
            $conflicto = Cliente::where('numero_cliente', $nuevoNumero)
                ->where('id', '!=', $cliente->id)
                ->lockForUpdate()
                ->first();

            $numeroAnterior = $cliente->numero_cliente;
            $temporal = 'EMERG-' . $cliente->id . '-' . now()->format('YmdHis');

            $cliente->update(['numero_cliente' => $temporal]);

            if ($conflicto) {
                $pareceIntercambio = strlen($numeroAnterior) > 8
                    && preg_match('/[\p{L}]/u', $numeroAnterior);

                $numeroAnteriorLibre = !Cliente::where('numero_cliente', $numeroAnterior)
                    ->whereNotIn('id', [$cliente->id, $conflicto->id])
                    ->exists();

                if ($pareceIntercambio && $numeroAnteriorLibre) {
                    $conflicto->update(['numero_cliente' => $numeroAnterior]);
                    Log::warning('Corrección emergencia: intercambio de número de cliente', [
                        'cliente_corregido_id' => $cliente->id,
                        'cliente_conflicto_id' => $conflicto->id,
                        'numero_asignado' => $nuevoNumero,
                        'numero_intercambiado' => $numeroAnterior,
                    ]);
                } else {
                    $temporalConflicto = 'EMERG-' . $conflicto->id . '-' . now()->format('YmdHis');
                    $conflicto->update(['numero_cliente' => $temporalConflicto]);
                    Log::warning('Corrección emergencia: conflicto reasignado a número temporal', [
                        'cliente_corregido_id' => $cliente->id,
                        'cliente_conflicto_id' => $conflicto->id,
                        'numero_temporal' => $temporalConflicto,
                    ]);
                }
            }

            $cliente->update(array_merge($datosAdicionales, [
                'numero_cliente' => $nuevoNumero,
                'nombre' => $nuevoNombre,
            ]));

            return $cliente->fresh();
        });
    }
}
