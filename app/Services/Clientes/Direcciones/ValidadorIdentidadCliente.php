<?php

namespace App\Services\Clientes\Direcciones;

use App\Models\Cliente;

class ValidadorIdentidadCliente
{
    /**
     * Compara numero_cliente como cadena exacta (preserva ceros a la izquierda).
     */
    public function buscarPorNumeroExacto(string $numeroCliente): ?Cliente
    {
        $numero = trim($numeroCliente);

        if ($numero === '') {
            return null;
        }

        return Cliente::query()
            ->where('numero_cliente', $numero)
            ->first();
    }

    public function coincideSegundoFactor(Cliente $cliente, ?string $correo = null, ?string $telefono = null): bool
    {
        $correo = $correo !== null ? mb_strtolower(trim($correo)) : null;
        $telefono = $telefono !== null ? preg_replace('/\D+/', '', $telefono) : null;

        if ($correo !== null && $correo !== '') {
            $registrado = mb_strtolower(trim((string) $cliente->correo_electronico));
            if ($registrado !== '' && hash_equals($registrado, $correo)) {
                return true;
            }
        }

        if ($telefono !== null && $telefono !== '') {
            $registrado = preg_replace('/\D+/', '', (string) $cliente->telefono) ?? '';
            if ($registrado !== '' && hash_equals($registrado, $telefono)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Coincidencia probable por número sin ceros o sufijo numérico (nunca auto-vincula).
     *
     * @return list<Cliente>
     */
    public function posiblesCoincidencias(string $numeroDeclarado, int $limite = 5): array
    {
        $numero = trim($numeroDeclarado);
        if ($numero === '') {
            return [];
        }

        $sinCeros = ltrim($numero, '0');
        if ($sinCeros === '') {
            $sinCeros = '0';
        }

        return Cliente::query()
            ->where(function ($q) use ($numero, $sinCeros) {
                $q->where('numero_cliente', $numero)
                    ->orWhere('numero_cliente', $sinCeros)
                    ->orWhere('numero_cliente', 'like', '%'.$sinCeros);
            })
            ->limit($limite)
            ->get()
            ->all();
    }
}
