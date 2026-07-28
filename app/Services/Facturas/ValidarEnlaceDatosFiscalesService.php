<?php

namespace App\Services\Facturas;

use App\Models\EnlaceDatosFiscales;

class ValidarEnlaceDatosFiscalesService
{
    public function porToken(string $token): ?EnlaceDatosFiscales
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $hash = hash('sha256', $token);

        return EnlaceDatosFiscales::query()
            ->where(function ($q) use ($token, $hash) {
                $q->where('token_hash', $hash)
                    ->orWhere('codigo_publico', $token);
            })
            ->first();
    }

    public function ejecutar(string $token): EnlaceDatosFiscales
    {
        $enlace = $this->porToken($token);

        if (! $enlace) {
            throw new \InvalidArgumentException('Enlace no válido.');
        }

        if ($enlace->fueUsado()) {
            throw new \InvalidArgumentException('Este enlace ya fue utilizado.');
        }

        if (! $enlace->estaVigente()) {
            throw new \InvalidArgumentException('El enlace expiró o fue revocado.');
        }

        return $enlace;
    }
}
